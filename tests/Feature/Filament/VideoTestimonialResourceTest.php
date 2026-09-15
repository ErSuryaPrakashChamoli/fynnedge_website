<?php

use App\Enums\FaqPlacement;
use App\Enums\PublishStatus;
use App\Enums\VideoTestimonialSource;
use App\Filament\Resources\VideoTestimonials\Pages\CreateVideoTestimonial;
use App\Filament\Resources\VideoTestimonials\Pages\ListVideoTestimonials;
use App\Models\User;
use App\Models\VideoTestimonial;
use App\Support\Testimonials\VideoTestimonials;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('renders the video testimonials index and edit pages', function () {
    $testimonial = VideoTestimonial::factory()->create();

    $this->get('/admin/video-testimonials')->assertOk();
    $this->get("/admin/video-testimonials/{$testimonial->public_id}/edit")->assertOk();
});

it('lists video testimonials in the admin table', function () {
    $testimonial = VideoTestimonial::factory()->create();

    Livewire::test(ListVideoTestimonials::class)
        ->assertCanSeeTableRecords([$testimonial]);
});

it('stores an uploaded video on the public disk and pins it to the chosen pages', function () {
    Livewire::test(CreateVideoTestimonial::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::Upload->value,
            'video_path' => UploadedFile::fake()->create('story.mp4', 4000, 'video/mp4'),
            'customer_name' => 'Asha Verma',
            'placements' => [VideoTestimonials::EVERY_PAGE, FaqPlacement::Contact->value],
            'show_as_floating' => true,
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $testimonial = VideoTestimonial::query()->sole();

    expect($testimonial->video_path)->toStartWith('video-testimonials/');
    Storage::disk('public')->assertExists($testimonial->video_path);
    expect($testimonial->placements)->toBe([VideoTestimonials::EVERY_PAGE, FaqPlacement::Contact->value]);
    expect($testimonial->show_as_floating)->toBeTrue();
});

it('stores the customer photo in its own directory on the public disk', function () {
    Livewire::test(CreateVideoTestimonial::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::YouTube->value,
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'customer_name' => 'Asha Verma',
            'customer_photo_path' => UploadedFile::fake()->image('asha.jpg', 400, 400),
            'placements' => [VideoTestimonials::EVERY_PAGE],
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $testimonial = VideoTestimonial::query()->sole();

    expect($testimonial->customer_photo_path)->toStartWith('video-testimonial-photos/');
    Storage::disk('public')->assertExists($testimonial->customer_photo_path);
});

it('rejects an upload that is not an MP4 or WebM video', function () {
    Livewire::test(CreateVideoTestimonial::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::Upload->value,
            'video_path' => UploadedFile::fake()->createWithContent('story.html', '<script>alert(1)</script>'),
            'customer_name' => 'Asha Verma',
            'placements' => [VideoTestimonials::EVERY_PAGE],
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['video_path']);

    expect(VideoTestimonial::query()->exists())->toBeFalse();
});

it('requires a video file when the source is an upload', function () {
    Livewire::test(CreateVideoTestimonial::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::Upload->value,
            'customer_name' => 'Asha Verma',
            'placements' => [VideoTestimonials::EVERY_PAGE],
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['video_path' => 'required']);
});

it('saves a YouTube testimonial without a video file', function () {
    Livewire::test(CreateVideoTestimonial::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::YouTube->value,
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'customer_name' => 'Rahul Das',
            'placements' => [VideoTestimonials::EVERY_PAGE],
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $testimonial = VideoTestimonial::query()->sole();

    expect($testimonial->video_source)->toBe(VideoTestimonialSource::YouTube);
    expect($testimonial->youtube_url)->toBe('https://youtu.be/dQw4w9WgXcQ');
    expect($testimonial->video_path)->toBeNull();
});

it('rejects a YouTube link that is not a YouTube video', function () {
    Livewire::test(CreateVideoTestimonial::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::YouTube->value,
            'youtube_url' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
            'customer_name' => 'Rahul Das',
            'placements' => [VideoTestimonials::EVERY_PAGE],
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['youtube_url']);
});

it('requires at least one page to show the video on', function () {
    Livewire::test(CreateVideoTestimonial::class)
        ->fillForm([
            'video_source' => VideoTestimonialSource::YouTube->value,
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'customer_name' => 'Rahul Das',
            'placements' => [],
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['placements' => 'required']);
});
