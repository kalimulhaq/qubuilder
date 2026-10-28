<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Album;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Article;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\ArticleTopic;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Attachment;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Bookmark;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Course;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Order;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Post;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Topic;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\TestCase;
use ReflectionProperty;

/**
 * Which morph types a `has` / `doesnthave` on a `MorphTo` searches.
 *
 * `Bookmark::bookmarkable()` points at `Article`, `Course` and `Album`, and all three
 * define `topics()` with a different shape: a `HasMany` to `article_topics` (has
 * `subject_id`), a `BelongsToMany` through `course_topic` (has `level`), and a `HasMany`
 * to `album_topics` (has neither). A sub-filter that fits one shape is invalid SQL for
 * the others, and MySQL rejects the whole statement. SQLite reads an unknown quoted
 * column as a string literal instead, so the tests check which sub-queries are built.
 */
class MorphTypeNarrowingTest extends TestCase
{
    /** @var array<string, int> Bookmark ids by name. */
    private array $ids = [];

    protected function tearDown(): void
    {
        // morphMap and relation resolvers are global static state — clear them between tests.
        Relation::morphMap([], false);

        $resolvers = new ReflectionProperty(Model::class, 'relationResolvers');
        $resolvers->setValue(null, []);

        parent::tearDown();
    }

    // ── A type condition narrows the morph types ────────────────────────────

    public function test_type_condition_in_the_same_and_group_narrows_to_that_type(): void
    {
        // The reported shape: the type is named next to a two-level nested `has`.
        $this->seedBookmarks();

        $query = $this->bookmarks(['and' => [
            ['field' => 'bookmarkable_type', 'op' => '=', 'value' => 'Article'],
            $this->topicsHas(['field' => 'subject_id', 'op' => 'in', 'value' => [1]]),
        ]]);

        $this->assertSearches(['articles'], $query);
        $this->assertSame([$this->ids['article1']], $this->idsOf($query));
    }

    public function test_type_condition_in_an_enclosing_and_group_narrows_a_has_in_a_nested_or_group(): void
    {
        $this->seedBookmarks();

        $query = $this->bookmarks(['and' => [
            ['field' => 'bookmarkable_type', 'value' => 'Article'],
            ['or' => [
                $this->topicsHas(['field' => 'id', 'op' => '>=', 'value' => 1]),
                ['field' => 'note', 'value' => 'pinned'],
            ]],
        ]]);

        $this->assertSearches(['articles'], $query);
        $this->assertSame([$this->ids['article1'], $this->ids['article2']], $this->idsOf($query));
    }

    public function test_table_qualified_type_column_narrows_too(): void
    {
        $this->seedBookmarks();

        $query = $this->bookmarks(['and' => [
            ['field' => 'bookmarks.bookmarkable_type', 'op' => 'in', 'value' => ['Course']],
            $this->topicsHas(['field' => 'id', 'op' => '>=', 'value' => 1]),
        ]]);

        $this->assertSearches(['courses'], $query);
        $this->assertSame([$this->ids['course']], $this->idsOf($query));
    }

    public function test_type_condition_matches_the_morph_alias_case_insensitively(): void
    {
        // MySQL's default collations compare 'article ' and 'Article' as equal, so the
        // narrowing must too. SQLite compares exactly, so only the plan is checked here.
        $this->seedBookmarks();

        $query = $this->bookmarks(['and' => [
            ['field' => 'bookmarkable_type', 'value' => 'article '],
            $this->topicsHas(['field' => 'id', 'op' => '>=', 'value' => 1]),
        ]]);

        $this->assertSearches(['articles'], $query);
    }

    public function test_or_group_of_type_conditions_counts_as_one_in_condition(): void
    {
        $this->seedBookmarks();

        $query = $this->bookmarks(['and' => [
            ['or' => [
                ['field' => 'bookmarkable_type', 'value' => 'Course'],
                ['field' => 'bookmarkable_type', 'value' => 'Album'],
            ]],
            $this->topicsHas(['field' => 'id', 'op' => '>=', 'value' => 1]),
        ]]);

        $this->assertSearches(['courses', 'albums'], $query);
        $this->assertSame([$this->ids['course'], $this->ids['album']], $this->idsOf($query));
    }

    public function test_type_condition_as_an_or_alternative_does_not_narrow(): void
    {
        // "Course OR has(...)": a row of another type can still match the `has`.
        $this->seedBookmarks();

        $query = $this->bookmarks(['or' => [
            ['field' => 'bookmarkable_type', 'value' => 'Course'],
            $this->topicsHas(['field' => 'subject_id', 'op' => 'in', 'value' => [1]]),
        ]]);

        $this->assertSearches(['articles'], $query);
        $this->assertSame([$this->ids['article1'], $this->ids['course']], $this->idsOf($query));
    }

    public function test_type_value_that_names_no_known_type_disables_the_narrowing(): void
    {
        // 'Podcast' is not a morph type here; narrowing on a value it cannot place could
        // drop rows the SQL keeps, so the condition is not used.
        $this->seedBookmarks();

        $query = $this->bookmarks(['and' => [
            ['field' => 'bookmarkable_type', 'op' => 'in', 'value' => ['Article', 'Podcast']],
            $this->topicsHas(['field' => 'id', 'op' => '>=', 'value' => 1]),
        ]]);

        $this->assertSearches(['articles', 'courses', 'albums'], $query);
        $this->assertSame([$this->ids['article1'], $this->ids['article2']], $this->idsOf($query));
    }

    // ── Each type is checked against the sub-filter ───────────────────────────

    public function test_types_whose_related_table_lacks_the_column_get_no_sub_query(): void
    {
        // No type condition: the check alone keeps Article (`subject_id` exists even though it
        // is not in ArticleTopic::$fillable) and drops Course and Album.
        $this->seedBookmarks();
        $this->assertNotContains('subject_id', (new ArticleTopic)->getFillable());

        $query = $this->bookmarks(['and' => [
            $this->topicsHas(['field' => 'subject_id', 'op' => 'in', 'value' => [1]]),
        ]]);

        $this->assertSearches(['articles'], $query);
        $this->assertSame([$this->ids['article1']], $this->idsOf($query));
    }

    public function test_pivot_column_keeps_only_the_type_whose_pivot_has_it(): void
    {
        $this->seedBookmarks();

        $query = $this->bookmarks(['and' => [
            $this->topicsHas(['field' => 'level', 'op' => '=', 'value' => 2]),
        ]]);

        $this->assertSearches(['courses'], $query);
        $this->assertSame([$this->ids['course']], $this->idsOf($query));
    }

    public function test_intermediate_column_of_a_through_relation_keeps_the_type(): void
    {
        // User::items() goes through orders; `status` is an orders column, not an items one.
        Relation::morphMap(['post' => Post::class, 'user' => User::class], false);

        $buyer = User::create(['name' => 'Buyer']);
        Order::create(['user_id' => $buyer->id, 'status' => 'completed'])->items()->create(['qty' => 1]);
        $post = Post::create(['title' => 'P']);

        $onBuyer = (new Attachment)->attachable()->associate($buyer);
        $onBuyer->save();
        (new Attachment)->attachable()->associate($post)->save();

        $query = Qubuilder::make(['filter' => ['and' => [[
            'field' => 'attachable',
            'op' => 'has',
            'value' => ['and' => [[
                'field' => 'items',
                'op' => 'has',
                'value' => ['and' => [['field' => 'status', 'value' => 'completed']]],
            ]]],
        ]]]], Attachment::class)->query();

        $this->assertStringContainsString('"qubuilder_users"', $query->toSql());
        $this->assertStringNotContainsString('"qubuilder_posts"', $query->toSql());
        $this->assertSame([$onBuyer->id], $query->pluck('id')->all());
    }

    public function test_relation_added_with_resolve_relation_using_is_found_at_a_nested_level(): void
    {
        $this->seedBookmarks();
        ArticleTopic::resolveRelationUsing('article', fn (ArticleTopic $topic) => $topic->belongsTo(Article::class, 'article_id'));

        $query = $this->bookmarks(['and' => [
            $this->topicsHas([
                'field' => 'article',
                'op' => 'has',
                'value' => ['and' => [['field' => 'title', 'value' => 'First']]],
            ]),
        ]]);

        $this->assertSearches(['articles'], $query);
        $this->assertSame([$this->ids['article1']], $this->idsOf($query));
    }

    public function test_morph_map_types_are_limited_to_the_types_actually_stored(): void
    {
        // The morph map is app-wide. Album is in it and could run the sub-filter, but no
        // bookmark points at an album, so it gets no sub-query.
        Relation::morphMap(['Article' => Article::class, 'Course' => Course::class, 'Album' => Album::class], false);
        $article = Article::create(['title' => 'Only']);
        $article->topics()->forceCreate(['subject_id' => 1]);
        $bookmark = (new Bookmark)->bookmarkable()->associate($article);
        $bookmark->save();

        $query = $this->bookmarks(['and' => [
            $this->topicsHas(['field' => 'id', 'op' => '>=', 'value' => 1]),
        ]]);

        $this->assertSearches(['articles'], $query);
        $this->assertSame([$bookmark->id], $this->idsOf($query));
    }

    // ── No eligible type ───────────────────────────────────────────────────

    public function test_has_with_no_eligible_type_matches_no_rows(): void
    {
        $this->seedBookmarks();

        $query = $this->bookmarks(['and' => [
            ['field' => 'bookmarkable_type', 'value' => 'Album'],
            $this->topicsHas(['field' => 'subject_id', 'op' => 'in', 'value' => [1]]),
        ]]);

        $this->assertStringContainsString('0 = 1', $query->toSql());
        $this->assertSame([], $this->idsOf($query));
    }

    public function test_doesnthave_with_no_eligible_type_matches_every_row(): void
    {
        $this->seedBookmarks();

        $query = $this->bookmarks(['and' => [
            ['field' => 'bookmarkable_type', 'value' => 'Album'],
            $this->topicsDoesntHave(['field' => 'subject_id', 'op' => 'in', 'value' => [1]]),
        ]]);

        $this->assertSame([$this->ids['album']], $this->idsOf($query));
    }

    public function test_doesnthave_with_no_eligible_type_keeps_a_following_or_condition_correct(): void
    {
        // TRUE OR anything is every row. If the empty group were dropped, the OR item would
        // become the first clause and the filter would return only the 'pinned' bookmark.
        $this->seedBookmarks();

        $query = $this->bookmarks([
            'AND' => [$this->topicsDoesntHave(['field' => 'no_such_column', 'value' => 1])],
            'OR' => [['field' => 'note', 'value' => 'pinned']],
        ]);

        $this->assertCount(count($this->ids), $this->idsOf($query));
    }

    // ── No morph map ─────────────────────────────────────────────────────────

    public function test_without_a_morph_map_a_class_named_in_the_request_is_never_instantiated(): void
    {
        // Types are stored as class names. The type condition names a class that is no morph
        // type at all; it must not be used to build a sub-query from that class.
        $article = Article::create(['title' => 'First']);
        $article->topics()->forceCreate(['subject_id' => 1]);
        (new Bookmark)->bookmarkable()->associate($article)->save();

        $query = $this->bookmarks(['and' => [
            ['field' => 'bookmarkable_type', 'value' => Pivot::class],
            $this->topicsHas(['field' => 'id', 'op' => '>=', 'value' => 1]),
        ]]);

        $this->assertStringNotContainsString('pivots', $query->toSql());
        $this->assertSame([], $this->idsOf($query));
    }

    /**
     * Two articles (subjects 1 and 2), a course with a level-2 topic, an album, and a
     * bookmark with no type, each bookmarked once. The course bookmark is 'pinned'.
     */
    private function seedBookmarks(): void
    {
        Relation::morphMap(['Article' => Article::class, 'Course' => Course::class, 'Album' => Album::class], false);

        $first = Article::create(['title' => 'First']);
        $first->topics()->forceCreate(['subject_id' => 1, 'weight' => 5]);
        $second = Article::create(['title' => 'Second']);
        $second->topics()->forceCreate(['subject_id' => 2]);

        $course = Course::create(['title' => 'Course']);
        $course->topics()->attach(Topic::create(['name' => 'Intro'])->id, ['level' => 2]);

        $album = Album::create(['title' => 'Album']);
        $album->topics()->create(['label' => 'Live']);

        foreach (['article1' => $first, 'article2' => $second, 'course' => $course, 'album' => $album] as $name => $target) {
            $bookmark = (new Bookmark(['note' => $name === 'course' ? 'pinned' : null]))->bookmarkable()->associate($target);
            $bookmark->save();
            $this->ids[$name] = $bookmark->id;
        }

        $this->ids['untyped'] = Bookmark::create([])->id;
    }

    /**
     * @param  array<int|string, mixed>  $filter
     * @return Builder<Bookmark>
     */
    private function bookmarks(array $filter): Builder
    {
        return Qubuilder::make(['filter' => $filter], Bookmark::class)->query();
    }

    /**
     * `bookmarkable has { topics has { <condition> } }`
     *
     * @param  array<string, mixed>  $condition
     * @return array<string, mixed>
     */
    private function topicsHas(array $condition, string $op = 'has'): array
    {
        return [
            'field' => 'bookmarkable',
            'op' => $op,
            'value' => ['and' => [['field' => 'topics', 'op' => 'has', 'value' => ['and' => [$condition]]]]],
        ];
    }

    /**
     * `bookmarkable doesnthave { topics has { <condition> } }`
     *
     * @param  array<string, mixed>  $condition
     * @return array<string, mixed>
     */
    private function topicsDoesntHave(array $condition): array
    {
        return $this->topicsHas($condition, 'doesnthave');
    }

    /**
     * Assert that the query builds a morph sub-query for exactly these type tables.
     *
     * @param  array<int, string>  $tables
     * @param  Builder<Bookmark>  $query
     */
    private function assertSearches(array $tables, Builder $query): void
    {
        $sql = $query->toSql();

        foreach (['articles', 'courses', 'albums'] as $table) {
            $built = str_contains($sql, 'exists (select * from "qubuilder_'.$table.'"');

            $this->assertSame(in_array($table, $tables, true), $built, "Sub-query for {$table}: ".$sql);
        }
    }

    /**
     * @param  Builder<Bookmark>  $query
     * @return array<int, int>
     */
    private function idsOf(Builder $query): array
    {
        return $query->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
