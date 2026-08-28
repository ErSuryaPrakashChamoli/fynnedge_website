<?php

namespace App\Modules\Journey\Enums;

use Filament\Support\Contracts\HasLabel;

enum FieldType: string implements HasLabel
{
    case Text = 'text';
    case Number = 'number';
    case Email = 'email';
    case Tel = 'tel';
    case Date = 'date';
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Textarea = 'textarea';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Email => 'Email',
            self::Tel => 'Phone',
            self::Date => 'Date',
            self::Select => 'Select (dropdown)',
            self::Radio => 'Radio buttons',
            self::Checkbox => 'Checkbox',
            self::Textarea => 'Long text',
        };
    }

    public function usesOptions(): bool
    {
        return in_array($this, [self::Select, self::Radio]);
    }
}
