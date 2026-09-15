<?php

use App\Models\VideoTestimonial;

it('extracts the video id from a YouTube link', function (string $url) {
    expect(VideoTestimonial::youtubeIdFrom($url))->toBe('dQw4w9WgXcQ');
})->with([
    'watch link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'watch link with other parameters first' => 'https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42s',
    'short link' => 'https://youtu.be/dQw4w9WgXcQ?si=abc123',
    'shorts link' => 'https://youtube.com/shorts/dQw4w9WgXcQ',
    'mobile link' => 'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
    'embed link' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
]);

it('finds no video id in a link that is not a YouTube video', function (string $url) {
    expect(VideoTestimonial::youtubeIdFrom($url))->toBeNull();
})->with([
    'another host' => 'https://example.com/watch?v=dQw4w9WgXcQ',
    'lookalike host' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
    'channel page' => 'https://www.youtube.com/@fynnedge',
    'markup after the id' => 'https://youtu.be/dQw4w9WgXcQ"><script>alert(1)</script>',
    'empty' => '',
]);
