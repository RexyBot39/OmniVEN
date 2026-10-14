<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Slack Web API, used by OmniVEN's Slack tab.
 * Scopes the bot needs: channels:read, channels:history, channels:join, chat:write,
 * chat:write.customize, users:read (+ groups:read, groups:history for private channels).
 */
class Slack
{
    public static function configured(): bool
    {
        return filled(config('services.slack_app.bot_token'));
    }

    /** Calls a Slack method; throws with Slack's error code (e.g. not_in_channel) on failure. */
    public static function call(string $method, array $params = [], bool $post = false): array
    {
        $req = Http::withToken(config('services.slack_app.bot_token'))->acceptJson()->timeout(10);
        $res = $post
            ? $req->asJson()->post("https://slack.com/api/{$method}", $params)
            : $req->get("https://slack.com/api/{$method}", $params);
        $json = $res->json() ?? [];
        if (! ($json['ok'] ?? false)) {
            throw new RuntimeException($json['error'] ?? 'http_'.$res->status());
        }

        return $json;
    }

    /** Workspace URL (https://yourteam.slack.com/) and team id, for "Open in Slack" links. */
    public static function team(): array
    {
        return Cache::remember('slack.team', now()->addDay(), function () {
            $t = self::call('auth.test');

            return ['url' => rtrim($t['url'] ?? 'https://app.slack.com', '/'), 'id' => $t['team_id'] ?? null];
        });
    }

    /** Finds a channel by name (with or without #) or by id. */
    public static function findChannel(string $nameOrId): ?array
    {
        $needle = strtolower(ltrim(trim($nameOrId), '#'));
        $cursor = null;
        do {
            $page = self::call('conversations.list', array_filter([
                'types' => 'public_channel,private_channel', 'exclude_archived' => 'true', 'limit' => 1000, 'cursor' => $cursor,
            ]));
            foreach ($page['channels'] ?? [] as $c) {
                if (strtolower($c['name']) === $needle || strtolower($c['id']) === $needle) {
                    return ['id' => $c['id'], 'name' => $c['name'], 'is_private' => $c['is_private'] ?? false, 'is_member' => $c['is_member'] ?? false];
                }
            }
            $cursor = $page['response_metadata']['next_cursor'] ?? null;
        } while ($cursor);

        return null;
    }

    /** Joins a public channel so the bot can read it. Private channels need a /invite in Slack. */
    public static function join(string $channelId): void
    {
        self::call('conversations.join', ['channel' => $channelId], true);
    }

    /** Latest messages, newest last. Cached briefly so many viewers don't hit Slack's rate limits. */
    public static function history(string $channelId, int $limit = 40): array
    {
        return Cache::remember("slack.history.{$channelId}", now()->addSeconds(45), function () use ($channelId, $limit) {
            $res = self::call('conversations.history', ['channel' => $channelId, 'limit' => $limit]);

            return array_reverse(array_values(array_filter($res['messages'] ?? [],
                fn ($m) => ! in_array($m['subtype'] ?? '', ['channel_join', 'channel_leave', 'channel_purpose', 'channel_topic'], true))));
        });
    }

    /** Map of Slack user id => [name, avatar]. */
    public static function users(): array
    {
        return Cache::remember('slack.users', now()->addHours(6), function () {
            $out = [];
            $cursor = null;
            do {
                $page = self::call('users.list', array_filter(['limit' => 500, 'cursor' => $cursor]));
                foreach ($page['members'] ?? [] as $u) {
                    $p = $u['profile'] ?? [];
                    $out[$u['id']] = [
                        'name' => ($p['display_name'] ?? '') ?: (($p['real_name'] ?? '') ?: ($u['name'] ?? 'Someone')),
                        'avatar' => $p['image_48'] ?? null,
                    ];
                }
                $cursor = $page['response_metadata']['next_cursor'] ?? null;
            } while ($cursor);

            return $out;
        });
    }

    public static function post(string $channelId, string $text, string $asName, ?string $iconUrl = null): void
    {
        self::call('chat.postMessage', array_filter([
            'channel' => $channelId,
            'text' => $text,
            'username' => $asName.' (via OmniVEN)',
            'icon_url' => $iconUrl && strlen($iconUrl) < 1000 ? $iconUrl : null,
            'unfurl_links' => false,
        ]), true);
        Cache::forget("slack.history.{$channelId}");
    }

    public static function permalink(string $channelId, string $ts): string
    {
        return self::team()['url'].'/archives/'.$channelId.'/p'.str_replace('.', '', $ts);
    }

    /** Slack mrkdwn → safe HTML. Text is escaped first; only http(s) links become anchors. */
    public static function render(string $text, array $users): string
    {
        $h = e($text);
        $h = preg_replace_callback('/&lt;@([A-Z0-9]+)(?:\|[^&]*)?&gt;/', fn ($m) => '<span class="sl-mention">@'.e($users[$m[1]]['name'] ?? 'someone').'</span>', $h);
        $h = preg_replace('/&lt;#[A-Z0-9]+\|([^&]+)&gt;/', '<span class="sl-mention">#$1</span>', $h);
        $h = preg_replace('/&lt;!(here|channel|everyone)[^&]*&gt;/', '<span class="sl-mention">@$1</span>', $h);
        $h = preg_replace_callback('/&lt;(https?:\/\/[^|&\s]+(?:&amp;[^|&\s]+)*)(?:\|([^&]+))?&gt;/', function ($m) {
            $url = html_entity_decode($m[1]);

            return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.($m[2] ?? e($url)).'</a>';
        }, $h);
        $h = preg_replace('/```(.+?)```/s', '<pre>$1</pre>', $h);
        $h = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $h);
        $h = preg_replace('/(^|[\s(])\*([^*\n]+)\*(?=[\s).,!?:;]|$)/m', '$1<strong>$2</strong>', $h);
        $h = preg_replace('/(^|[\s(])_([^_\n]+)_(?=[\s).,!?:;]|$)/m', '$1<em>$2</em>', $h);

        return nl2br($h, false);
    }
}
