<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies authors from the interim settings storage into the `authors` table:
 *
 *  - `blog_authors` (a JSON list of {id, name, slug, designation, bio,
 *    photo_path, profile_url, is_active}) → one `authors` row each. The legacy
 *    `id` (a UUID) becomes `public_id` and the slug is kept, so every
 *    /resources/authors/{slug} URL and every photo file stays as it was.
 *  - `blog_article_authors` ({article public_id: author id}) → articles.author_id.
 *
 * Additive and repeatable: an author already present (same public_id) is not
 * inserted twice, and an article that already has an author is not changed.
 * The two settings are deliberately NOT deleted — they stay as the source of
 * truth for rollback until the move has been verified in production.
 *
 * Self-contained (query builder only, no app classes), so later changes to
 * the models can never change what this migration does.
 */
return new class extends Migration
{
    public function up(): void
    {
        $authors = $this->setting('blog_authors');
        $assignments = $this->setting('blog_article_authors');
        $idMap = [];
        $order = 0;

        foreach ($authors as $author) {
            if (! is_array($author) || blank($author['name'] ?? null) || blank($author['id'] ?? null)) {
                continue;
            }

            $publicId = Str::isUuid((string) $author['id']) ? (string) $author['id'] : (string) Str::uuid();
            $existing = DB::table('authors')->where('public_id', $publicId)->value('id');

            if ($existing !== null) {
                $idMap[(string) $author['id']] = $existing;

                continue;
            }

            $name = Str::limit(trim(strip_tags((string) $author['name'])), 255, '');
            $profileUrl = trim((string) ($author['profile_url'] ?? ''));

            $idMap[(string) $author['id']] = DB::table('authors')->insertGetId([
                'public_id' => $publicId,
                'name' => $name,
                'slug' => $this->uniqueSlug(filled($author['slug'] ?? null) ? (string) $author['slug'] : $name),
                'designation' => filled($author['designation'] ?? null) ? Str::limit(strip_tags((string) $author['designation']), 255, '') : null,
                'bio' => filled($author['bio'] ?? null) ? strip_tags((string) $author['bio']) : null,
                'photo_path' => is_string($author['photo_path'] ?? null) && $author['photo_path'] !== '' ? $author['photo_path'] : null,
                'profile_url' => preg_match('#^https://#i', $profileUrl) === 1 ? Str::limit($profileUrl, 255, '') : null,
                'is_active' => (bool) ($author['is_active'] ?? true),
                'sort_order' => $order++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($assignments as $articlePublicId => $legacyAuthorId) {
            $authorId = $idMap[(string) $legacyAuthorId] ?? null;

            if ($authorId === null || ! is_string($articlePublicId)) {
                continue;
            }

            DB::table('articles')
                ->where('public_id', $articlePublicId)
                ->whereNull('author_id')
                ->update(['author_id' => $authorId]);
        }
    }

    /**
     * Undoes only what up() copied: articles lose the author it assigned and
     * the copied author rows are removed. The legacy settings were never
     * touched, so they still hold everything.
     */
    public function down(): void
    {
        $publicIds = collect($this->setting('blog_authors'))
            ->map(fn (mixed $author): ?string => is_array($author) && Str::isUuid((string) ($author['id'] ?? '')) ? (string) $author['id'] : null)
            ->filter()
            ->values()
            ->all();

        if ($publicIds === []) {
            return;
        }

        $authorIds = DB::table('authors')->whereIn('public_id', $publicIds)->pluck('id')->all();

        DB::table('articles')->whereIn('author_id', $authorIds)->update(['author_id' => null]);
        DB::table('authors')->whereIn('id', $authorIds)->delete();
    }

    /**
     * @return array<int|string, mixed>
     */
    private function setting(string $key): array
    {
        $value = DB::table('settings')->where('key', $key)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'author';
        $slug = $base;
        $suffix = 2;

        while (DB::table('authors')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
};
