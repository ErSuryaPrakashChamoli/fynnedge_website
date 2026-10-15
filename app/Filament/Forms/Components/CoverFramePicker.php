<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Lets an admin pick the frame of an uploaded video that a card shows as its
 * cover: they scrub a preview of the video and press "Use this frame", which
 * stores the moment in seconds. The preview is cropped like the public card.
 *
 * The video is read from a sibling FileUpload (`video_path` by default),
 * which must be live() so the preview appears as soon as an upload finishes.
 */
class CoverFramePicker extends Field
{
    protected string $view = 'filament.forms.components.cover-frame-picker';

    protected string $videoField = 'video_path';

    protected string $videoDisk = 'public';

    private const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'mov', 'webm'];

    public function video(string $field, string $disk = 'public'): static
    {
        $this->videoField = $field;
        $this->videoDisk = $disk;

        return $this;
    }

    /**
     * A URL the admin's browser can play: the stored file, or Livewire's
     * signed preview of a file uploaded but not yet saved. Null when there is
     * no video yet, or when a fresh upload is not a video Livewire can preview
     * (WebM is not in livewire.temporary_file_upload.preview_mimes), in which
     * case the view asks the admin to save first.
     */
    public function getVideoPreviewUrl(): ?string
    {
        $file = Arr::first(Arr::wrap($this->evaluate(fn (Get $get): mixed => $get($this->videoField))));

        if ($file instanceof TemporaryUploadedFile) {
            // By detected type, not the "video/" mime prefix: MP4s are often detected as application/mp4.
            return $file->isPreviewable() && in_array($file->guessExtension(), self::VIDEO_EXTENSIONS, true) ? $file->temporaryUrl() : null;
        }

        return is_string($file) && filled($file) ? Storage::disk($this->videoDisk)->url($file) : null;
    }
}
