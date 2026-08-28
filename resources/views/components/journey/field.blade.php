@use('App\Modules\Journey\Enums\FieldType')

@props(['field', 'value' => null])

@php
    $span = $field->type === FieldType::Textarea ? 'sm:col-span-2' : '';
    $errorBag = $errors->has($field->key);
@endphp

<div
    x-show="isVisible('{{ $field->key }}')"
    x-cloak
    class="{{ $span }}"
>
    @switch($field->type)
        @case(FieldType::Select)
            <div class="flex flex-col gap-1.5">
                <label for="{{ $field->key }}" class="text-sm font-medium text-ink">{{ $field->label }}</label>
                <select
                    id="{{ $field->key }}"
                    name="{{ $field->key }}"
                    x-model="values['{{ $field->key }}']"
                    class="rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink transition-colors focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30 {{ $errorBag ? 'border-warn' : 'border-line-strong' }}"
                >
                    <option value="">Select…</option>
                    @foreach ($field->options ?? [] as $option)
                        <option value="{{ $option['value'] }}" @selected(old($field->key, $value) == $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                @if ($errorBag)
                    <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                @elseif ($field->help_text)
                    <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                @endif
            </div>
            @break

        @case(FieldType::Radio)
            <fieldset class="flex flex-col gap-2">
                <legend class="text-sm font-medium text-ink">{{ $field->label }}</legend>
                <div class="flex flex-wrap gap-4">
                    @foreach ($field->options ?? [] as $option)
                        <label class="flex items-center gap-2 text-sm text-ink-muted">
                            <input
                                type="radio"
                                name="{{ $field->key }}"
                                value="{{ $option['value'] }}"
                                x-model="values['{{ $field->key }}']"
                                @checked(old($field->key, $value) == $option['value'])
                                class="border-line-strong text-accent focus:ring-accent/30"
                            >
                            {{ $option['label'] }}
                        </label>
                    @endforeach
                </div>
                @if ($errorBag)
                    <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                @elseif ($field->help_text)
                    <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                @endif
            </fieldset>
            @break

        @case(FieldType::Checkbox)
            <div class="flex flex-col gap-1.5">
                <label class="flex items-start gap-2.5 text-sm text-ink-muted">
                    <input
                        type="checkbox"
                        name="{{ $field->key }}"
                        value="1"
                        x-model="values['{{ $field->key }}']"
                        @checked(old($field->key, $value))
                        class="mt-0.5 rounded border-line-strong text-accent focus:ring-accent/30"
                    >
                    <span>{{ $field->label }}</span>
                </label>
                @if ($errorBag)
                    <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                @elseif ($field->help_text)
                    <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                @endif
            </div>
            @break

        @case(FieldType::Textarea)
            <x-ui.textarea
                :name="$field->key"
                :label="$field->label"
                :hint="$field->help_text"
                :value="$value"
                x-model="values['{{ $field->key }}']"
            />
            @break

        @default
            <x-ui.input
                :name="$field->key"
                :label="$field->label"
                :type="$field->type->value"
                :hint="$field->help_text"
                :value="$value"
                x-model="values['{{ $field->key }}']"
            />
    @endswitch
</div>
