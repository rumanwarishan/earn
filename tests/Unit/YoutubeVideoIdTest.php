<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class YoutubeVideoIdTest extends TestCase
{
    public function test_extracts_id_from_watch_url(): void
    {
        $this->assertSame('dQw4w9WgXcQ', youtube_video_id('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_extracts_id_from_watch_url_with_extra_query_params_before_v(): void
    {
        $this->assertSame('dQw4w9WgXcQ', youtube_video_id('https://www.youtube.com/watch?list=PLxyz&v=dQw4w9WgXcQ&t=42s'));
    }

    public function test_extracts_id_from_short_url(): void
    {
        $this->assertSame('dQw4w9WgXcQ', youtube_video_id('https://youtu.be/dQw4w9WgXcQ'));
    }

    public function test_extracts_id_from_embed_url(): void
    {
        $this->assertSame('dQw4w9WgXcQ', youtube_video_id('https://www.youtube.com/embed/dQw4w9WgXcQ'));
    }

    public function test_extracts_id_from_shorts_url(): void
    {
        $this->assertSame('dQw4w9WgXcQ', youtube_video_id('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
    }

    public function test_extracts_id_from_nocookie_domain(): void
    {
        $this->assertSame('dQw4w9WgXcQ', youtube_video_id('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'));
    }

    public function test_accepts_bare_video_id_passthrough(): void
    {
        // Admins sometimes paste just the 11-character ID rather than a full
        // URL - this used to be silently rejected by the older, stricter
        // extractor even though it's an unambiguous, valid input.
        $this->assertSame('dQw4w9WgXcQ', youtube_video_id('dQw4w9WgXcQ'));
    }

    public function test_trims_surrounding_whitespace(): void
    {
        $this->assertSame('dQw4w9WgXcQ', youtube_video_id("  dQw4w9WgXcQ\n"));
    }

    public function test_returns_null_for_garbage_input(): void
    {
        $this->assertNull(youtube_video_id('not a url at all'));
        $this->assertNull(youtube_video_id(''));
        $this->assertNull(youtube_video_id('https://example.com/video'));
    }

    public function test_thumbnail_url_uses_hqdefault(): void
    {
        $this->assertSame('https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', youtube_thumbnail_url('dQw4w9WgXcQ'));
    }
}
