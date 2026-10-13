<?php

namespace App\Modules\MobileOtp\Providers;

use App\Modules\MobileOtp\Contracts\SmsProvider;
use App\Modules\MobileOtp\DataTransferObjects\SmsSendResult;
use App\Modules\MobileOtp\Enums\SmsFailureReason;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Areach SMS: POST {base_url}/sms with an `apikey` header and a JSON body of
 * sender / to / text / type.
 *
 * Areach publishes no response schema we could verify, so the response is read
 * defensively: a send counts as Accepted only when the body carries an explicit
 * success marker, as Failed on an explicit error marker or a non-2xx status,
 * and as Unconfirmed otherwise. An HTTP 200 on its own is never taken to mean
 * the text was sent, let alone delivered. Tighten acceptsBody()/rejectsBody()
 * once the live smoke test (php artisan mobile-otp:smoke-test) shows the real shape.
 */
class AreachSmsProvider implements SmsProvider
{
    private const SUCCESS_WORDS = ['success', 'successful', 'ok', 'accepted', 'submitted', 'queued', 'sent'];

    private const FAILURE_WORDS = ['error', 'failed', 'failure', 'rejected', 'invalid', 'unauthorized', 'unauthorised'];

    private const REFERENCE_KEYS = ['message_id', 'messageId', 'msg_id', 'msgid', 'request_id', 'requestId', 'reference', 'ref_id', 'id', 'uuid'];

    /**
     * Top-level keys of the last response mapped to their value TYPES only —
     * never values, which may echo the message (and so the code) back. Read by
     * the live smoke test so the parser can be tightened to Areach's real shape.
     *
     * @var array<string, string>
     */
    private array $lastResponseShape = [];

    /**
     * @return array<string, string>
     */
    public function lastResponseShape(): array
    {
        return $this->lastResponseShape;
    }

    public function name(): string
    {
        return 'areach';
    }

    public function sendOtp(string $recipient, string $message): SmsSendResult
    {
        $config = config('services.areach');

        if (blank($config['key'] ?? null) || blank($config['sender'] ?? null) || blank($config['base_url'] ?? null)) {
            return SmsSendResult::failed(SmsFailureReason::NotConfigured);
        }

        $timeout = max(1, (int) ($config['timeout'] ?? 10));

        try {
            // No ->retry(): after a read timeout the text may already be on its way,
            // and a second attempt would send the visitor a second, different code.
            $response = Http::baseUrl($config['base_url'])
                ->withHeaders(['apikey' => $config['key']])
                ->acceptJson()
                ->asJson()
                ->connectTimeout(min($timeout, 5))
                ->timeout($timeout)
                ->post('sms', [
                    'sender' => $config['sender'],
                    'to' => $recipient,
                    'text' => $message,
                    'type' => 'OTP',
                ]);
        } catch (ConnectionException $exception) {
            return $this->isTimeout($exception)
                ? SmsSendResult::unconfirmed(SmsFailureReason::Timeout)
                : SmsSendResult::failed(SmsFailureReason::ConnectionFailed);
        }

        return $this->interpret($response);
    }

    private function interpret(Response $response): SmsSendResult
    {
        $status = $response->status();
        $body = $response->json();

        $this->lastResponseShape = is_array($body)
            ? array_map(get_debug_type(...), array_filter($body, is_string(...), ARRAY_FILTER_USE_KEY))
            : [];

        if (! $response->successful()) {
            return SmsSendResult::failed(SmsFailureReason::HttpError, $status);
        }

        if (! is_array($body)) {
            return SmsSendResult::unconfirmed(SmsFailureReason::InvalidResponse, null, $status);
        }

        if ($this->rejectsBody($body)) {
            return SmsSendResult::failed(SmsFailureReason::ProviderRejected, $status);
        }

        $reference = $this->referenceFrom($body);

        return $this->acceptsBody($body)
            ? SmsSendResult::accepted($reference, $status)
            : SmsSendResult::unconfirmed(SmsFailureReason::UnrecognisedResponse, $reference, $status);
    }

    /**
     * @param  array<mixed>  $body
     */
    private function acceptsBody(array $body): bool
    {
        return ($body['success'] ?? null) === true
            || ($body['status'] ?? null) === true
            || $this->isWordIn($body['status'] ?? null, self::SUCCESS_WORDS);
    }

    /**
     * @param  array<mixed>  $body
     */
    private function rejectsBody(array $body): bool
    {
        return ($body['success'] ?? null) === false
            || ($body['status'] ?? null) === false
            || $this->isWordIn($body['status'] ?? null, self::FAILURE_WORDS)
            || filled($body['error'] ?? null)
            || filled($body['errors'] ?? null);
    }

    /**
     * The first scalar id-like field, at the top level or under `data`
     * (object or first list item). Only ever an identifier — never echoed text.
     *
     * @param  array<mixed>  $body
     */
    private function referenceFrom(array $body): ?string
    {
        $data = $body['data'] ?? null;

        foreach ([$body, is_array($data) ? $data : [], is_array($data) && array_is_list($data) ? ($data[0] ?? []) : []] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            foreach (self::REFERENCE_KEYS as $key) {
                $value = Arr::get($candidate, $key);

                if (is_string($value) || is_int($value)) {
                    return substr((string) $value, 0, 191);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $words
     */
    private function isWordIn(mixed $value, array $words): bool
    {
        return is_string($value) && in_array(strtolower(trim($value)), $words, true);
    }

    /**
     * cURL error 28 is a timeout. A connect timeout means nothing was sent, but a
     * read timeout can mean the provider already accepted it — the two are not
     * reliably distinguishable here, so both are reported as Unconfirmed.
     */
    private function isTimeout(ConnectionException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'cURL error 28') || stripos($message, 'timed out') !== false;
    }
}
