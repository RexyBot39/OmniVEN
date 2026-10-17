<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\OutreachEvent;
use App\Models\Publisher;
use App\Models\User;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Publisher outreach: who we're pitching, where each pitch stands, and when to follow up.
 * Everyone works the list; only admins delete a publisher.
 */
class OutreachController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();
        $stages = config('outreach.stages');
        $f = [
            'who' => $request->query('who') === 'mine' ? 'mine' : 'all',
            'stage' => array_key_exists((string) $request->query('stage'), $stages) ? $request->query('stage') : null,
            'type' => array_key_exists((string) $request->query('type'), config('outreach.types')) ? $request->query('type') : null,
            'q' => trim((string) $request->query('q')),
        ];
        $base = Publisher::with(['owner', 'brand'])
            ->when($f['who'] === 'mine', fn ($q) => $q->where('owner_id', $me->id))
            ->when($f['type'], fn ($q) => $q->where('type', $f['type']))
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$f['q'].'%')->orWhere('site', 'like', '%'.$f['q'].'%')));
        $today = $this->today();

        return view('outreach.index', [
            'f' => $f,
            'stages' => $stages,
            'types' => config('outreach.types'),
            'counts' => (clone $base)->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage'),
            'due' => $f['stage'] ? collect() : (clone $base)->whereNotIn('stage', config('outreach.closed'))->whereNotNull('follow_up_on')
                ->where('follow_up_on', '<=', $today->format('Y-m-d'))->orderBy('follow_up_on')->get(),
            'list' => (clone $base)->when($f['stage'], fn ($q) => $q->where('stage', $f['stage']), fn ($q) => $q->whereNotIn('stage', config('outreach.closed')))
                ->orderByRaw('follow_up_on is null')->orderBy('follow_up_on')->orderBy('name')->limit(300)->get(),
            'live' => $f['stage'] ? collect() : (clone $base)->where('stage', 'live')->latest('live_at')->limit(8)->get(),
            'today' => $today,
        ]);
    }

    public function create(): View
    {
        return view('outreach.form', ['publisher' => new Publisher(['type' => 'blog', 'owner_id' => auth()->id(), 'stage' => 'idea'])] + $this->choices());
    }

    public function store(Request $request): RedirectResponse
    {
        $p = Publisher::create($this->validated($request));
        $this->log($p, 'note', 'Added to outreach');
        Activity::log('outreach.added', "Added “{$p->name}” to outreach", $p);

        return redirect()->route('outreach.show', $p)->with('status', 'Added.');
    }

    /** Paste a list: one publisher per line, "Name, https://site" (site optional). Duplicates by site are skipped. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'string', 'max:20000'],
            'type' => ['required', Rule::in(array_keys(config('outreach.types')))],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
        ]);
        $added = 0;
        $skipped = 0;
        foreach (preg_split('/\r?\n/', $data['lines']) as $line) {
            $parts = array_map('trim', preg_split('/\s*[,\t|]\s*/', trim($line), 2));
            if (($parts[0] ?? '') === '') {
                continue;
            }
            [$name, $site] = [mb_substr($parts[0], 0, 160), $parts[1] ?? null];
            if (preg_match('#^https?://#i', $name) && ! $site) {
                [$name, $site] = [preg_replace('/^www\./', '', (string) parse_url($name, PHP_URL_HOST)) ?: $name, $name];
            }
            $site = $site && preg_match('#^https?://\S+$#i', $site) ? mb_substr($site, 0, 500) : null;
            $host = $site ? preg_replace('/^www\./', '', (string) parse_url($site, PHP_URL_HOST)) : null;
            if (($host && Publisher::where('site', 'like', '%'.$host.'%')->exists()) || Publisher::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                $skipped++;

                continue;
            }
            Publisher::create(['name' => $name, 'site' => $site, 'type' => $data['type'], 'brand_id' => $data['brand_id'] ?? null, 'owner_id' => $request->user()->id, 'stage' => 'idea']);
            $added++;
        }
        Activity::log('outreach.added', "Added {$added} publishers to outreach");

        return redirect()->route('outreach.index')->with('status', "Added {$added}.".($skipped ? " Skipped {$skipped} already on the list." : ''));
    }

    public function show(Publisher $publisher): View
    {
        $sales = null;
        if ($publisher->network && $publisher->network_id && Gate::allows('see-revenue')) {
            $rows = DB::table('affiliate_months')->where('network', $publisher->network)->where('publisher_id', $publisher->network_id)
                ->where('month', '>=', $this->today()->startOfMonth()->subMonths(11)->format('Y-m-d'))->get();
            $sales = ['this' => $rows->filter(fn ($r) => substr((string) $r->month, 0, 7) === $this->today()->format('Y-m'))->sum('sale_cents'),
                'year' => $rows->sum('sale_cents'), 'orders' => $rows->sum('orders'), 'comm' => $rows->sum('commission_cents')];
        }

        return view('outreach.show', ['publisher' => $publisher->load(['owner', 'brand', 'events.user']), 'sales' => $sales,
            'codes' => \App\Models\AffiliateAsset::where('kind', 'code')->where('publisher_id', $publisher->id)->orderByDesc('id')->get()] + $this->choices());
    }

    public function edit(Publisher $publisher): View
    {
        return view('outreach.form', ['publisher' => $publisher] + $this->choices());
    }

    public function update(Request $request, Publisher $publisher): RedirectResponse
    {
        $before = $publisher->stage;
        $publisher->fill($this->validated($request));
        if ($publisher->stage === 'live' && ! $publisher->live_at) {
            $publisher->live_at = now();
        }
        $publisher->save();
        if ($before !== $publisher->stage) {
            $this->log($publisher, 'stage', config('outreach.stages.'.$before).' → '.$publisher->stageLabel());
        }

        return redirect()->route('outreach.show', $publisher)->with('status', 'Saved.');
    }

    /** One-click steps: pitched, followed up, they replied, went live, declined, no reply, reopen, or a note. */
    public function act(Request $request, Publisher $publisher): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['pitched', 'nudged', 'replied', 'live', 'declined', 'no_reply', 'reopen', 'note'])],
            'body' => ['nullable', 'string', 'max:1000'],
            'result_url' => ['nullable', 'required_if:action,live', 'url:http,https', 'max:1024'],
        ], ['result_url.required_if' => 'Paste the link to the article, review or post.']);
        $today = $this->today();
        $body = trim((string) ($data['body'] ?? '')) ?: null;
        $p = $publisher;

        switch ($data['action']) {
            case 'pitched':
            case 'nudged':
                $p->fill(['last_contacted_on' => $today, 'follow_up_on' => $today->addDays(config('outreach.follow_up_days'))]);
                if (in_array($p->stage, ['idea', 'no_reply'], true)) {
                    $p->stage = 'contacted';
                }
                break;
            case 'replied':
                $p->fill(['stage' => in_array($p->stage, ['idea', 'contacted', 'no_reply'], true) ? 'replied' : $p->stage, 'follow_up_on' => $today->addDays(config('outreach.reply_follow_up_days'))]);
                break;
            case 'live':
                $p->fill(['stage' => 'live', 'result_url' => $data['result_url'], 'live_at' => now(), 'follow_up_on' => null]);
                $body = $body ?? $data['result_url'];
                break;
            case 'declined':
            case 'no_reply':
                $p->fill(['stage' => $data['action'], 'follow_up_on' => null]);
                break;
            case 'reopen':
                $p->fill(['stage' => 'contacted', 'follow_up_on' => $today]);
                break;
        }
        $p->save();
        $this->log($p, match ($data['action']) {
            'declined', 'no_reply', 'reopen' => 'stage',
            default => $data['action'],
        }, $body ?? (in_array($data['action'], ['declined', 'no_reply', 'reopen'], true) ? 'Now: '.$p->stageLabel() : null));
        if ($data['action'] === 'live') {
            Activity::log('outreach.live', "“{$p->name}” went live", $p);
        }

        return back()->with('status', match ($data['action']) {
            'pitched', 'nudged' => 'Logged. Follow up on '.$p->follow_up_on->format('D M j').'.',
            'replied' => 'Logged. Answer them by '.$p->follow_up_on->format('D M j').'.',
            'live' => 'Marked live. 🎉',
            'note' => 'Note added.',
            default => 'Now: '.$p->stageLabel().'.',
        });
    }

    public function destroy(Publisher $publisher): RedirectResponse
    {
        Gate::authorize('admin');
        Activity::log('outreach.deleted', "Removed “{$publisher->name}” from outreach");
        $publisher->delete();

        return redirect()->route('outreach.index')->with('status', 'Removed.');
    }

    private function log(Publisher $p, string $kind, ?string $body): void
    {
        OutreachEvent::create(['publisher_id' => $p->id, 'user_id' => auth()->id(), 'kind' => $kind, 'body' => $body ? mb_substr($body, 0, 1000) : null]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'site' => ['nullable', 'url:http,https', 'max:500'],
            'type' => ['required', Rule::in(array_keys(config('outreach.types')))],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'stage' => ['required', Rule::in(array_keys(config('outreach.stages')))],
            'contact' => ['nullable', 'string', 'max:200'],
            'network' => ['nullable', Rule::in(array_keys(config('outreach.networks')))],
            'network_id' => ['nullable', 'string', 'max:64'],
            'follow_up_on' => ['nullable', 'date'],
            'result_url' => ['nullable', 'url:http,https', 'max:1024'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
        if (in_array($data['stage'], config('outreach.closed'), true)) {
            $data['follow_up_on'] = null;
        }

        return $data;
    }

    private function choices(): array
    {
        return [
            'types' => config('outreach.types'),
            'stages' => config('outreach.stages'),
            'networks' => config('outreach.networks'),
            'brands' => Brand::orderBy('sort')->get(),
            'people' => User::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }
}
