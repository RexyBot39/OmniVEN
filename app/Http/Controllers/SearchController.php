<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Brand;
use App\Models\CalendarItem;
use App\Models\CalendarWeek;
use App\Models\Note;
use App\Models\Routine;
use App\Models\Task;
use App\Models\Tool;
use App\Models\User;
use App\Support\BrandIcon;
use App\Support\Launchpad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The omni search behind the dashboard search box: brands, accounts, tools, pages, categories,
 * routines, tasks, the action calendar, bulletin posts, your own sticky notes and (for admins) people.
 * Never searches or returns logins' passwords, other people's sticky notes or Slack messages.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 6;

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $words = collect(preg_split('/\s+/', Str::lower(Str::limit($q, 80, ''))))->filter(fn ($w) => mb_strlen($w) > 0)->values();
        if ($words->isEmpty() || mb_strlen($q) < 2) {
            return response()->json(['q' => $q, 'groups' => []]);
        }
        $user = $request->user();
        $home = route('dashboard');
        $hit = fn (string ...$fields) => $words->every(fn ($w) => str_contains(Str::lower(implode(' ', $fields)), $w));

        $groups = [];
        $add = function (string $label, $items) use (&$groups) {
            $items = collect($items)->filter()->values();
            if ($items->isNotEmpty()) {
                $groups[] = ['label' => $label, 'total' => $items->count(), 'items' => $items->take(self::PER_GROUP)->all()];
            }
        };

        $add('Brands', Brand::orderBy('sort')->get()->filter(fn ($b) => $hit($b->name, $b->slug, (string) $b->site, (string) $b->focus, (string) $b->channels, (string) $b->notes))
            ->map(fn ($b) => ['t' => $b->name, 's' => 'Brand hub'.($b->focus ? ' · '.Str::limit($b->focus, 70) : ''), 'u' => route('brands.show', $b), 'x' => false]));

        $add('Pages', collect(Launchpad::PAGES)->filter(fn ($p) => ! $p[4] || $user->can($p[4]))
            ->filter(fn ($p) => $hit($p[0], $p[1], $p[3] ?? ''))
            ->map(fn ($p) => ['t' => $p[0], 's' => $p[1], 'u' => route($p[2]).($p[3] ? '#'.$p[3] : ''), 'x' => false])
            ->merge(collect(Launchpad::categories())->filter(fn ($n) => $hit($n, 'category tools'))
                ->map(fn ($n) => ['t' => $n, 's' => 'Tool category', 'u' => route('dashboard', ['cat' => Launchpad::categorySlug($n)]).'#tools', 'x' => false])));

        $add('Tools', Tool::with('brands')->orderBy('name')->get()
            ->filter(fn ($t) => $hit($t->name, (string) $t->category, (string) $t->description, (string) $t->login, (string) $t->owner, (string) $t->access_note, $t->brands->pluck('name')->implode(' ')))
            ->map(fn ($t) => ['t' => $t->name, 's' => collect([$t->category, Str::limit((string) $t->description, 70)])->filter()->implode(' · '), 'u' => $t->url ?: $home.'#tools', 'x' => (bool) $t->url, 'ic' => BrandIcon::meta(BrandIcon::forTool($t))]));

        $add('Accounts', Account::with('brand')->get()
            ->filter(fn ($a) => $hit($a->title(), $a->platform, (string) ($a->platformInfo()['short'] ?? ''), $a->group(), (string) $a->account_ref, (string) $a->login, (string) $a->owner, (string) $a->notes))
            ->map(fn ($a) => ['t' => $a->title(), 's' => collect([$a->account_ref ? 'ID '.$a->account_ref : null, $a->owner ? 'Owner '.$a->owner : null])->filter()->implode(' · ') ?: $a->platformLabel(), 'u' => $a->openUrl() ?: ($a->brand ? route('brands.show', $a->brand) : $home.'#access'), 'x' => (bool) $a->openUrl(), 'ic' => BrandIcon::meta(BrandIcon::forPlatform($a->platform))]));

        $cadences = config('omniven.cadences');
        $add('Routines', Routine::with(['brands', 'assignee'])->orderBy('sort')->get()
            ->filter(fn ($r) => $hit($r->title, (string) $r->description, (string) $r->cadence, $r->brands->pluck('name')->implode(' '), (string) $r->assignee?->name))
            ->map(fn ($r) => ['t' => $r->title, 's' => collect([$cadences[$r->cadence] ?? $r->cadence, $r->assignee ? '→ '.$r->assignee->firstName() : null, $r->isDone() ? 'Done' : 'Due'])->filter()->implode(' · '), 'u' => $home.'#routines', 'x' => false]));

        $add('Tasks', Task::with('assignee')->orderByRaw('completed_at is not null')->orderBy('due_on')->get()
            ->filter(fn ($t) => $hit($t->title, (string) $t->details, (string) $t->assignee?->name))
            ->map(fn ($t) => ['t' => $t->title, 's' => collect([$t->assignee ? '→ '.$t->assignee->firstName() : null, $t->due_on ? 'Due '.$t->due_on->format('M j') : null, $t->completed_at ? 'Done' : ($t->hot ? 'Hotlist' : null)])->filter()->implode(' · '), 'u' => $home.'#tasks', 'x' => false]));

        $current = CalendarWeek::current();
        $weeks = CalendarWeek::orderBy('number')->get();
        $calendar = CalendarItem::with('week')->get()
            ->filter(fn ($i) => $hit($i->title, (string) $i->keyword, (string) $i->done_when, (string) $i->owner, (string) $i->brand_slug, (string) $i->day))
            ->map(function ($i) use ($current, $weeks) {
                $week = $i->week ?? $current ?? $weeks->first();

                return $week ? ['t' => Str::limit($i->title, 90), 's' => ($i->week ? 'Week '.$i->week->number : 'Every week').' · '.$i->day.($i->owner ? ' · '.$i->owner : ''), 'u' => route('calendar.week', $week).'#week', 'x' => false] : null;
            })
            ->merge($weeks->filter(fn ($w) => $hit('week '.$w->number, (string) $w->range_label, (string) $w->phase_label, (string) $w->phase_text, (string) $w->season_text))
                ->map(fn ($w) => ['t' => 'Week '.$w->number.' · '.$w->range_label, 's' => Str::limit(collect([$w->phase_label, $w->season_text])->filter()->implode(' · '), 90), 'u' => route('calendar.week', $w), 'x' => false]));
        $add('Action calendar', $calendar);

        $add('Bulletin', Note::orderByDesc('pinned')->latest()->get()->filter(fn ($n) => $hit($n->title, (string) $n->body))
            ->map(fn ($n) => ['t' => $n->title, 's' => Str::limit((string) $n->body, 90), 'u' => $home.'#tools', 'x' => false]));

        // Only your own sticky notes: everyone else's stay private.
        $add('Your sticky notes', $user->postits()->with('items')->get()
            ->filter(fn ($p) => $hit($p->title, $p->items->pluck('body')->implode(' ')))
            ->map(fn ($p) => ['t' => $p->title, 's' => $p->items->whereNull('done_at')->count().' left', 'u' => $home.'#postit', 'x' => false]));

        if ($user->can('admin')) {
            $add('People', User::where('is_active', true)->orderBy('name')->get()->filter(fn ($p) => $hit($p->name, $p->email))
                ->map(fn ($p) => ['t' => $p->name, 's' => 'Staff page · '.$p->email, 'u' => route('people.show', $p), 'x' => false]));
        }

        $groups[] = ['label' => 'Help', 'total' => 1, 'items' => [['t' => 'Search Help for “'.$q.'”', 's' => 'Field guide: setup, shortcuts and playbooks', 'u' => route('help', ['q' => $q]), 'x' => false]]];

        return response()->json(['q' => $q, 'groups' => $groups]);
    }
}
