<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Filters;

use Illuminate\Database\Eloquent\Relations\Relation;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Comment;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\Post;
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

    // ── Include: morphWith via {relation}Map() ────────────────────────────────

    public function test_include_uses_morph_with_per_type(): void
    {
        $this->registerMorphMap();

        $author  = User::create(['name' => 'Author']);
        $channel = User::create(['name' => 'Channel']);

        $post  = Post::create(['title' => 'P', 'author_id' => $author->id]);
        $video = Video::create(['title' => 'V', 'channel_id' => $channel->id]);

        $post->comments()->create(['body' => 'on post']);
        $video->comments()->create(['body' => 'on video']);

        $comments = Qubuilder::make([
            'include' => [[
                'name'    => 'commentable',
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

    // ── WHERE has on a MorphTo relation ───────────────────────────────────────

    public function test_where_has_morph_with_morph_map(): void
    {
        $this->registerMorphMap();

        $matching = Post::create(['title' => 'Laravel']);
        $other    = Post::create(['title' => 'Other']);

        $matching->comments()->create(['body' => 'c1']);
        $other->comments()->create(['body' => 'c2']);

        $comments = Qubuilder::make([
            'filter' => [[
                'field' => 'commentable',
                'op'    => 'has',
                'value' => ['AND' => [['field' => 'title', 'op' => '=', 'value' => 'Laravel']]],
            ]],
        ], Comment::class)->query()->get();

        $this->assertSame(['c1'], $comments->pluck('body')->all());
    }

    public function test_where_has_morph_without_morph_map_uses_wildcard(): void
    {
        // No morphMap registered -> commentable_type stored as FQCN, '*' path used.
        $post  = Post::create(['title' => 'Laravel']);
        $video = Video::create(['title' => 'Other']);

        $post->comments()->create(['body' => 'c1']);
        $video->comments()->create(['body' => 'c2']);

        $comments = Qubuilder::make([
            'filter' => [[
                'field' => 'commentable',
                'op'    => 'has',
                'value' => ['AND' => [['field' => 'title', 'op' => '=', 'value' => 'Laravel']]],
            ]],
        ], Comment::class)->query()->get();

        $this->assertSame(['c1'], $comments->pluck('body')->all());
    }
}
