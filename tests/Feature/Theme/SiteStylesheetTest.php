<?php

/**
 * The --color-* tokens declared in the first block of app.css that opens with
 * $opening ('@theme {' for light mode, the data-theme selector for dark).
 *
 * @return array<string, string>
 */
function themeColors(string $opening): array
{
    $css = file_get_contents(resource_path('css/app.css'));
    $block = substr($css, strpos($css, $opening));
    $tokens = substr($block, 0, min(array_filter([strpos($block, '}'), strpos($block, '@keyframes')])));

    preg_match_all('/--color-([a-z0-9-]+):\s*(#[0-9a-f]{6});/i', $tokens, $matches);

    return array_combine($matches[1], $matches[2]);
}

function contrastRatio(string $foreground, string $background): float
{
    $luminance = function (string $hex): float {
        $channels = array_map(function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };

    [$lighter, $darker] = [max($luminance($foreground), $luminance($background)), min($luminance($foreground), $luminance($background))];

    return ($lighter + 0.05) / ($darker + 0.05);
}

/*
 * text-ink-faint carries helper text, eyebrows and the hero stat labels.
 * Lighthouse failed it at 2.9–3.3:1 (2026-10-31); WCAG AA needs 4.5:1 for
 * text this small.
 */
it('keeps faint text at AA contrast on every surface it sits on', function (string $theme) {
    $colors = themeColors($theme);

    foreach (['bg', 'surface', 'surface-2', 'accent-soft'] as $surface) {
        expect(contrastRatio($colors['ink-faint'], $colors[$surface]))->toBeGreaterThanOrEqual(4.5);
    }
})->with([
    'light' => ['@theme {'],
    'dark' => [":root[data-theme='dark'] {"],
]);

it('keeps faint text lighter than muted text, so the two still read as different tiers', function () {
    $colors = themeColors('@theme {');

    expect(contrastRatio($colors['ink-faint'], $colors['surface']))->toBeLessThan(contrastRatio($colors['ink-muted'], $colors['surface']));
});

/*
 * Scanning storage/framework/views added the classes of whatever views were
 * compiled when the build ran (the admin, error pages): 116 KB or 131 KB of
 * CSS from the same commit.
 */
it('builds the public stylesheet from the templates, not from whichever views happen to be compiled', function () {
    expect(file_get_contents(resource_path('css/app.css')))->not->toMatch('#@source\s+[\'"][^\'"]*storage/framework/views#');
});

/*
 * Without a reserved gutter, a classic scrollbar appearing as a long page
 * streams in moved every centred row 7.5px: a 0.09 layout shift on desktop.
 */
it('reserves the scrollbar gutter from the first paint', function () {
    expect(file_get_contents(resource_path('css/app.css')))->toMatch('/html \{\s*scrollbar-gutter: stable;\s*\}/');
});
