<?php

namespace App\Support\Metabase;

use App\Models\User;
use App\Support\AccessNotifier;
use App\Support\Activity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Keeps OmniVEN from being used to copy the Metabase database: a daily allowance of query runs and rows per
 * person, personal-detail columns masked or refused, and SQL kept to admins. Hitting a limit is logged and
 * admins are told in Slack (once a day per person). Only counts are stored, never the data.
 */
class MetabaseGuard
{
    /** Refuse the run if this person has used today's allowance; $runs is how many queries it costs. */
    public static function allow(User $user, int $runs = 1): void
    {
        $u = self::usage($user);
        $why = match (true) {
            $u['queries'] + $runs > (int) config('metabase.daily_queries') => 'the daily limit of '.number_format((int) config('metabase.daily_queries')).' Metabase queries',
            $u['rows'] >= (int) config('metabase.daily_rows') => 'the daily limit of '.number_format((int) config('metabase.daily_rows')).' rows from Metabase',
            default => null,
        };
        if ($why === null) {
            return;
        }
        DB::table('metabase_usage')->insertOrIgnore(['user_id' => $user->id, 'day' => today()->toDateString(), 'queries' => 0, 'row_count' => 0, 'blocked' => 0]);
        DB::table('metabase_usage')->where('user_id', $user->id)->where('day', today()->toDateString())->increment('blocked');
        if (Cache::add("mb:alerted:{$user->id}:".today()->toDateString(), 1, now()->endOfDay())) {
            Activity::log('metabase.limit', "Reached $why", null, $user);
            AccessNotifier::postToChannel(':rotating_light: *'.AccessNotifier::clean($user->name).'* reached '.$why.' in OmniVEN today. Further Metabase queries are blocked until tomorrow.');
        }
        throw new LimitReached('You’ve reached '.$why.'. It resets tomorrow; ask an admin if you need more.');
    }

    /** Count a run and the rows it brought back. */
    public static function record(User $user, int $runs, int $rows): void
    {
        $day = today()->toDateString();
        DB::table('metabase_usage')->insertOrIgnore(['user_id' => $user->id, 'day' => $day, 'queries' => 0, 'row_count' => 0, 'blocked' => 0]);
        DB::table('metabase_usage')->where('user_id', $user->id)->where('day', $day)->update([
            'queries' => DB::raw('queries + '.max(0, $runs)),
            'row_count' => DB::raw('row_count + '.max(0, $rows)),
        ]);
    }

    public static function usage(User $user): array
    {
        $row = DB::table('metabase_usage')->where('user_id', $user->id)->where('day', today()->toDateString())->first();

        return ['queries' => (int) ($row->queries ?? 0), 'rows' => (int) ($row->row_count ?? 0)];
    }

    public static function canWriteSql(User $user): bool
    {
        return config('metabase.queries') && config('metabase.sql') === 'admins' && $user->is_admin;
    }

    public static function canQuery(): bool
    {
        return (bool) config('metabase.queries');
    }

    /** Whether a column looks like a personal detail (email, phone, name, address, card…), by Metabase's type or its name. */
    public static function sensitive(string $name, ?string $semanticType, string $kind): bool
    {
        if ($semanticType && in_array($semanticType, config('metabase.sensitive_types'), true)) {
            return true;
        }
        $name = str_replace([' ', '-'], '_', mb_strtolower($name));
        if (! preg_match(config('metabase.sensitive_names'), $name)) {
            return false;
        }

        return $kind !== 'number' || (bool) preg_match(config('metabase.sensitive_numbers'), $name);
    }

    /** Whether a column's values are mostly emails or phone numbers. */
    public static function looksPersonal(array $values): bool
    {
        $values = array_values(array_filter($values, fn ($v) => is_string($v) && trim($v) !== ''));
        if (! $values) {
            return false;
        }
        $hits = count(array_filter($values, fn ($v) => preg_match('/[^@\s]+@[^@\s]+\.[a-z]{2,}/i', $v)
            || preg_match('/^\+?[\d\s().-]{7,20}$/', trim($v)) && strlen(preg_replace('/\D/', '', $v)) >= 7));

        return $hits / count($values) >= 0.5;
    }

    /** "j•••@gmail.com", "•••4321", "S•••". Enough to tell rows apart, not to copy them. */
    public static function mask($v): ?string
    {
        if ($v === null || $v === '') {
            return $v === null ? null : '';
        }
        $s = is_scalar($v) ? (string) $v : json_encode($v);
        if (str_contains($s, '@')) {
            [$local, $domain] = explode('@', $s, 2);

            return mb_substr($local, 0, 1).'•••@'.$domain;
        }
        $digits = preg_replace('/\D/', '', $s);
        if (strlen($digits) >= 7 && strlen($digits) >= strlen(preg_replace('/\s/', '', $s)) - 4) {
            return '•••'.substr($digits, -4);
        }

        return mb_substr($s, 0, 1).'•••';
    }
}
