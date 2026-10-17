<?php

namespace App\Support;

use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The morning "my day" message: one person's Hotlist, overdue and due-today tasks and the routines
 * assigned to them that are due, sent as a Slack DM from the OmniVEN app. Task titles only.
 */
class MyDay
{
    private const PER_SECTION = 8;

    /** What's on someone's plate today, grouped. */
    public static function for(User $user): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $open = Task::with('brand')->open()->where('assignee_id', $user->id)
            ->orderByRaw('due_on is null')->orderBy('due_on')->get();
        $routines = Routine::where('assignee_id', $user->id)->orderBy('sort')->get()->reject->isDone()->values();

        $hot = $open->where('hot', true)->values();
        $overdue = $open->filter(fn ($t) => ! $t->hot && $t->due_on && $t->due_on->lt($today))->values();
        $dueToday = $open->filter(fn ($t) => ! $t->hot && $t->due_on && $t->due_on->isSameDay($today))->values();
        $rest = $open->count() - $hot->count() - $overdue->count() - $dueToday->count();

        return compact('hot', 'overdue', 'dueToday', 'routines', 'rest') + ['open' => $open->count(), 'today' => $today];
    }

    public static function isEmpty(array $d): bool
    {
        return $d['hot']->isEmpty() && $d['overdue']->isEmpty() && $d['dueToday']->isEmpty() && $d['routines']->isEmpty();
    }

    /** Slack message text (mrkdwn). Titles are escaped so they can't form links or mentions. */
    public static function text(User $user, array $d): string
    {
        $esc = fn ($s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string) $s);
        $line = function ($t) use ($esc, $d) {
            $bits = [];
            if ($t->brand) {
                $bits[] = $t->brand->name;
            }
            if ($t->due_on) {
                $bits[] = $t->due_on->lt($d['today']) ? 'due '.$t->due_on->format('M j') : ($t->due_on->isSameDay($d['today']) ? 'due today' : 'due '.$t->due_on->format('M j'));
            }

            return '• '.$esc($t->title).($bits ? ' _('.$esc(implode(' · ', $bits)).')_' : '');
        };
        $section = function (string $head, $items, callable $fmt) {
            if ($items->isEmpty()) {
                return null;
            }
            $lines = $items->take(self::PER_SECTION)->map($fmt)->all();
            if ($items->count() > self::PER_SECTION) {
                $lines[] = '• …and '.($items->count() - self::PER_SECTION).' more';
            }

            return "*{$head}* ({$items->count()})\n".implode("\n", $lines);
        };

        $parts = array_filter([
            '*Good morning, '.$esc(rtrim($user->firstName(), '.')).'.* Here’s your day for '.$d['today']->format('l, M j').'.',
            $section('🔥 Hotlist', $d['hot'], $line),
            $section('⚠️ Overdue', $d['overdue'], $line),
            $section('📅 Due today', $d['dueToday'], $line),
            $section('↻ Routines due', $d['routines'], fn ($r) => '• '.$esc($r->title).' _('.strtolower(config('omniven.cadences.'.$r->cadence, $r->cadence)).')_'
                .($r->isSplit() ? ' · still to do: '.$esc($r->splitBrands()->reject(fn ($b) => $r->currentBrandChecks()->has($b->id))->pluck('name')->implode(', ')) : '')),
            ($d['rest'] > 0 ? $d['rest'].' more open '.($d['rest'] === 1 ? 'task' : 'tasks').' later on. ' : '').'<'.route('dashboard').'#tasks|Open your board in OmniVEN>',
        ]);

        return implode("\n\n", $parts);
    }

    /**
     * Send one person their message. Returns 'sent', 'empty', 'off', 'no_slack_user' or throws on a
     * Slack error (missing_scope etc.). Never sends twice in one day unless forced.
     */
    public static function send(User $user, bool $force = false): string
    {
        if (! $force && (! ($user->myday_slack ?? true) || ! $user->is_active)) {
            return 'off';
        }
        $today = CarbonImmutable::now(config('app.timezone'));
        if (! $force && $user->myday_sent_on?->isSameDay($today)) {
            return 'sent';
        }
        $d = self::for($user);
        if (! $force && self::isEmpty($d)) {
            return 'empty';
        }
        $slackId = $user->slack_user_id ?: self::lookup($user);
        if (! $slackId) {
            return 'no_slack_user';
        }
        Slack::call('chat.postMessage', ['channel' => $slackId, 'text' => self::text($user, $d), 'unfurl_links' => false, 'unfurl_media' => false], true);
        $user->forceFill(['myday_sent_on' => $today->format('Y-m-d')])->save();

        return 'sent';
    }

    /** Find the person's Slack account by their work email (needs the users:read.email scope). */
    private static function lookup(User $user): ?string
    {
        try {
            $json = Slack::call('users.lookupByEmail', ['email' => $user->email]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'users_not_found') {
                return null;
            }
            throw $e;
        }
        $id = $json['user']['id'] ?? null;
        if ($id) {
            $user->forceFill(['slack_user_id' => $id])->save();
        }

        return $id;
    }

    /** Everyone's message, weekdays only unless forced. Returns counts by outcome. */
    public static function sendAll(bool $force = false): array
    {
        $counts = [];
        if (! $force && CarbonImmutable::now(config('app.timezone'))->isWeekend()) {
            return ['weekend' => 1];
        }
        foreach (User::where('is_active', true)->where('myday_slack', true)->orderBy('id')->get() as $u) {
            try {
                $r = self::send($u);
            } catch (Throwable $e) {
                $r = 'error: '.$e->getMessage();
                Log::warning('My day message failed', ['user' => $u->id, 'error' => $e->getMessage()]);
                if (in_array($e->getMessage(), ['missing_scope', 'invalid_auth', 'not_authed', 'account_inactive'], true)) {
                    $counts[$r] = ($counts[$r] ?? 0) + 1;
                    break;
                }
            }
            $counts[$r] = ($counts[$r] ?? 0) + 1;
        }

        return $counts;
    }
}
