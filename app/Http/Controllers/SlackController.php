<?php

namespace App\Http\Controllers;

use App\Models\SlackChannel;
use App\Support\Activity;
use App\Support\Slack;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class SlackController extends Controller
{
    /** HTML fragment for the Slack tab: channel picker, messages, reply box. Loaded by the page's script. */
    public function feed(Request $request): View
    {
        if (! Slack::configured()) {
            return view('slack.feed', ['state' => 'unconfigured']);
        }

        $this->loadDefaultChannels();
        $channels = SlackChannel::orderBy('sort')->orderBy('name')->get();
        $current = $channels->firstWhere('channel_id', $request->input('channel')) ?? $channels->first();

        $messages = [];
        $error = null;
        $users = [];
        $team = ['url' => 'https://app.slack.com', 'id' => null];
        if ($current) {
            try {
                $team = Slack::team();
                $users = Slack::users();
                $messages = collect(Slack::history($current->channel_id))->map(fn ($m) => $this->present($m, $users, $current->channel_id))->all();
            } catch (Throwable $e) {
                $error = $this->explain($e->getMessage(), $current->name);
                Log::warning('Slack feed failed', ['channel' => $current->name, 'error' => $e->getMessage()]);
            }
        }

        return view('slack.feed', [
            'state' => 'ok', 'channels' => $channels, 'current' => $current,
            'messages' => $messages, 'error' => $error, 'team' => $team,
        ]);
    }

    public function post(Request $request, SlackChannel $channel): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:3000']]);
        $user = $request->user();
        try {
            Slack::post($channel->channel_id, $data['text'], $user->name, $user->avatar);
        } catch (Throwable $e) {
            return response()->json(['message' => $this->explain($e->getMessage(), $channel->name)], 422);
        }
        Activity::log('slack.posted', "Posted in Slack #{$channel->name}");

        return response()->json(['ok' => true]);
    }

    /** Admins add a channel by name; the bot joins public channels itself. */
    public function store(Request $request): RedirectResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:120']])['name'];
        try {
            $found = Slack::findChannel($name);
            if (! $found) {
                return $this->back("Couldn't find a Slack channel called “{$name}”.", true);
            }
            if (! $found['is_member'] && ! $found['is_private']) {
                Slack::join($found['id']);
            }
        } catch (Throwable $e) {
            return $this->back($this->explain($e->getMessage(), $name), true);
        }

        $ch = SlackChannel::updateOrCreate(['channel_id' => $found['id']], ['name' => $found['name'], 'sort' => SlackChannel::max('sort') + 1]);
        Activity::log('slack.channel_added', "Added Slack channel #{$ch->name} to OmniVEN");
        $note = $found['is_private'] && ! $found['is_member'] ? ' It\'s private: in Slack, type /invite @OmniVEN in that channel.' : '';

        return $this->back("Added #{$ch->name}.".$note);
    }

    public function destroy(SlackChannel $channel): RedirectResponse
    {
        Activity::log('slack.channel_removed', "Removed Slack channel #{$channel->name} from OmniVEN");
        $channel->delete();

        return $this->back("Removed #{$channel->name} from OmniVEN. Nothing changed in Slack.");
    }

    private function back(string $msg, bool $error = false): RedirectResponse
    {
        return redirect()->to(route('dashboard').'#slack')->with($error ? 'error' : 'status', $msg);
    }

    /** First run: add the default channels (OMNIVEN_SLACK_CHANNELS) so the tab isn't empty. */
    private function loadDefaultChannels(): void
    {
        if (SlackChannel::exists() || Cache::has('slack.defaults_tried')) {
            return;
        }
        Cache::put('slack.defaults_tried', true, now()->addMinutes(10));
        foreach (array_filter(array_map('trim', explode(',', (string) config('services.slack_app.default_channels')))) as $i => $name) {
            try {
                $found = Slack::findChannel($name);
                if (! $found) {
                    continue;
                }
                if (! $found['is_member'] && ! $found['is_private']) {
                    Slack::join($found['id']);
                }
                SlackChannel::firstOrCreate(['channel_id' => $found['id']], ['name' => $found['name'], 'sort' => $i + 1]);
            } catch (Throwable $e) {
                Log::warning('Slack default channel failed', ['channel' => $name, 'error' => $e->getMessage()]);
            }
        }
    }

    private function present(array $m, array $users, string $channelId): array
    {
        $uid = $m['user'] ?? null;
        $name = $m['username'] ?? ($m['bot_profile']['name'] ?? null) ?? ($uid ? ($users[$uid]['name'] ?? 'Someone') : 'Slack');
        $avatar = $m['icons']['image_48'] ?? ($m['bot_profile']['icons']['image_48'] ?? null) ?? ($uid ? ($users[$uid]['avatar'] ?? null) : null);
        $at = Carbon::createFromTimestamp((float) $m['ts'])->setTimezone(config('app.timezone'));

        return [
            'name' => $name,
            'avatar' => $avatar && str_starts_with($avatar, 'https://') ? $avatar : null,
            'when' => $at->isToday() ? 'Today '.$at->format('g:i A') : ($at->isYesterday() ? 'Yesterday '.$at->format('g:i A') : $at->format('M j, g:i A')),
            'iso' => $at->toIso8601String(),
            'html' => Slack::render($m['text'] ?? '', $users),
            'files' => collect($m['files'] ?? [])->pluck('name')->filter()->all(),
            'replies' => (int) ($m['reply_count'] ?? 0),
            'link' => Slack::permalink($channelId, $m['ts']),
        ];
    }

    private function explain(string $code, string $channel): string
    {
        return match ($code) {
            'not_in_channel' => "OmniVEN isn't in #{$channel} yet. In Slack, open #{$channel} and type /invite @OmniVEN.",
            'channel_not_found' => "Slack can't find #{$channel}. It may be private (invite @OmniVEN) or archived.",
            'invalid_auth', 'not_authed', 'account_inactive', 'token_revoked' => 'The Slack connection has stopped working. An admin needs to update SLACK_BOT_TOKEN in Railway.',
            'missing_scope' => 'The Slack app is missing a permission. See the Slack setup steps in the README.',
            'ratelimited' => 'Slack asked us to slow down. Try again in a minute.',
            default => "Slack didn't respond as expected ({$code}). Try again in a minute.",
        };
    }
}
