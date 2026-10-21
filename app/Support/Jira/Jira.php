<?php

namespace App\Support\Jira;

use App\Models\Task;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Tasks and the company Jira board (Jira Cloud REST API v3). A task can be sent as a ticket; the board then shows
 * the ticket's column, a ticket that reaches Done ticks the task off, and ticking the task moves the ticket to Done.
 * The API token is used from the server only.
 */
class Jira
{
    public static function configured(): bool
    {
        return filled(config('jira.base_url')) && filled(config('jira.email')) && filled(config('jira.api_token')) && filled(config('jira.project'));
    }

    public static function link(string $key): string
    {
        return config('jira.base_url').'/browse/'.rawurlencode($key);
    }

    /** Create the ticket for a task and remember its key. */
    public static function send(Task $task, User $by): string
    {
        if ($task->jira_key) {
            return $task->jira_key;
        }
        $fields = [
            'project' => ['key' => (string) config('jira.project')],
            'issuetype' => ['name' => (string) config('jira.issue_type')],
            'summary' => mb_substr(trim(preg_replace('/\s+/', ' ', $task->title)), 0, 250),
            'description' => self::description($task, $by),
            'labels' => array_values(array_filter([config('jira.label'), $task->brand?->slug])),
        ];
        if ($task->due_on) {
            $fields['duedate'] = $task->due_on->format('Y-m-d');
        }
        if ($id = self::accountId((string) $task->assignee?->email)) {
            $fields['assignee'] = ['id' => $id];
        }

        try {
            $issue = self::call('post', '/rest/api/3/issue', ['fields' => $fields]);
        } catch (FieldRejected $e) {
            // Boards differ: a project without due dates, labels or that assignee says so; send without those.
            $issue = self::call('post', '/rest/api/3/issue', ['fields' => array_diff_key($fields, array_flip($e->fields))]);
        }
        $key = (string) ($issue['key'] ?? throw new RuntimeException('Jira didn’t return a ticket number.'));
        $task->update(['jira_key' => $key, 'jira_status' => 'To Do', 'jira_synced_at' => now()]);

        return $key;
    }

    /** Check the column of every open task's ticket (and recently finished ones), and tick off tasks whose ticket is Done. */
    public static function sync(): int
    {
        $done = 0;
        $tasks = Task::whereNotNull('jira_key')->where(fn ($q) => $q->whereNull('completed_at')->orWhere('completed_at', '>=', now()->subDays(14)))->get()->keyBy('jira_key');
        foreach ($tasks->keys()->chunk(50) as $keys) {
            $found = self::call('post', '/rest/api/3/search/jql', ['jql' => 'key in ('.implode(',', $keys->all()).')', 'fields' => ['status'], 'maxResults' => 100]);
            foreach ($found['issues'] ?? [] as $issue) {
                $task = $tasks->get($issue['key'] ?? '');
                $status = $issue['fields']['status'] ?? null;
                if (! $task || ! is_array($status)) {
                    continue;
                }
                $task->fill(['jira_status' => mb_substr((string) ($status['name'] ?? ''), 0, 80), 'jira_synced_at' => now()]);
                if (($status['statusCategory']['key'] ?? null) === 'done' && ! $task->completed_at) {
                    $task->fill(['completed_at' => now(), 'completed_by' => null]);
                    Activity::log('task.completed', "Completed task “{$task->title}” (done in Jira as {$task->jira_key})", $task, $task->assignee);
                    $done++;
                }
                $task->save();
            }
        }
        Cache::forever('jira:last', now()->timestamp);

        return $done;
    }

    public static function syncIfStale(): void
    {
        if (! self::configured() || ! Task::whereNotNull('jira_key')->whereNull('completed_at')->exists()) {
            return;
        }
        $last = Cache::get('jira:last');
        if (is_numeric($last) && now()->timestamp - (int) $last < (int) config('jira.sync_minutes') * 60) {
            return;
        }
        if (! Cache::add('jira:busy', 1, 120)) {
            return;
        }
        dispatch(function () {
            try {
                self::sync();
            } catch (Throwable $e) {
                Log::warning('Jira sync failed', ['error' => $e->getMessage()]);
                Cache::forever('jira:last', now()->timestamp);
            } finally {
                Cache::forget('jira:busy');
            }
        })->afterResponse();
    }

    /** Move a ticket to the board's Done column (whatever its name), when the task is ticked in OmniVEN. */
    public static function finish(Task $task): void
    {
        if (! $task->jira_key || ! self::configured()) {
            return;
        }
        $key = rawurlencode($task->jira_key);
        $options = self::call('get', "/rest/api/3/issue/$key/transitions")['transitions'] ?? [];
        $done = collect($options)->first(fn ($t) => ($t['to']['statusCategory']['key'] ?? null) === 'done');
        if (! $done) {
            return;
        }
        self::call('post', "/rest/api/3/issue/$key/transitions", ['transition' => ['id' => (string) $done['id']]]);
        $task->update(['jira_status' => mb_substr((string) ($done['to']['name'] ?? 'Done'), 0, 80), 'jira_synced_at' => now()]);
    }

    /** The Jira account for an OmniVEN person, by work email (empty when Jira hides emails or has no match). */
    private static function accountId(string $email): ?string
    {
        if ($email === '') {
            return null;
        }

        return Cache::remember('jira:account:'.md5(strtolower($email)), now()->addDay(), function () use ($email) {
            try {
                $users = self::call('get', '/rest/api/3/user/search', ['query' => $email]);
            } catch (RuntimeException) {
                return '';
            }
            $match = collect($users)->first(fn ($u) => is_array($u) && strcasecmp((string) ($u['emailAddress'] ?? ''), $email) === 0)
                ?? (count($users) === 1 && is_array($users[0] ?? null) ? $users[0] : null);

            return (string) ($match['accountId'] ?? '');
        }) ?: null;
    }

    /** The ticket body in Atlassian Document Format: the details, then who and when, then a link back. */
    private static function description(Task $task, User $by): array
    {
        $para = fn (array $content) => ['type' => 'paragraph', 'content' => $content];
        $text = fn (string $t, array $marks = []) => ['type' => 'text', 'text' => $t] + ($marks ? ['marks' => $marks] : []);
        $content = [];
        foreach (preg_split('/\R{2,}/', trim((string) $task->details)) ?: [] as $block) {
            $lines = array_values(array_filter(preg_split('/\R/', $block), fn ($l) => trim($l) !== ''));
            if ($lines) {
                $content[] = $para(array_merge(...array_map(fn ($l, $i) => $i ? [['type' => 'hardBreak'], $text($l)] : [$text($l)], $lines, array_keys($lines))));
            }
        }
        $meta = array_filter([
            $task->assignee ? 'For: '.$task->assignee->name : null,
            $task->brand ? 'Brand: '.$task->brand->name : null,
            $task->due_on ? 'Due: '.$task->due_on->format('M j, Y') : null,
            $task->hot ? 'On the OmniVEN Hotlist (urgent)' : null,
            'Sent from OmniVEN by '.$by->name,
        ]);
        $content[] = $para(array_merge(...array_map(fn ($l, $i) => $i ? [['type' => 'hardBreak'], $text($l)] : [$text($l)], array_values($meta), array_keys(array_values($meta)))));
        $url = route('dashboard').'#tasks';
        $content[] = $para([$text('Open the task in OmniVEN', [['type' => 'link', 'attrs' => ['href' => $url]]])]);

        return ['type' => 'doc', 'version' => 1, 'content' => $content];
    }

    private static function call(string $method, string $path, array $data = []): array
    {
        if (! self::configured()) {
            throw new RuntimeException('Jira isn’t set up: add JIRA_BASE_URL, JIRA_EMAIL, JIRA_API_TOKEN and JIRA_PROJECT.');
        }
        $http = Http::withBasicAuth((string) config('jira.email'), (string) config('jira.api_token'))->acceptJson()->timeout((int) config('jira.timeout'));
        try {
            $res = $method === 'get' ? $http->get(config('jira.base_url').$path, $data) : $http->post(config('jira.base_url').$path, $data);
        } catch (ConnectionException) {
            throw new RuntimeException('Couldn’t reach Jira. Try again in a minute.');
        }

        return self::check($res);
    }

    private static function check(Response $res): array
    {
        $body = $res->json();
        $errors = is_array($body) ? array_merge((array) ($body['errorMessages'] ?? []), array_map(fn ($k, $v) => "$k: $v", array_keys((array) ($body['errors'] ?? [])), (array) ($body['errors'] ?? []))) : [];
        $detail = mb_substr(implode(' ', array_map('strval', $errors)), 0, 300);
        if ($res->status() === 400 && is_array($body['errors'] ?? null) && array_intersect(array_keys($body['errors']), ['duedate', 'labels', 'assignee'])) {
            throw new FieldRejected(array_values(array_intersect(array_keys($body['errors']), ['duedate', 'labels', 'assignee'])), $detail);
        }

        return match (true) {
            $res->status() === 401 => throw new RuntimeException('Jira didn’t accept the login. An admin can check JIRA_EMAIL and JIRA_API_TOKEN.'),
            $res->status() === 403 => throw new RuntimeException('OmniVEN’s Jira account isn’t allowed to do that in project '.config('jira.project').'. Give it “Create issues” and “Transition issues” permission.'),
            $res->status() === 404 => throw new RuntimeException('Jira couldn’t find that'.($detail !== '' ? ': '.$detail : '').'. Check JIRA_PROJECT and JIRA_ISSUE_TYPE.'),
            ! $res->successful() => throw new RuntimeException('Jira couldn’t do that'.($detail !== '' ? ': '.$detail : ' (HTTP '.$res->status().')').'.'),
            default => is_array($body) ? $body : [],
        };
    }
}
