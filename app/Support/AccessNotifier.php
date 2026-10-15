<?php

namespace App\Support;

use App\Models\AccessRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Short Slack notes for admins (access requests, broken links). Never includes a request's reason. */
class AccessNotifier
{
    public static function requested(AccessRequest $req): void
    {
        $url = route('dashboard').'#access';
        self::postToChannel(':key: *'.self::clean($req->user->name).'* requested access to *'.self::clean($req->itemName()).'*. '
            .'An admin can grant or decline it on the <'.$url.'|OmniVEN Access tab>.');
    }

    /** Posts to OMNIVEN_ACCESS_SLACK_CHANNEL. Returns false when not set up or Slack fails; never throws. */
    public static function postToChannel(string $text): bool
    {
        $channel = trim((string) config('omniven.access_slack_channel'));
        if ($channel === '' || ! Slack::configured()) {
            return false;
        }
        try {
            $id = Cache::remember('slack.access-channel.'.md5($channel), now()->addDay(), fn () => Slack::findChannel($channel)['id'] ?? null);
            if (! $id) {
                Log::warning('Admin Slack channel not found', ['channel' => $channel]);

                return false;
            }
            Slack::post($id, $text, 'OmniVEN');

            return true;
        } catch (Throwable $e) {
            // Whatever triggered the note is saved either way; admins still see it in OmniVEN.
            Log::warning('Admin Slack post failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /** Stop names from carrying Slack markup like <!channel> or links. */
    public static function clean(string $s): string
    {
        return str_replace(['&', '<', '>', '*'], ['&amp;', '&lt;', '&gt;', ''], $s);
    }
}
