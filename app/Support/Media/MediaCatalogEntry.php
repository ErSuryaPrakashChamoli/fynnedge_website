<?php

namespace App\Support\Media;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class MediaCatalogEntry
{
    /**
     * @param  Collection<int, MediaReference>  $references
     */
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly bool $existsOnDisk,
        public readonly Collection $references,
        public readonly ?int $sizeBytes,
        public readonly ?Carbon $lastModifiedAt,
    ) {}

    public function filename(): string
    {
        return basename($this->path);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    public function usageCount(): int
    {
        return $this->references->count();
    }

    public function status(): string
    {
        return match (true) {
            ! $this->existsOnDisk => 'missing',
            $this->usageCount() > 0 => 'used',
            default => 'unused',
        };
    }

    public function humanSize(): ?string
    {
        if ($this->sizeBytes === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->sizeBytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 1).' '.$units[$unitIndex];
    }

    public function isImage(): bool
    {
        return in_array($this->extension(), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'], true);
    }
}
