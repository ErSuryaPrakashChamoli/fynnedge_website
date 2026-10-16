<?php

namespace App\Modules\CreditBureau\DataTransferObjects;

use Illuminate\Http\Client\Response;

/**
 * What happened on the wire for one Surepass request: either an HTTP answer
 * (any status) or a transport failure category. Interpreting the answer is
 * left to the bureau's own service.
 */
final readonly class SurepassCall
{
    /** Token or base URL missing — nothing was sent. */
    public const NOT_CONFIGURED = 'not_configured';

    /** DNS, refused, TLS or connect-phase timeout — nothing was sent. */
    public const CONNECTION_FAILED = 'connection_failed';

    /** Read timeout — Surepass may already have done (and billed) the work. */
    public const TIMEOUT = 'timeout';

    /** Dropped after sending, or anything else — outcome unknown. */
    public const UNEXPECTED = 'unexpected';

    private function __construct(
        public ?Response $response,
        public ?string $failure,
    ) {}

    public static function answered(Response $response): self
    {
        return new self($response, null);
    }

    public static function failed(string $failure): self
    {
        return new self(null, $failure);
    }

    public function status(): ?int
    {
        return $this->response?->status();
    }

    /**
     * @return array<mixed>|null The decoded JSON body, or null when it is not JSON.
     */
    public function json(): ?array
    {
        $body = $this->response?->json();

        return is_array($body) ? $body : null;
    }

    public function messageCode(): ?string
    {
        $code = $this->json()['message_code'] ?? null;

        return is_string($code) ? substr($code, 0, 100) : null;
    }

    /** Surepass's human message — for classification only, never stored or shown. */
    public function message(): string
    {
        $message = $this->json()['message'] ?? null;

        return is_string($message) ? $message : '';
    }
}
