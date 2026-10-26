<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Filters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Attachment;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Comment;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Podcast;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Post;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Profile;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Reaction;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Video;
use Kalimulhaq\Qubuilder\Tests\TestCase;

class MorphTest extends TestCase
{
    protected function tearDown(): void
    {
        // morphMap is global static state — clear it between tests.
        Relation::morphMap([], false);

        parent::tearDown();
    }

    private function registerMorphMap(): void
    {
        Relation::morphMap(['post' => Post::class, 'video' => Video::class], false);
    }

    /**
     * Podcast is a morph type of the app but not of the relation maps of Comment and Reaction.
     */
    private function registerMorphMapWithPodcast(): void
    {
        Relation::morphMap(['post' => Post::class, 'video' => Video::class, 'podcast' => Podcast::class], false);
    }

    // ── Include: morphWith via {relation}Map() ────────────────────────────────

    public function test_include_uses_morph_with_per_type(): void
    {
        $this->registerMorphMap();

        $author = User::create(['name' => 'Author']);
        $channel = User::create(['name' => 'Channel']);

        $post = Post::create(['title' => 'P', 'author_id' => $author->id]);
        $video = Video::create(['title' => 'V', 'channel_id' => $channel->id]);

        $post->comments()->create(['body' => 'on post']);
        $video->comments()->create(['body' => 'on video']);

        $comments = Qubuilder::make([
            'include' => [[
                'name' => 'commentable',
                'include' => [['name' => 'author'], ['name' => 'channel']],
            ]],
        ], Comment::class)->query()->get();

        $onPost = $comments->firstWhere('body', 'on post');
        $this->assertInstanceOf(Post::class, $onPost->commentable);
        $this->assertTrue($onPost->commentable->relationLoaded('author'));
        $this->assertSame('Author', $onPost->commentable->author->name);

        $onVideo = $comments->firstWhere('body', 'on video');
        $this->assertInstanceOf(Video::class, $onVideo->commentable);
        $this->assertTrue($onVideo->commentable->relationLoaded('channel'));
        $this->assertSame('Channel', $onVideo->commentable->channel->name);
    }

    public function test_include_morph_sub_include_applies_its_own_select_and_filter(): void
    {
        $this->registerMorphMap();

        $keep = User::create(['name' => 'Keep', 'email' => 'keep@example.com']);
        $drop = User::create(['name' => 'Drop', 'email' => 'drop@example.com']);

        Post::create(['title' => 'P1', 'author_id' => $keep->id])->comments()->create(['body' => 'keep']);
        Post::create(['title' => 'P2', 'author_id' => $drop->id])->comments()->create(['body' => 'drop']);

        $comments = Qubuilder::make([
            'include' => [[
                'name' => 'commentable',
                'include' => [[
                    'name' => 'author',
                    'select' => ['id', 'name'],
                    'filter' => [['field' => 'name', 'op' => '=', 'value' => 'Keep']],
                ]],
            ]],
        ], Comment::class)->query()->get();

        $kept = $comments->firstWhere('body', 'keep')->commentable->author;
        $this->assertSame('Keep', $kept->name);
        $this->assertArrayNotHasKey('email', $kept->getAttributes());

        // The sub-include's own filter excludes the second author: loaded, but empty.
        $dropped = $comments->firstWhere('body', 'drop')->commentable;
        $this->assertTrue($dropped->relationLoaded('author'));
        $this->assertNull($dropped->author);
    }

    public function test_include_morph_with_fqcn_map_keys(): void
    {
        // No morphMap registered -> morph type is stored as the FQCN, and the map is
        // keyed by FQCN (Post::class / Video::class). Mirrors the real-world usage.
        $author = User::create(['name' => 'Author']);
        $channel = User::create(['name' => 'Channel']);

        $post = Post::create(['title' => 'P', 'author_id' => $author->id]);
        $video = Video::create(['title' => 'V', 'channel_id' => $channel->id]);

        $onPost = new Reaction;
        $onPost->reactable()->associate($post)->save();

        $onVideo = new Reaction;
        $onVideo->reactable()->associate($video)->save();

        $reactions = Qubuilder::make([
            'include' => [[
                'name' => 'reactable',
                'include' => [['name' => 'author'], ['name' => 'channel']],
            ]],
        ], Reaction::class)->query()->get();

        $postReaction = $reactions->first(fn ($r) => $r->reactable_type === Post::class);
        $videoReaction = $reactions->first(fn ($r) => $r->reactable_type === Video::class);

        $this->assertInstanceOf(Post::class, $postReaction->reactable);
        $this->assertTrue($postReaction->reactable->relationLoaded('author'));
        $this->assertSame('Author', $postReaction->reactable->author->name);

        $this->assertInstanceOf(Video::class, $videoReaction->reactable);
        $this->assertTrue($videoReaction->reactable->relationLoaded('channel'));
        $this->assertSame('Channel', $videoReaction->reactable->channel->name);
    }

    public function test_include_morph_with_fqcn_keys_and_registered_morph_map(): void
    {
        // Mirrors engage/core: a morph map is enforced (type stored as an alias) while
        // the model's {relation}Map() is keyed by FQCN. The FQCN key must still resolve.
        $this->registerMorphMap();

        $author = User::create(['name' => 'Author']);
        $post = Post::create(['title' => 'P', 'author_id' => $author->id]);

        $reaction = new Reaction;
        $reaction->reactable()->associate($post)->save();

        $loaded = Qubuilder::make([
            'include' => [[
                'name' => 'reactable',
                'include' => [['name' => 'author']],
            ]],
        ], Reaction::class)->query()->first();

        $this->assertInstanceOf(Post::class, $loaded->reactable);
        $this->assertTrue($loaded->reactable->relationLoaded('author'));
        $this->assertSame('Author', $loaded->reactable->author->name);
    }

    // ── WHERE has on a MorphTo relation ───────────────────────────────────────

    public function test_where_has_morph_with_morph_map(): void
    {
        $this->registerMorphMap();

        $matching = Post::create(['title' => 'Laravel']);
        $other = Post::create(['title' => 'Other']);

        $matching->comments()->create(['body' => 'c1']);
        $other->comments()->create(['body' => 'c2']);

        $comments = Qubuilder::make([
            'filter' => [[
                'field' => 'commentable',
                'op' => 'has',
                'value' => ['AND' => [['field' => 'title', 'op' => '=', 'value' => 'Laravel']]],
            ]],
        ], Comment::class)->query()->get();

        $this->assertSame(['c1'], $comments->pluck('body')->all());
    }

    public function test_where_has_morph_without_morph_map_uses_wildcard(): void
    {
        // No morphMap registered -> commentable_type stored as FQCN, '*' path used.
        $post = Post::create(['title' => 'Laravel']);
        $video = Video::create(['title' => 'Other']);

        $post->comments()->create(['body' => 'c1']);
        $video->comments()->create(['body' => 'c2']);

        $comments = Qubuilder::make([
            'filter' => [[
                'field' => 'commentable',
                'op' => 'has',
                'value' => ['AND' => [['field' => 'title', 'op' => '=', 'value' => 'Laravel']]],
            ]],
        ], Comment::class)->query()->get();

        $this->assertSame(['c1'], $comments->pluck('body')->all());
    }

    // ── WHERE has on a MorphTo relation with a {relation}Map() ────────────────

    public function test_where_has_morph_builds_no_subquery_for_types_outside_the_relation_map(): void
    {
        // Podcast has an `author` relation too, but its author is a Profile (no `name`
        // column) and Podcast is not in Reaction::reactableMap(). Searching it fails with
        // "Unknown column 'name'" on MySQL. SQLite reads the unknown column as a string
        // literal and stays silent, so the generated SQL is checked as well.
        $this->registerMorphMapWithPodcast();

        $ann = User::create(['name' => 'Ann']);
        $bob = User::create(['name' => 'Bob']);

        $annsReaction = $this->reactTo(Post::create(['title' => 'P1', 'author_id' => $ann->id]));
        $this->reactTo(Post::create(['title' => 'P2', 'author_id' => $bob->id]));
        $this->reactTo(Podcast::create(['title' => 'Pod', 'author_id' => Profile::create(['bio' => 'Ann'])->id]));

        $query = Qubuilder::make([
            'filter' => [[
                'field' => 'reactable',
                'op' => 'has',
                'value' => ['AND' => [[
                    'field' => 'author',
                    'op' => 'has',
                    'value' => ['AND' => [['field' => 'name', 'op' => '=', 'value' => 'Ann']]],
                ]]],
            ]],
        ], Reaction::class)->query();

        $this->assertStringNotContainsString('podcasts', $query->toSql());
        $this->assertSame([$annsReaction->id], $query->get()->pluck('id')->all());
    }

    public function test_where_has_morph_only_searches_the_types_in_the_relation_map(): void
    {
        $this->registerMorphMapWithPodcast();

        $post = Post::create(['title' => 'Same']);
        $podcast = Podcast::create(['title' => 'Same']);

        $postReaction = $this->reactTo($post);
        $this->reactTo($podcast);

        $reactions = Qubuilder::make([
            'filter' => [[
                'field' => 'reactable',
                'op' => 'has',
                'value' => ['AND' => [['field' => 'title', 'op' => '=', 'value' => 'Same']]],
            ]],
        ], Reaction::class)->query()->get();

        $this->assertSame([$postReaction->id], $reactions->pluck('id')->all());
    }

    public function test_where_has_morph_resolves_alias_keys_of_the_relation_map(): void
    {
        // Comment::commentableMap() is keyed by morph aliases ('post', 'video'); the
        // registered morph map turns them into classes. Podcast is not in that map.
        $this->registerMorphMapWithPodcast();

        $author = User::create(['name' => 'Ann', 'bio' => 'shared']);
        $profile = Profile::create(['bio' => 'shared']);

        $post = Post::create(['title' => 'P', 'author_id' => $author->id]);
        $podcast = Podcast::create(['title' => 'Pod', 'author_id' => $profile->id]);

        $post->comments()->create(['body' => 'on post']);
        Comment::create(['body' => 'on podcast', 'commentable_type' => 'podcast', 'commentable_id' => $podcast->id]);

        $comments = Qubuilder::make([
            'filter' => [[
                'field' => 'commentable',
                'op' => 'has',
                'value' => ['AND' => [[
                    'field' => 'author',
                    'op' => 'has',
                    'value' => ['AND' => [['field' => 'bio', 'op' => '=', 'value' => 'shared']]],
                ]]],
            ]],
        ], Comment::class)->query()->get();

        $this->assertSame(['on post'], $comments->pluck('body')->all());
    }

    public function test_where_has_morph_without_a_relation_map_searches_every_type_of_the_morph_map(): void
    {
        $this->registerMorphMapWithPodcast();

        $post = Post::create(['title' => 'Same']);
        $podcast = Podcast::create(['title' => 'Same']);
        $video = Video::create(['title' => 'Other']);

        foreach ([$post, $podcast, $video] as $attachable) {
            $attachment = new Attachment;
            $attachment->attachable()->associate($attachable)->save();
        }

        $attachments = Qubuilder::make([
            'filter' => [[
                'field' => 'attachable',
                'op' => 'has',
                'value' => ['AND' => [['field' => 'title', 'op' => '=', 'value' => 'Same']]],
            ]],
        ], Attachment::class)->query()->get();

        $this->assertEqualsCanonicalizing(['post', 'podcast'], $attachments->pluck('attachable_type')->all());
    }

    // ── WHERE has on a dotted path through a MorphTo ──────────────────────────

    public function test_where_has_dotted_path_through_morph_skips_types_without_the_relation(): void
    {
        // Laravel's own nested has() runs `has('author')` on every stored type and fails
        // with "Call to undefined method Video::author()". The package unrolls the path
        // and uses the relation map, so Video gets no sub-query at all.
        $this->registerMorphMap();
        $this->seedCommentsByAuthor();

        $query = Qubuilder::make([
            'filter' => [[
                'field' => 'commentable.author',
                'op' => 'has',
                'value' => ['AND' => [['field' => 'name', 'op' => '=', 'value' => 'Ann']]],
            ]],
        ], Comment::class)->query();

        $this->assertStringNotContainsString('videos', $query->toSql());
        $this->assertSame(['by Ann'], $query->get()->pluck('body')->all());
    }

    public function test_where_has_dotted_path_through_morph_with_count_form(): void
    {
        $this->registerMorphMap();
        $this->seedCommentsByAuthor();

        $comments = Qubuilder::make([
            'filter' => [['field' => 'commentable.author', 'op' => 'has|>=', 'value' => 1]],
        ], Comment::class)->query()->get();

        $this->assertSame(['by Ann', 'by Bob'], $comments->pluck('body')->all());
    }

    public function test_where_doesnt_have_dotted_path_through_morph(): void
    {
        $this->registerMorphMap();
        $this->seedCommentsByAuthor();

        $query = Qubuilder::make([
            'filter' => [[
                'field' => 'commentable.author',
                'op' => 'doesnthave',
                'value' => ['AND' => [['field' => 'name', 'op' => '=', 'value' => 'Ann']]],
            ]],
        ], Comment::class)->query();

        $this->assertStringContainsString('not exists', $query->toSql());
        $this->assertSame(['by Bob'], $query->get()->pluck('body')->all());
    }

    public function test_where_has_dotted_path_without_any_map_guards_types_without_the_relation(): void
    {
        // No morphMap and no attachableMap(): the '*' path is used, and the Video type
        // (no `author` relation) is excluded inside the callback with `0 = 1`.
        $ann = User::create(['name' => 'Ann']);
        $post = Post::create(['title' => 'P', 'author_id' => $ann->id]);
        $video = Video::create(['title' => 'V']);

        foreach ([$post, $video] as $attachable) {
            $attachment = new Attachment;
            $attachment->attachable()->associate($attachable)->save();
        }

        $query = Qubuilder::make([
            'filter' => [[
                'field' => 'attachable.author',
                'op' => 'has',
                'value' => ['AND' => [['field' => 'name', 'op' => '=', 'value' => 'Ann']]],
            ]],
        ], Attachment::class)->query();

        $this->assertStringContainsString('0 = 1', $query->toSql());
        $this->assertSame([Post::class], $query->get()->pluck('attachable_type')->all());
    }

    public function test_where_has_dotted_path_with_morph_as_second_segment(): void
    {
        // The MorphTo is the second segment: Post -> comments -> commentable -> author.
        // Each segment is compiled on its own model, so the morph handling applies there too.
        $this->registerMorphMap();
        $this->seedCommentsByAuthor();

        $posts = Qubuilder::make([
            'filter' => [[
                'field' => 'comments.commentable.author',
                'op' => 'has',
                'value' => ['AND' => [['field' => 'name', 'op' => '=', 'value' => 'Ann']]],
            ]],
        ], Post::class)->query()->get();

        $this->assertSame(['By Ann'], $posts->pluck('title')->all());
    }

    /**
     * One comment on a post by Ann, one on a post by Bob, one on a video (no `author` relation).
     */
    private function seedCommentsByAuthor(): void
    {
        $ann = User::create(['name' => 'Ann']);
        $bob = User::create(['name' => 'Bob']);

        Post::create(['title' => 'By Ann', 'author_id' => $ann->id])->comments()->create(['body' => 'by Ann']);
        Post::create(['title' => 'By Bob', 'author_id' => $bob->id])->comments()->create(['body' => 'by Bob']);
        Video::create(['title' => 'Clip', 'channel_id' => $ann->id])->comments()->create(['body' => 'on video']);
    }

    private function reactTo(Model $reactable): Reaction
    {
        $reaction = new Reaction;
        $reaction->reactable()->associate($reactable)->save();

        return $reaction;
    }
}
