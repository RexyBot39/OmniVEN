<?php

namespace App\Support\Revenue;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A minimal, read-only Google Analytics Data API client. It signs in as a service account (a JWT
 * signed with the account's private key, swapped for an access token) and runs reports.
 * The key stays on the server; error messages never include it or the response body.
 */
class Ga4
{
    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const TOKEN_CACHE = 'revenue:ga4:token';

    public static function configured(): bool
    {
        return self::credentials() !== null && config('revenue.ga4.properties') !== [];
    }

    /** The service account key, accepted as raw JSON or base64 of it. */
    public static function credentials(): ?array
    {
        $raw = trim((string) config('revenue.ga4.credentials'));
        if ($raw === '') {
            return null;
        }
        if (! str_starts_with($raw, '{')) {
            $raw = (string) base64_decode($raw, true);
        }
        $key = json_decode($raw, true);

        return is_array($key) && ! empty($key['client_email']) && ! empty($key['private_key']) ? $key : null;
    }

    /**
     * Rows of [dimension values..., metric values...] for one property and date range, all pages.
     *
     * @return list<array{d: list<string>, m: list<string>}>
     */
    public static function report(string $propertyId, string $from, string $to, array $dimensions, array $metrics): array
    {
        $rows = [];
        $offset = 0;
        $limit = 50000;
        do {
            $res = Http::withToken(self::token())->acceptJson()->timeout(config('revenue.ga4.timeout'))
                ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", [
                    'dateRanges' => [['startDate' => $from, 'endDate' => $to]],
                    'dimensions' => array_map(fn ($d) => ['name' => $d], $dimensions),
                    'metrics' => array_map(fn ($m) => ['name' => $m], $metrics),
                    'limit' => $limit,
                    'offset' => $offset,
                    'keepEmptyRows' => false,
                ]);
            if ($res->status() === 401) {
                Cache::forget(self::TOKEN_CACHE);
            }
            if (! $res->successful()) {
                throw new RuntimeException(self::explain($res->status(), $propertyId, (string) ($res->json('error.status') ?? '')));
            }
            foreach ($res->json('rows') ?? [] as $r) {
                $rows[] = [
                    'd' => array_map(fn ($v) => (string) ($v['value'] ?? ''), $r['dimensionValues'] ?? []),
                    'm' => array_map(fn ($v) => (string) ($v['value'] ?? '0'), $r['metricValues'] ?? []),
                ];
            }
            $offset += $limit;
        } while ($offset < (int) ($res->json('rowCount') ?? 0));

        return $rows;
    }

    private static function token(): string
    {
        return self::accessToken(self::SCOPE);
    }

    /** An access token for the service account with one read-only scope (GA4, Search Console…), cached. */
    public static function accessToken(string $scope): string
    {
        $cache = $scope === self::SCOPE ? self::TOKEN_CACHE : 'google:token:'.md5($scope);

        return Cache::get($cache) ?? self::freshToken($scope, $cache);
    }

    private static function freshToken(string $scope, string $cache): string
    {
        $key = self::credentials() ?? throw new RuntimeException('GA4_CREDENTIALS is missing or isn’t a service account JSON key.');
        $now = time();
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
            'iss' => $key['client_email'], 'scope' => $scope, 'aud' => self::TOKEN_URL, 'iat' => $now, 'exp' => $now + 3600,
        ]));
        $pkey = openssl_pkey_get_private($key['private_key']);
        if ($pkey === false || ! openssl_sign($unsigned, $sig, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The private key in GA4_CREDENTIALS can’t be read.');
        }

        $res = Http::asForm()->acceptJson()->timeout(10)->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned.'.'.$b64($sig),
        ]);
        $token = $res->json('access_token');
        if (! $res->successful() || ! is_string($token)) {
            throw new RuntimeException('Google refused the service account sign-in (HTTP '.$res->status().'). Check the key in GA4_CREDENTIALS is current.');
        }
        Cache::put($cache, $token, max(60, (int) ($res->json('expires_in') ?? 3600) - 300));

        return $token;
    }

    private static function explain(int $status, string $propertyId, string $reason): string
    {
        return match (true) {
            $status === 403 => "GA4 property {$propertyId} refused the service account (HTTP 403). In GA4 → Admin → Property access management, add the service account's email as a Viewer.",
            $status === 404, $reason === 'NOT_FOUND' => "GA4 property {$propertyId} wasn’t found (HTTP 404). Check the property ID in GA4_PROPERTIES.",
            $status === 400 => "GA4 rejected the report for property {$propertyId} (HTTP 400".($reason !== '' ? ', '.$reason : '').').',
            $status === 429 => 'GA4 quota reached for now (HTTP 429). It will try again later.',
            default => "GA4 answered HTTP {$status} for property {$propertyId}.",
        };
    }
}
