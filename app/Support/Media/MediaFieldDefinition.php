<?php

namespace App\Support\Media;

/**
 * One genuine media-path field the Media Governance catalog knows about —
 * deliberately curated by inspecting each model/Setting, never a blind scan
 * of every database column. Two shapes:
 *
 * - `model`: a path column on an Eloquent model (e.g. Banner::image_path).
 * - `setting`: a single site-wide image stored in the Setting key/value
 *   store (e.g. the branding logo) — there is no "record", just one value.
 */
final class MediaFieldDefinition
{
    private function __construct(
        public readonly string $source,
        public readonly string $label,
        public readonly string $pathField,
        public readonly string $directory,
        public readonly string $disk,
        public readonly ?string $modelClass = null,
        public readonly ?string $altField = null,
        public readonly ?string $recordLabelField = null,
        public readonly bool $withTrashed = false,
        public readonly ?string $settingKey = null,
    ) {}

    public static function forModel(
        string $modelClass,
        string $label,
        string $pathField,
        string $directory,
        ?string $altField = null,
        ?string $recordLabelField = null,
        bool $withTrashed = false,
        string $disk = 'public',
    ): self {
        return new self(
            source: 'model',
            label: $label,
            pathField: $pathField,
            directory: $directory,
            disk: $disk,
            modelClass: $modelClass,
            altField: $altField,
            recordLabelField: $recordLabelField,
            withTrashed: $withTrashed,
        );
    }

    public static function forSetting(
        string $settingKey,
        string $label,
        string $directory,
        string $disk = 'public',
    ): self {
        return new self(
            source: 'setting',
            label: $label,
            pathField: $settingKey,
            directory: $directory,
            disk: $disk,
            settingKey: $settingKey,
        );
    }
}
