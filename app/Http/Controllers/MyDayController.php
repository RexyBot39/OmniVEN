<?php

namespace App\Http\Controllers;

use App\Support\MyDay;
use App\Support\Slack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Your morning Slack summary: preview it, turn it on or off, or send it to yourself now. */
class MyDayController extends Controller
{
    public function show(Request $request): View
    {
        $me = $request->user();
        $d = MyDay::for($me);

        return view('my-day', ['me' => $me, 'd' => $d, 'text' => MyDay::text($me, $d), 'empty' => MyDay::isEmpty($d), 'slack' => Slack::configured()]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $me = $request->user();
        $me->forceFill(['myday_slack' => ! ($me->myday_slack ?? true)])->save();

        return back()->with('status', $me->myday_slack ? 'Your morning summary is on. It arrives in Slack at 8:30 on weekdays.' : 'Your morning summary is off.');
    }

    public function send(Request $request): RedirectResponse
    {
        try {
            $r = MyDay::send($request->user(), true);
        } catch (RuntimeException $e) {
            $msg = match ($e->getMessage()) {
                'missing_scope' => 'The OmniVEN Slack app needs the users:read.email permission. An admin should reinstall it from slack-app-manifest.yml.',
                'invalid_auth', 'not_authed' => 'Slack refused OmniVEN’s token. An admin should check SLACK_BOT_TOKEN.',
                default => 'Slack said: '.$e->getMessage(),
            };

            return back()->withErrors(['slack' => $msg]);
        }

        return back()->with('status', $r === 'sent' ? 'Sent. Check your Slack messages from OmniVEN.' : 'Couldn’t find your Slack account by your work email. Ask an admin.');
    }
}
