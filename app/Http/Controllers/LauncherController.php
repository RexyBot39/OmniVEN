<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Tool;
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
            $items->push(['k' => 'Action', 't' => 'Add an account', 's' => 'Brand + platform', 'u' => route('accounts.create'), 'x' => false]);
        }

        foreach (Brand::orderBy('sort')->get() as $b) {
            $items->push(['k' => 'Brand', 't' => $b->name, 's' => 'Brand hub · every account and tool', 'u' => route('brands.show', $b), 'x' => false]);
        }

        foreach (Account::with('brand')->get() as $a) {
            $items->push([
                'k' => 'Account', 't' => $a->title(),
                's' => collect([$a->account_ref ? 'ID '.$a->account_ref : null, $a->login ? 'Login '.$a->login : null, $a->hasDirectLink() ? null : 'No direct link yet'])->filter()->implode(' · '),
                'u' => $a->openUrl(), 'x' => true,
                // Extra words people type: platform short code and group, e.g. "ig", "social".
                'a' => strtolower(($a->platformInfo()['short'] ?? '').' '.$a->group().' '.$a->platform),
            ]);
        }

        foreach (Tool::orderBy('name')->get() as $t) {
            $items->push(['k' => 'Tool', 't' => $t->name, 's' => $t->category ?? '', 'u' => $t->url ?: $home.'#tools', 'x' => (bool) $t->url, 'a' => strtolower((string) $t->description)]);
        }

        return response()->json($items->values());
    }
}
