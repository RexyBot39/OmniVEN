<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Tool;
use App\Support\BrandIcon;
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

        // Major pages: each can be starred, so it carries a "page:N" key.
        $aliases = [
            3 => 'postit post-it postits notes', 8 => 'q4 week runbook boost calendar',
            9 => 'guide how to faq field guide', 10 => 'add assign todo to-do',
        ];
        foreach (Launchpad::PAGES as $id => [$name, $sub, $route, $hash, $gate]) {
            if ($gate && ! $user->can($gate)) {
                continue;
            }
            $items->push(['k' => 'Page', 'key' => 'page:'.$id, 't' => $name, 's' => $sub, 'u' => route($route).($hash ? '#'.$hash : ''), 'x' => false, 'a' => $aliases[$id] ?? '']);
        }
        // Tool categories, e.g. Affiliate: open the Tools tab at that section.
        foreach (Launchpad::categories() as $id => $name) {
            $items->push(['k' => 'Category', 'key' => 'category:'.$id, 't' => $name, 's' => 'Tool category', 'u' => route('dashboard', ['cat' => Launchpad::categorySlug($name)]).'#tools', 'x' => false, 'a' => 'tools category']);
        }
        if ($user->can('admin')) {
            foreach (\App\Models\User::where('is_active', true)->orderBy('name')->get() as $p) {
                $items->push(['k' => 'Page', 't' => $p->name, 's' => 'Staff page · routines and tasks', 'u' => route('people.show', $p), 'x' => false, 'a' => 'staff person people '.strtolower($p->email)]);
            }
            $items->push(['k' => 'Action', 't' => 'Add an account', 's' => 'Brand + platform', 'u' => route('accounts.create'), 'x' => false]);
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
                'u' => $a->openUrl(), 'x' => true, 'ic' => BrandIcon::meta(BrandIcon::forPlatform($a->platform)),
                // Extra words people type: platform short code and group, e.g. "ig", "social".
                'a' => strtolower(($a->platformInfo()['short'] ?? '').' '.$a->group().' '.$a->platform),
            ]);
        }

        foreach (Tool::orderBy('name')->get() as $t) {
            $items->push(['k' => 'Tool', 'key' => $t->launchKey(), 't' => $t->name, 's' => $t->category ?? '', 'u' => $t->url ?: $home.'#tools', 'x' => (bool) $t->url, 'a' => strtolower((string) $t->description), 'ic' => BrandIcon::meta(BrandIcon::forTool($t))]);
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
