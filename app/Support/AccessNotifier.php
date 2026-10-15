<?php

namespace App\Support;

use App\Models\AccessRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Posts a short Slack note when someone requests access. Never includes the reason. */
class AccessNotifier
{
    public static function requested(AccessRequest $req): void
    {
        $channel = trim((string) config('omniven.access_slack_channel'));
        if ($channel === '' || ! Slack::configured()) {
            return;
        }
        try {
            $id = Cache::remember('slack.access-channel.'.md5($channel), now()->addDay(), fn () => Slack::findChannel($channel)['id'] ?? null);
            if (! $id) {
                Log::warning('Access request Slack channel not found', ['channel' => $channel]);

                return;
            }
            $url = route('dashboard').'#access';
            $text = ':key: *'.self::clean($req->user->name).'* requested access to *'.self::clean($req->itemName()).'*. '
                .'An admin can grant or decline it on the <'.$url.'|OmniVEN Access tab>.';
            Slack::post($id, $text, 'OmniVEN');
        } catch (Throwable $e) {
            // The request is saved either way; admins still see it on the Access tab.
            Log::warning('Access request Slack post failed', ['request_id' => $req->id, 'error' => $e->getMessage()]);
        }
    }

    /** Stop names from carrying Slack markup like <!channel> or links. */
    private static function clean(string $s): string
    {
        return str_replace(['&', '<', '>', '*'], ['&amp;', '&lt;', '&gt;', ''], $s);
    }
}
