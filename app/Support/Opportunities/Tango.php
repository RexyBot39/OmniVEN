<?php

namespace App\Support\Opportunities;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OmniVEN's link to Tango (tango.makegov.com), the same REST API the tango-go SDK wraps: SAM.gov opportunities,
 * state and local bids, and DLA DIBBS. The API key goes in the X-API-KEY header from the server only.
 */
class Tango
{
    public static function configured(): bool
    {
        return filled(config('tango.api_key'));
    }

    /** Every result of a list endpoint, following "next" for up to max_pages pages. */
    public static function list(string $path, array $query): array
    {
        $rows = [];
        $url = config('tango.base').$path;
        for ($page = 0; $url && $page < (int) config('tango.max_pages'); $page++) {
            $body = self::get($url, $page === 0 ? $query : []);
            foreach ($body['results'] ?? [] as $r) {
                if (is_array($r)) {
                    $rows[] = $r;
                }
            }
            $next = $body['next'] ?? null;
            // Only follow pages on Tango itself.
            $url = is_string($next) && str_starts_with($next, config('tango.base').'/') ? $next : null;
        }

        return $rows;
    }

    public static function get(string $url, array $query = []): array
    {
        if (! self::configured()) {
            throw new RuntimeException('Tango isn’t set up: add TANGO_API_KEY.');
        }
        try {
            $res = Http::withHeaders(['X-API-KEY' => (string) config('tango.api_key')])->acceptJson()
                ->timeout((int) config('tango.timeout'))->retry(2, 500, fn ($e) => $e instanceof ConnectionException || ($e->response?->serverError() ?? false), throw: false)
                ->get($url, $query);
        } catch (ConnectionException) {
            throw new RuntimeException('Couldn’t reach Tango. Try again later.');
        }
        $body = $res->json();
        $detail = is_array($body) ? (string) ($body['detail'] ?? $body['message'] ?? '') : '';

        return match (true) {
            $res->status() === 401 => throw new RuntimeException('Tango didn’t accept the API key. Check TANGO_API_KEY.'),
            $res->status() === 403 => throw new RuntimeException('The Tango plan doesn’t include this source'.($detail !== '' ? ' ('.mb_substr($detail, 0, 160).')' : '').'. Upgrade the plan at tango.makegov.com, or turn the source off.'),
            $res->status() === 429 => throw new RuntimeException('Tango’s rate limit was reached. The next daily search will pick up where this one stopped.'),
            ! $res->successful() => throw new RuntimeException('Tango answered HTTP '.$res->status().($detail !== '' ? ': '.mb_substr($detail, 0, 160) : '').'.'),
            ! is_array($body) => throw new RuntimeException('Tango sent back something unreadable.'),
            default => $body,
        };
    }
}
