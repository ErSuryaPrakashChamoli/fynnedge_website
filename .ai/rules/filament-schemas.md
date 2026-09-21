---
paths:
  - app/Filament/Schemas/HtmlBodyEditor.php
---

# Filament Schemas

## HtmlBodyEditor pins editor image uploads to the public disk — keep it that way
The rich editor's toolbar "attach files" button uploads via Filament's fileAttachments* settings, whose default disk is config('filament.default_filesystem_disk') = FILESYSTEM_DISK = 'local' (private) here. Filament writes each image's URL into the saved HTML at save time, so a private-disk upload produces an <img src> that 404s on the public site with no error in admin. HtmlBodyEditor::make() therefore sets fileAttachmentsDisk('public'), fileAttachmentsVisibility('public') and a per-caller fileAttachmentsDirectory (default 'rich-content'; ArticleForm passes 'articles/inline'). Never add a bare RichEditor with attachFiles enabled elsewhere without the same three calls. Pinned by tests/Feature/Filament/ArticleImageUploadTest.php.
