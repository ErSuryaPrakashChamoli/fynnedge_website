<?php

namespace App\Support\Media;

/**
 * One "this record uses this file" fact — e.g. LoanProduct #12 → image_path.
 */
final class MediaReference
{
    public function __construct(
        public readonly string $modelLabel,
        public readonly string $recordLabel,
        public readonly ?string $recordEditUrl,
        public readonly string $fieldLabel,
        public readonly ?string $alt,
    ) {}
}
