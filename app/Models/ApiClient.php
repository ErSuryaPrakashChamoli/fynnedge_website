<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use Database\Factories\ApiClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Another system (e.g. the LMS) allowed to call the FynnEdge API with its own
 * key, sent as `Authorization: Bearer fyn_…` (App\Support\ApiClients\
 * AuthenticateApiClient). The key signs in as the client's service account
 * (`user`), so what it may do is that account's role — the same permissions
 * and policies as staff.
 *
 * The key itself is never stored: only its SHA-256 hash, plus the first few
 * characters to tell keys apart. It is shown once, when issued.
 */
#[Fillable(['name', 'allowed_ips', 'notes'])]
#[Hidden(['key_hash'])]
class ApiClient extends Model
{
    /** @use HasFactory<ApiClientFactory> */
    use Auditable, HasFactory, HasPublicId;

    public const KEY_PREFIX = 'fyn_';

    /** Service accounts get an address on a reserved domain that can never receive mail. */
    public const SERVICE_ACCOUNT_DOMAIN = 'api-clients.invalid';

    protected function casts(): array
    {
        return [
            'allowed_ips' => 'array',
            'key_generated_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Replaces the client's key (the old one stops working at once) and
     * re-activates a revoked client. Returns the new key — the only time it
     * exists in plain text.
     */
    public function issueKey(): string
    {
        $key = self::KEY_PREFIX.Str::random(48);

        $this->forceFill([
            'key_prefix' => substr($key, 0, 12),
            'key_hash' => self::hashKey($key),
            'key_generated_at' => now(),
            'revoked_at' => null,
        ])->save();

        return $key;
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function maskedKey(): string
    {
        return $this->key_prefix.'…';
    }

    /**
     * No list means any address; otherwise the caller's IP must be one of the
     * listed addresses or ranges (CIDR).
     */
    public function allowsIp(?string $ip): bool
    {
        $allowed = array_values(array_filter($this->allowed_ips ?? []));

        return $allowed === [] || ($ip !== null && IpUtils::checkIp($ip, $allowed));
    }

    public static function isValidIpOrRange(string $value): bool
    {
        [$address, $mask] = array_pad(explode('/', trim($value), 2), 2, null);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return $mask === null || (ctype_digit($mask) && (int) $mask <= (str_contains($address, ':') ? 128 : 32));
    }

    /**
     * Notes when and from where the key was last used — at most once a minute
     * per address, and without model events, so neither busy traffic nor the
     * audit trail turns into a write per request.
     */
    public function recordUse(?string $ip): void
    {
        if ($this->last_used_ip === $ip && $this->last_used_at?->gt(now()->subMinute())) {
            return;
        }

        $this->newQuery()->whereKey($this->getKey())->toBase()->update(['last_used_at' => now(), 'last_used_ip' => $ip]);
    }

    /**
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return ['key_hash', 'last_used_at', 'last_used_ip'];
    }
}
