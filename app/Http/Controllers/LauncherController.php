<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Tool;
use App\Support\Launchpad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Everything the ⌘K launcher can jump to, for the signed-in person. */
class LauncherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $home = route('dashboard');
        $items = collect();

        $pages = [
            ['Routines', 'routines'], ['Tasks', 'tasks'], ['PostIT Notes', 'postit'], ['Slack', 'slack'],
            ['Brands', 'brands'], ['Tools', 'tools'], ['Access', 'access'],
        ];
        foreach ($pages as [$name, $tab]) {
            $items->push(['k' => 'Page', 't' => $name, 's' => 'OmniVEN tab', 'u' => $home.'#'.$tab, 'x' => false]);
        }
        if ($user->can('view-activity')) {
            $items->push(['k' => 'Page', 't' => 'Activity report', 's' => 'Admin', 'u' => route('activity.index'), 'x' => false]);
        }
        if ($user->can('admin')) {
            $items->push(['k' => 'Page', 't' => 'People', 's' => 'Admin', 'u' => route('people.index'), 'x' => false]);
            foreach (\App\Models\User::where('is_active', true)->orderBy('name')->get() as $p) {
                $items->push(['k' => 'Page', 't' => $p->name, 's' => 'Staff page · routines and tasks', 'u' => route('people.show', $p), 'x' => false, 'a' => 'staff person people '.strtolower($p->email)]);
            }
            $items->push(['k' => 'Action', 't' => 'Add an account', 's' => 'Brand + platform', 'u' => route('accounts.create'), 'x' => false]);
            $items->push(['k' => 'Page', 't' => 'Access review', 's' => 'Admin · quarterly check of every login and owner', 'u' => route('access.review'), 'x' => false]);
            $waiting = \App\Models\AccessRequest::open()->count();
            if ($waiting) {
                $items->push(['k' => 'Action', 't' => $waiting.' access '.\Illuminate\Support\Str::plural('request', $waiting), 's' => 'Waiting for an admin', 'u' => $home.'#access', 'x' => false]);
            }
        }

        foreach (Brand::orderBy('sort')->get() as $b) {
            $items->push(['k' => 'Brand', 'key' => $b->launchKey(), 't' => $b->name, 's' => 'Brand hub · every account and tool', 'u' => route('brands.show', $b), 'x' => false]);
        }

        foreach (Account::with('brand')->get() as $a) {
            $items->push([
                'k' => 'Account', 'key' => $a->launchKey(), 't' => $a->title(),
                's' => collect([$a->account_ref ? 'ID '.$a->account_ref : null, $a->login ? 'Login '.$a->login : null, $a->hasDirectLink() ? null : 'No direct link yet'])->filter()->implode(' · '),
                'u' => $a->openUrl(), 'x' => true,
                // Extra words people type: platform short code and group, e.g. "ig", "social".
                'a' => strtolower(($a->platformInfo()['short'] ?? '').' '.$a->group().' '.$a->platform),
            ]);
        }

        foreach (Tool::orderBy('name')->get() as $t) {
            $items->push(['k' => 'Tool', 'key' => $t->launchKey(), 't' => $t->name, 's' => $t->category ?? '', 'u' => $t->url ?: $home.'#tools', 'x' => (bool) $t->url, 'a' => strtolower((string) $t->description)]);
        }

        // Your launchpad: f = position among your stars, r = how recently you opened it (0 = latest).
        $mine = Launchpad::forUser($user, Launchpad::KEEP_RECENT);
        $fav = $mine['favorites']->pluck('key')->flip();
        $recent = $mine['recent']->pluck('key')->flip();
        $items = $items->map(function ($it) use ($fav, $recent) {
            if (isset($it['key'])) {
                if ($fav->has($it['key'])) {
                    $it['f'] = $fav[$it['key']];
                }
                if ($recent->has($it['key'])) {
                    $it['r'] = $recent[$it['key']];
                }
            }

            return $it;
        });

        return response()->json($items->values());
    }
}
