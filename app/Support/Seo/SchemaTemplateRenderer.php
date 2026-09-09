<?php

namespace App\Support\Seo;

use App\Models\SchemaTemplate;

/**
 * Turns an admin-authored SchemaTemplate into a concrete JSON-LD node by
 * substituting `{{ token }}` placeholders with the page's real values.
 *
 * The substitution rule is deliberately subtractive: a placeholder whose token
 * resolves to nothing removes the key that held it, rather than leaving the
 * literal `{{ description }}` in the output or emitting an empty string. That
 * is the same "never fabricate, never ship a blank" contract OrganizationSchema
 * follows with its `filled()` filter — an admin who writes a template against
 * fields a given page doesn't have gets a smaller node, not a broken one.
 */
class SchemaTemplateRenderer
{
    /**
     * Tokens every template can reference, resolved per page.
     *
     * @return array<int, string>
     */
    public static function availableTokens(): array
    {
        return ['title', 'description', 'url', 'image', 'site_name', 'organization_id', 'website_id'];
    }

    /**
     * @param  array<string, string|null>  $context
     * @return array<string, mixed>|null
     */
    public static function render(SchemaTemplate $template, array $context): ?array
    {
        $node = self::substitute($template->body, $context);

        if (! is_array($node) || $node === []) {
            return null;
        }

        $node['@type'] ??= $template->schema_type;

        /**
         * Every node in the graph is addressable by @id (see SchemaGraph), so a
         * template that omits one gets a stable id derived from the page URL
         * and the template's own key rather than joining the graph anonymously.
         */
        if (! isset($node['@id']) && filled($context['url'] ?? null)) {
            $node['@id'] = $context['url'].'#template-'.$template->getKey();
        }

        return $node;
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private static function substitute(mixed $value, array $context): mixed
    {
        if (is_array($value)) {
            $resolved = [];

            foreach ($value as $key => $item) {
                $item = self::substitute($item, $context);

                if ($item === null) {
                    continue;
                }

                $resolved[$key] = $item;
            }

            return $resolved === [] ? null : $resolved;
        }

        if (! is_string($value)) {
            return $value;
        }

        return self::substituteString($value, $context);
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private static function substituteString(string $value, array $context): ?string
    {
        $substituted = preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            fn (array $matches): string => (string) ($context[strtolower($matches[1])] ?? ''),
            $value,
        );

        return filled(trim((string) $substituted)) ? trim((string) $substituted) : null;
    }

    /**
     * The token values for one rendered page.
     *
     * @return array<string, string|null>
     */
    public static function context(
        string $title,
        ?string $description,
        string $url,
        ?string $imageUrl,
        string $siteName,
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'image' => $imageUrl,
            'site_name' => $siteName,
            'organization_id' => SchemaGraph::organizationId(),
            'website_id' => SchemaGraph::websiteId(),
        ];
    }
}
