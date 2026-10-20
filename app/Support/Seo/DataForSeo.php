<?php

namespace App\Support\Seo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The one way OmniVEN talks to DataForSEO. Signs every call with the API login (never shown or logged), turns
 * failures into plain-English messages, records what each call cost and stops billed calls once the month's
 * budget is spent.
 */
class DataForSeo
{
    public static function configured(): bool
    {
        return filled(config('dataforseo.login')) && filled(config('dataforseo.password'));
    }

    /** Backlinks is its own DataForSEO subscription, so it has its own switch. */
    public static function backlinks(): bool
    {
        return self::configured() && (bool) config('dataforseo.backlinks');
    }

    /** POST one task to a live endpoint and return its first result (empty when Google had no results). */
    public static function post(string $path, array $task, ?int $userId = null): array
    {
        return self::task(self::send('post', $path, [$task], $userId))['result'][0] ?? [];
    }

    /** POST several tasks at once (the queue endpoints take up to 100) and return each task. */
    public static function postTasks(string $path, array $tasks, ?int $userId = null): array
    {
        return self::send('post', $path, array_values($tasks), $userId)['tasks'] ?? [];
    }

    /** GET a queued task or a crawl's results (free). Returns the task, whatever its status, or null. */
    public static function get(string $path): ?array
    {
        return self::send('get', $path, null, null)['tasks'][0] ?? null;
    }

    public static function spentThisMonth(): float
    {
        return (float) DB::table('dataforseo_calls')->where('created_at', '>=', now()->startOfMonth())->sum('cost');
    }

    public static function budget(): float
    {
        return (float) config('dataforseo.monthly_budget');
    }

    public static function overBudget(): bool
    {
        return self::spentThisMonth() >= self::budget();
    }

    /** A finished task's status: OK, "no results" (empty but fine), or a failure worth a plain message. */
    public static function task(array $body): array
    {
        $task = $body['tasks'][0] ?? null;
        if (! is_array($task)) {
            throw new RuntimeException('DataForSEO sent back no result.');
        }
        $code = (int) ($task['status_code'] ?? 0);
        if ($code === 20000 || $code === 40501) {
            return $code === 40501 ? ['result' => []] + $task : $task;
        }
        throw new RuntimeException(self::explain($code, (string) ($task['status_message'] ?? '')));
    }

    private static function send(string $method, string $path, ?array $tasks, ?int $userId): array
    {
        if (! self::configured()) {
            throw new RuntimeException('DataForSEO isn’t set up: add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD.');
        }
        if ($method === 'post' && self::overBudget()) {
            throw new RuntimeException('This month’s DataForSEO budget ($'.number_format(self::budget(), 2).') is used up. It resets on the 1st, or an admin can raise DATAFORSEO_MONTHLY_BUDGET.');
        }
        $http = Http::withBasicAuth((string) config('dataforseo.login'), (string) config('dataforseo.password'))
            ->acceptJson()->timeout((int) config('dataforseo.timeout'));
        try {
            $res = $method === 'post' ? $http->post(config('dataforseo.base').$path, $tasks) : $http->get(config('dataforseo.base').$path);
        } catch (ConnectionException) {
            throw new RuntimeException('Couldn’t reach DataForSEO. It may be busy; try again in a few minutes.');
        }
        $body = self::check($res);
        $cost = (float) ($body['cost'] ?? 0);
        if ($cost > 0 || $method === 'post') {
            DB::table('dataforseo_calls')->insert(['path' => mb_substr(preg_replace('#/[0-9a-f-]{20,}$#', '/…', $path), 0, 160), 'cost' => $cost, 'user_id' => $userId, 'created_at' => now()]);
        }

        return $body;
    }

    private static function check(Response $res): array
    {
        if ($res->status() === 401) {
            throw new RuntimeException('DataForSEO didn’t accept the login. Check DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD (the API password from the dashboard, not the account password).');
        }
        if ($res->status() === 402) {
            throw new RuntimeException('The DataForSEO account is out of credit. Add funds in the DataForSEO dashboard.');
        }
        if (! $res->successful()) {
            throw new RuntimeException('DataForSEO answered HTTP '.$res->status().'. Try again later.');
        }
        $body = $res->json();
        if (! is_array($body)) {
            throw new RuntimeException('DataForSEO sent back something unreadable.');
        }
        $code = (int) ($body['status_code'] ?? 0);
        if ($code !== 20000) {
            throw new RuntimeException(self::explain($code, (string) ($body['status_message'] ?? '')));
        }

        return $body;
    }

    private static function explain(int $code, string $message): string
    {
        $message = mb_substr(trim($message), 0, 200);

        return match (true) {
            $code === 40100 =>'DataForSEO didn’t accept the login. Check DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD.',
            $code === 40200 || $code === 40210 => 'The DataForSEO account is out of credit. Add funds in the DataForSEO dashboard.',
            $code === 40204 => 'This DataForSEO account doesn’t include that API yet (Backlinks needs its own subscription). Turn it on under Plans and Subscriptions.',
            $code === 40202 || $code === 40209 => 'DataForSEO is limiting how fast we can ask. Try again in a minute.',
            default => 'DataForSEO couldn’t do that'.($message !== '' ? ': '.$message : '').($code ? " ($code)" : '').'.',
        };
    }
}
