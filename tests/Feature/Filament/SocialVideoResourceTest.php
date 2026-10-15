<?php

use App\Enums\FaqPlacement;
use App\Enums\PublishStatus;
use App\Enums\SocialPlatform;
use App\Enums\VideoTestimonialSource;
use App\Filament\Resources\SocialVideos\Pages\CreateSocialVideo;
use App\Filament\Resources\SocialVideos\Pages\EditSocialVideo;
use App\Filament\Resources\SocialVideos\Pages\ListSocialVideos;
use App\Models\SocialVideo;
use App\Models\User;
use App\Support\SocialVideos\SocialVideos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('renders the social media videos index and edit pages', function () {
    $video = SocialVideo::factory()->create();

    $this->get('/admin/social-videos')->assertOk();
    $this->get("/admin/social-videos/{$video->public_id}/edit")->assertOk();
});

it('lists social media videos in the admin table', function () {
    $video = SocialVideo::factory()->create();

    Livewire::test(ListSocialVideos::class)
        ->assertCanSeeTableRecords([$video]);
});

it('stores an uploaded social media video on the public disk with its platform, post link and pages', function () {
    Livewire::test(CreateSocialVideo::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::Upload->value,
            'video_path' => UploadedFile::fake()->create('reel.mp4', 4000, 'video/mp4'),
            'poster_path' => UploadedFile::fake()->image('cover.jpg', 1080, 1350),
            'platform' => SocialPlatform::Facebook->value,
            'post_url' => 'https://www.facebook.com/fynnedge/videos/123',
            'title' => 'How CIBIL scores are calculated',
            'tagline' => 'Credit score basics',
            'cover_time' => 3.5,
            'placements' => [FaqPlacement::Home->value, FaqPlacement::Contact->value],
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $video = SocialVideo::query()->sole();

    expect($video->video_path)->toStartWith('social-videos/');
    expect($video->poster_path)->toStartWith('social-video-covers/');
    Storage::disk('public')->assertExists([$video->video_path, $video->poster_path]);
    expect($video->platform)->toBe(SocialPlatform::Facebook);
    expect($video->post_url)->toBe('https://www.facebook.com/fynnedge/videos/123');
    expect($video->tagline)->toBe('Credit score basics');
    expect($video->cover_time)->toBe(3.5);
    expect($video->placements)->toBe([FaqPlacement::Home->value, FaqPlacement::Contact->value]);
});

it('saves a YouTube social media video without a video file', function () {
    Livewire::test(CreateSocialVideo::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::YouTube->value,
            'youtube_url' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'platform' => SocialPlatform::YouTube->value,
            'title' => 'Fixed or floating rate?',
            'placements' => [SocialVideos::EVERY_PAGE],
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $video = SocialVideo::query()->sole();

    expect($video->video_source)->toBe(VideoTestimonialSource::YouTube);
    expect($video->video_path)->toBeNull();
});

it('requires a title, a platform, a video file and at least one page', function () {
    Livewire::test(CreateSocialVideo::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::Upload->value,
            'platform' => null,
            'title' => null,
            'placements' => [],
        ])
        ->call('create')
        ->assertHasFormErrors([
            'title' => 'required',
            'platform' => 'required',
            'video_path' => 'required',
            'placements' => 'required',
        ]);

    expect(SocialVideo::query()->exists())->toBeFalse();
});

it('rejects a post link that is not a web address', function () {
    Livewire::test(CreateSocialVideo::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::YouTube->value,
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'platform' => SocialPlatform::Instagram->value,
            'post_url' => 'javascript:alert(1)',
            'title' => 'Fixed or floating rate?',
            'placements' => [SocialVideos::EVERY_PAGE],
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['post_url'])
        ->assertSee('The post link must start with https:// (or http://).');

    expect(SocialVideo::query()->exists())->toBeFalse();
});

it('rejects a YouTube link that is not a YouTube video', function () {
    Livewire::test(CreateSocialVideo::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::YouTube->value,
            'youtube_url' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
            'platform' => SocialPlatform::YouTube->value,
            'title' => 'Fixed or floating rate?',
            'placements' => [SocialVideos::EVERY_PAGE],
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['youtube_url']);
});

it('rejects an upload that is not an MP4 or WebM video', function () {
    Livewire::test(CreateSocialVideo::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::Upload->value,
            'video_path' => UploadedFile::fake()->createWithContent('reel.html', '<script>alert(1)</script>'),
            'platform' => SocialPlatform::Instagram->value,
            'title' => 'Fixed or floating rate?',
            'placements' => [SocialVideos::EVERY_PAGE],
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['video_path']);

    expect(SocialVideo::query()->exists())->toBeFalse();
});

it('shows the saved video in the cover picker so a frame can be chosen from it', function () {
    Storage::disk('public')->put('social-videos/reel.mp4', 'fake-video-bytes');
    $video = SocialVideo::factory()->create(['video_path' => 'social-videos/reel.mp4']);

    Livewire::test(EditSocialVideo::class, ['record' => $video->public_id])
        ->assertSeeHtml('src="/storage/social-videos/reel.mp4"');
});

it('previews a freshly uploaded video in the cover picker before it is saved', function () {
    $mp4Header = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

    Livewire::test(CreateSocialVideo::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::Upload->value,
            'video_path' => UploadedFile::fake()->createWithContent('reel.mp4', $mp4Header),
        ])
        ->assertSeeHtml('x-ref="video"')
        ->assertDontSee('Upload the video above and it will appear here.');
});

it('asks for the video first when there is none to pick a cover frame from', function () {
    Livewire::test(CreateSocialVideo::class)
        ->assertSee('Upload the video above and it will appear here.')
        ->assertDontSeeHtml('x-ref="video"');
});

it('rejects a negative cover frame time', function () {
    Livewire::test(CreateSocialVideo::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::Upload->value,
            'video_path' => UploadedFile::fake()->create('reel.mp4', 4000, 'video/mp4'),
            'cover_time' => -2,
            'platform' => SocialPlatform::Instagram->value,
            'title' => 'Fixed or floating rate?',
            'placements' => [SocialVideos::EVERY_PAGE],
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['cover_time' => 'min']);
});
