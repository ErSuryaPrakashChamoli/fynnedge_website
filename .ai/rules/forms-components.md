---
paths:
  - 'app/Filament/Forms/Components/CoverFramePicker.php,app/Filament/Resources/SocialVideos/**,resources/views/filament/forms/components/cover-frame-picker.blade.php'
---

# Forms Components

## Social video cover: picked frame (cover_time) or uploaded image
The SocialVideo form's "Cover" section offers CoverFramePicker (stores `cover_time` seconds; the card renders `video_url#t={coverFrameTime()}`, default 0.1) and an optional uploaded image (`poster_path`), which wins when set. The picker previews the saved file, or Livewire's temporaryUrl() of an unsaved upload — so `video_path` must stay ->live(). Detect "is a video" with isPreviewable() + guessExtension(), never a `video/` mime prefix (MP4s are often application/mp4), and never call temporaryUrl() unguarded: it throws FileNotPreviewableException (extends \Exception, not RuntimeException) for non-previewable uploads, which 500s the form. WebM isn't in livewire preview_mimes, so a fresh WebM shows "save first". In tests, a saved video_path must exist on the faked disk or FileUpload empties the state.
