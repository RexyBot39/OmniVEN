<?php

namespace App\Support\Assistant;

use App\Models\Account;
use App\Models\AiCheck;
use App\Models\AiQuestion;
use App\Models\Brand;
use App\Models\CalendarCheck;
use App\Models\CalendarWeek;
use App\Models\Campaign;
use App\Models\OutreachEvent;
use App\Models\Publisher;
use App\Models\RedditThread;
use App\Models\Review;
use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Models\SitePage;
use App\Models\Task;
use App\Models\User;
use App\Models\UtmLink;
use App\Support\Email\Klaviyo;
use App\Support\Revenue\Report;
use App\Support\Revenue\Revenue;
use App\Support\Seo\ContentGaps;
use App\Support\Seo\SearchConsole;
use App\Support\Seo\SiteCheck;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The read-only lookups the assistant can make. Each returns compact facts from one part of OmniVEN, for
 * the person asking: revenue, affiliate and email numbers only for people who can see revenue, and never
 * sticky notes, Slack messages, logins, keys or anything about customers.
 */
class Tools
{
    public function __construct(private User $user) {}

    /** Tool definitions in the OpenAI Responses API format. */
    public function definitions(): array
    {
        $brand = ['type' => 'string', 'description' => 'Brand code or name, e.g. 39dg, ocusafe, ocusleep. Omit for all brands.'];
        $defs = [
            ['overview', 'Start here. Brands, team, open and overdue work, routines due, running campaigns, and which data sources are connected.', []],
            ['tasks', 'Tasks, newest first: title, assignee, brand, due date, Hotlist, done date, result link, campaign.', [
                'status' => ['type' => 'string', 'enum' => ['open', 'overdue', 'done', 'all'], 'description' => 'Default open.'],
                'person' => ['type' => 'string', 'description' => 'First name or email.'],
                'brand' => $brand,
                'done_within_days' => ['type' => 'integer', 'description' => 'For done tasks: finished in the last N days (default 30).'],
                'limit' => ['type' => 'integer', 'description' => 'Max rows, default 80, up to 200.'],
            ]],
            ['routines', 'Recurring routines with cadence, assignee, brands, whether done this period, and how many of the last 8 periods were done.', []],
            ['people', 'Each active person: open, overdue and Hotlist tasks, tasks finished in the last 30 days, routines assigned.', []],
            ['campaigns', 'Campaigns running, upcoming and recently ended, with dates, goals, task progress and tracked links.', []],
            ['search', 'Google Search Console for a brand: last 28 days vs the 28 before, clicks, impressions, CTR, position, branded share, rising and falling searches and pages.', ['brand' => $brand]],
            ['content_gaps', 'Open content gaps: searches and AI questions that need a page, a better page or a better title, most promising first.', ['brand' => $brand]],
            ['reviews', 'Ratings per review platform and recent reviews (stars, date, short excerpt), including low ones.', []],
            ['ai_answers', 'Monthly AI-assistant visibility checks: how often ChatGPT, Perplexity and others mention or recommend us, per question and month.', []],
            ['outreach', 'Publisher outreach pipeline: counts by stage, follow-ups due, recently live placements.', []],
            ['site_check', 'Structured data issues on key pages and product feed problems.', []],
            ['coverage', 'Which brands are missing which social, ads, shopping, search, email and review accounts, and accounts needing attention.', []],
            ['reddit', 'Reddit threads from the past week worth a reply, scored.', []],
            ['calendar_week', 'This week of the Q4 Action Calendar: phase, items by day, and which are checked off.', []],
            ['links', 'Campaign (UTM) links made in the last N days, grouped by campaign and source.', ['days' => ['type' => 'integer', 'description' => 'Default 90.']]],
        ];
        if (Revenue::canSee($this->user)) {
            $period = ['type' => 'string', 'description' => 'mtd, last30, last90, ytd, or a month as YYYY-MM. Default mtd.'];
            $defs[] = ['revenue', 'GA4 revenue for a period: revenue, orders, conversion rate, average order vs the same dates last year, by brand and by channel, plus pace to this month’s goal.', ['period' => $period, 'brand' => $brand]];
            $defs[] = ['revenue_trend', 'Monthly GA4 revenue, orders and sessions for the last 13 months, per brand, with the same month last year where known.', ['brand' => $brand]];
            $defs[] = ['affiliates', 'CJ and Awin affiliate sales, orders, commission and top publishers for a month.', ['month' => ['type' => 'string', 'description' => 'YYYY-MM, default this month.']]];
            $defs[] = ['email', 'Klaviyo email and SMS for the last 30 days: campaigns, flows, buyers, open, click, unsubscribe and spam rates, list size.', []];
        }

        return array_map(fn ($d) => ['type' => 'function', 'name' => $d[0], 'description' => $d[1],
            'parameters' => ['type' => 'object', 'properties' => (object) $d[2], 'additionalProperties' => false]], $defs);
    }

    /** Run one tool. Errors come back as data so the model can say what's missing. */
    public function call(string $name, array $args): array
    {
        $method = 'tool'.Str::studly($name);
        if (! method_exists($this, $method) || ! collect($this->definitions())->contains('name', $name)) {
            return ['error' => "No tool named {$name} for this person."];
        }
        try {
            return $this->{$method}($args);
        } catch (Throwable $e) {
            report($e);

            return ['error' => 'That lookup failed inside OmniVEN ('.class_basename($e).').'];
        }
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    private function brand(?string $q): ?Brand
    {
        $q = trim((string) $q);
        if ($q === '' || in_array(strtolower($q), ['all', 'any'], true)) {
            return null;
        }

        return Brand::where('slug', strtolower($q))->first() ?? Brand::whereRaw('lower(name) = ?', [strtolower($q)])->first()
            ?? Brand::whereRaw('lower(name) like ?', ['%'.strtolower($q).'%'])->first();
    }

    private function d(?\DateTimeInterface $d): ?string
    {
        return $d?->format('Y-m-d');
    }

    private function toolOverview(array $a): array
    {
        $today = $this->today();
        $open = Task::whereNull('completed_at');
        $keys = collect(array_keys(config('omniven.cadences')))->mapWithKeys(fn ($c) => [$c => Routine::periodKey($c)]);
        $routines = Routine::with('latestCompletion')->get();

        return [
            'today' => $today->format('l Y-m-d').' (US Eastern)',
            'company' => '39DollarGlasses and sister brands; OmniVEN is the marketing team’s control center.',
            'brands' => Brand::orderBy('sort')->get()->map(fn ($b) => ['code' => $b->slug, 'name' => $b->name, 'site' => $b->site, 'focus' => $b->focus, 'channels' => $b->channels])->all(),
            'team' => User::where('is_active', true)->orderBy('name')->get()->map(fn ($u) => $u->name.($u->is_admin ? ' (admin)' : ''))->all(),
            'tasks' => ['open' => (clone $open)->count(), 'overdue' => (clone $open)->whereNotNull('due_on')->where('due_on', '<', $today->format('Y-m-d'))->count(),
                'hotlist' => (clone $open)->where('hot', true)->count(), 'done_last_7_days' => Task::where('completed_at', '>=', $today->subDays(7))->count()],
            'routines' => ['total' => $routines->count(), 'done_this_period' => $routines->filter->isDone()->count(),
                'not_done' => $routines->reject->isDone()->map(fn ($r) => $r->title.' ('.$r->cadence.')')->take(25)->values()->all()],
            'campaigns_running' => Campaign::during($today, $today)->pluck('name')->all(),
            'connected' => [
                'ga4_revenue' => Revenue::configured(), 'search_console' => SearchConsole::configured(), 'klaviyo' => Klaviyo::configured(),
                'affiliates' => \App\Support\Affiliate\Affiliates::configured(), 'reviews' => \App\Support\Reviews\ReviewSync::anyConfigured(),
                'reddit' => \App\Support\Reddit::configured(), 'product_feeds' => config('site_check.feeds') !== [],
            ],
            'asker' => ['name' => $this->user->name, 'admin' => (bool) $this->user->is_admin, 'can_see_revenue' => Revenue::canSee($this->user)],
        ];
    }

    private function toolTasks(array $a): array
    {
        $today = $this->today();
        $status = $a['status'] ?? 'open';
        $q = Task::with(['assignee', 'completer', 'brand', 'campaign']);
        match ($status) {
            'done' => $q->whereNotNull('completed_at')->where('completed_at', '>=', $today->subDays(max(1, min(365, (int) ($a['done_within_days'] ?? 30)))))->latest('completed_at'),
            'overdue' => $q->whereNull('completed_at')->whereNotNull('due_on')->where('due_on', '<', $today->format('Y-m-d'))->orderBy('due_on'),
            'all' => $q->latest('id'),
            default => $q->whereNull('completed_at')->orderByRaw('due_on is null')->orderBy('due_on'),
        };
        if ($p = trim((string) ($a['person'] ?? ''))) {
            $ids = User::whereRaw('lower(name) like ? or lower(email) like ?', ['%'.strtolower($p).'%', '%'.strtolower($p).'%'])->pluck('id');
            $q->whereIn('assignee_id', $ids);
        }
        if ($b = $this->brand($a['brand'] ?? null)) {
            $q->where('brand_id', $b->id);
        }
        $rows = $q->limit(max(1, min(200, (int) ($a['limit'] ?? 80))))->get();

        return ['count' => $rows->count(), 'tasks' => $rows->map(fn ($t) => array_filter([
            'title' => $t->title, 'details' => $t->details ? Str::limit($t->details, 240) : null, 'assignee' => $t->assignee?->name, 'brand' => $t->brand?->name,
            'due' => $this->d($t->due_on), 'hotlist' => $t->hot ?: null, 'done' => $t->completed_at ? $this->d($t->completed_at).' by '.($t->completer?->firstName() ?? '?') : null,
            'result' => $t->outcome || $t->outcome_url ? trim($t->outcome.' '.$t->outcome_url) : null, 'campaign' => $t->campaign?->name,
        ], fn ($v) => $v !== null))->all()];
    }

    private function toolRoutines(array $a): array
    {
        $routines = Routine::with(['brands', 'assignee', 'latestCompletion'])->orderBy('cadence')->orderBy('sort')->get();
        $done = RoutineCompletion::selectRaw('routine_id, period_key')->get()->groupBy('routine_id')->map(fn ($c) => $c->pluck('period_key')->flip());

        return ['routines' => $routines->map(function ($r) use ($done) {
            $keys = collect(range(1, 8))->map(fn ($i) => Routine::periodKey($r->cadence, match ($r->cadence) {
                'daily' => now()->subDays($i), 'weekly' => now()->subWeeks($i), 'monthly' => now()->subMonths($i), default => now()->subMonths($i * 3),
            }));
            $hits = $keys->filter(fn ($k) => isset($done[$r->id][$k]))->count();

            return ['title' => $r->title, 'cadence' => $r->cadence, 'assignee' => $r->assignee?->name ?? 'whole team', 'brands' => implode(', ', $r->brandLabels()),
                'done_this_period' => $r->isDone(), 'last_done' => $this->d($r->latestCompletion?->completed_at), 'done_in_last_8_periods' => $hits];
        })->all()];
    }

    private function toolPeople(array $a): array
    {
        $today = $this->today()->format('Y-m-d');

        return ['people' => User::where('is_active', true)->orderBy('name')->get()->map(fn ($u) => [
            'name' => $u->name, 'admin' => (bool) $u->is_admin,
            'open' => Task::where('assignee_id', $u->id)->whereNull('completed_at')->count(),
            'overdue' => Task::where('assignee_id', $u->id)->whereNull('completed_at')->whereNotNull('due_on')->where('due_on', '<', $today)->count(),
            'hotlist' => Task::where('assignee_id', $u->id)->whereNull('completed_at')->where('hot', true)->count(),
            'finished_last_30_days' => Task::where('completed_by', $u->id)->where('completed_at', '>=', now()->subDays(30))->count(),
            'routines' => Routine::where('assignee_id', $u->id)->count(),
        ])->all()];
    }

    private function toolCampaigns(array $a): array
    {
        $today = $this->today();

        return ['campaigns' => Campaign::with('brand')->withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->whereNotNull('completed_at'), 'links'])
            ->where('ends_on', '>=', $today->subDays(60)->format('Y-m-d'))->orderBy('starts_on')->get()->map(fn ($c) => [
                'name' => $c->name, 'state' => $c->state(), 'dates' => $c->starts_on->format('Y-m-d').' to '.$c->ends_on->format('Y-m-d'), 'brand' => $c->brand?->name,
                'goal' => $c->goal, 'tasks_done' => $c->done_count.' of '.$c->tasks_count, 'tracked_links' => $c->links_count, 'link_tag' => $c->tag,
            ])->all()];
    }

    private function toolSearch(array $a): array
    {
        if (! SearchConsole::configured()) {
            return ['error' => 'Search Console isn’t connected yet (SEARCH_CONSOLE_SITES).'];
        }
        $brands = SearchConsole::brands();
        $b = $this->brand($a['brand'] ?? null);
        $slug = $b && $brands->has($b->slug) ? $b->slug : $brands->keys()->first();
        $s = $slug ? SearchConsole::summary($slug) : null;
        if (! $s) {
            return ['error' => 'No Search Console numbers yet for '.($slug ?? 'any brand').'. '.(SearchConsole::status()['error'] ?? 'They load on the first visit to Search.'), 'brands_connected' => $brands->keys()->all()];
        }
        unset($s['gap_rows'], $s['daily']);

        return ['brand' => $slug, 'brands_connected' => $brands->keys()->all()] + $s;
    }

    private function toolContentGaps(array $a): array
    {
        $b = $this->brand($a['brand'] ?? null);

        return ['gaps' => ContentGaps::open()->filter(fn ($g) => ! $b || $g['brand']?->is($b))->take(30)->map(fn ($g) => [
            'kind' => ContentGaps::KINDS[$g['kind']], 'brand' => $g['brand']?->name, 'query' => $g['query'], 'page' => $g['page'], 'numbers' => $g['stats'], 'why' => $g['why'],
        ])->values()->all()];
    }

    private function toolReviews(array $a): array
    {
        $ratings = collect(config('reviews.platforms'))->map(fn ($p, $key) => ['platform' => $p['label'],
            'latest' => DB::table('review_snapshots')->where('platform', $key)->orderByDesc('day')->first(['day', 'rating', 'review_count'])])->filter(fn ($r) => $r['latest'])->values();
        $recent = Review::where('reviewed_at', '>=', now()->subDays(60))->latest('reviewed_at')->limit(40)->get();

        return ['ratings' => $ratings->all(),
            'last_60_days' => ['count' => $recent->count(), 'average_stars' => $recent->avg('stars') ? round($recent->avg('stars'), 2) : null, 'two_stars_or_less' => $recent->where('stars', '<=', 2)->count()],
            'recent' => $recent->map(fn ($r) => ['platform' => $r->platform, 'stars' => $r->stars, 'date' => $this->d($r->reviewed_at), 'excerpt' => Str::limit((string) $r->excerpt, 200)])->all()];
    }

    private function toolAiAnswers(array $a): array
    {
        $checks = AiCheck::where('month', '>=', $this->today()->startOfMonth()->subMonths(5)->format('Y-m-d'))->get(['ai_question_id', 'engine', 'month', 'result']);
        $byMonth = $checks->groupBy(fn ($c) => $c->month->format('Y-m'))->map(fn ($cs) => ['checks' => $cs->count(),
            'mentioned_or_recommended' => round($cs->whereIn('result', ['mentioned', 'recommended'])->count() / max(1, $cs->count()) * 100).'%',
            'recommended' => round($cs->where('result', 'recommended')->count() / max(1, $cs->count()) * 100).'%']);
        $latest = $checks->max('month');

        return ['by_month' => $byMonth->sortKeys()->all(), 'latest_month_per_question' => AiQuestion::with('brand')->where('active', true)->get()->map(fn ($q) => [
            'question' => $q->question, 'brand' => $q->brand?->name,
            'results' => $checks->where('ai_question_id', $q->id)->filter(fn ($c) => $latest && $c->month->eq($latest))->mapWithKeys(fn ($c) => [$c->engine => $c->result])->all(),
        ])->all()];
    }

    private function toolOutreach(array $a): array
    {
        $today = $this->today()->format('Y-m-d');

        return ['by_stage' => Publisher::selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage')->all(),
            'follow_ups_due' => Publisher::with('owner')->whereNotIn('stage', config('outreach.closed'))->whereNotNull('follow_up_on')->where('follow_up_on', '<=', $today)
                ->orderBy('follow_up_on')->limit(30)->get()->map(fn ($p) => ['name' => $p->name, 'stage' => $p->stageLabel(), 'due' => $this->d($p->follow_up_on), 'owner' => $p->owner?->name])->all(),
            'live_last_90_days' => Publisher::where('stage', 'live')->where('live_at', '>=', now()->subDays(90))->get(['name', 'result_url', 'live_at'])->map(fn ($p) => ['name' => $p->name, 'link' => $p->result_url, 'live' => $this->d($p->live_at)])->all(),
            'pitched_last_30_days' => OutreachEvent::where('kind', 'pitched')->where('created_at', '>=', now()->subDays(30))->distinct()->count('publisher_id')];
    }

    private function toolSiteCheck(array $a): array
    {
        return ['pages' => SitePage::with('brand')->get()->map(fn ($p) => ['url' => $p->url, 'kind' => $p->kind, 'brand' => $p->brand?->name, 'status' => $p->status,
            'issues' => collect($p->issues ?? [])->map(fn ($i) => $i[0].': '.$i[1])->all(), 'error' => $p->error])->all(),
            'feeds' => collect(SiteCheck::feedReports())->map(fn ($f) => $f['report'] ? ['items' => $f['report']['items'] ?? null, 'error' => $f['report']['error'] ?? null,
                'issues' => collect($f['report']['issues'] ?? [])->map(fn ($i) => $i['level'].': '.$i['label'].' × '.$i['count'])->all()] : 'not checked yet')->all()];
    }

    private function toolCoverage(array $a): array
    {
        $platforms = collect(config('omniven.platforms'))->except('other');
        $accounts = Account::all()->groupBy('brand_id')->map(fn ($as) => $as->pluck('platform')->flip());
        $skips = DB::table('brand_platform_skips')->get()->groupBy('brand_id')->map(fn ($r) => $r->pluck('platform')->flip());

        return ['brands' => Brand::whereNull('accounts_from_id')->orderBy('sort')->get()->map(function ($b) use ($platforms, $accounts, $skips) {
            $has = ($accounts[''] ?? collect())->keys()->merge(($accounts[$b->id] ?? collect())->keys())->unique();
            $missing = $platforms->keys()->reject(fn ($k) => $has->contains($k) || isset($skips[$b->id][$k]));

            return ['brand' => $b->name, 'has' => $has->map(fn ($k) => $platforms[$k]['label'] ?? $k)->values()->all(), 'missing' => $missing->map(fn ($k) => $platforms[$k]['label'])->values()->all()];
        })->all(),
            'accounts_not_verified_90_days' => Account::with('brand')->get()->filter->needsVerify()->map->title()->values()->take(40)->all()];
    }

    private function toolReddit(array $a): array
    {
        return ['threads' => RedditThread::where('status', 'new')->where('posted_at', '>=', now()->subDays(7))->orderByDesc('score')->limit(20)->get()
            ->map(fn ($t) => ['title' => $t->title, 'subreddit' => $t->subreddit, 'kind' => RedditThread::KINDS[$t->kind] ?? $t->kind, 'score' => $t->score, 'comments' => $t->comments, 'url' => $t->url])->all()];
    }

    private function toolCalendarWeek(array $a): array
    {
        $w = CalendarWeek::current();
        if (! $w) {
            return ['error' => 'The action calendar has no weeks.'];
        }
        $checks = CalendarCheck::where('calendar_week_id', $w->id)->pluck('calendar_item_id')->flip();

        return ['week' => $w->number, 'dates' => $w->range_label, 'phase' => $w->phase_label, 'phase_text' => $w->phase_text, 'season' => $w->season_text, 'sunday_note' => $w->notes,
            'items' => $w->items()->map(fn ($i) => ['day' => $i->day, 'title' => $i->title, 'owner' => $i->owner, 'brand' => $i->brand_slug ?? 'all', 'done' => isset($checks[$i->id])])->all()];
    }

    private function toolLinks(array $a): array
    {
        $days = max(1, min(365, (int) ($a['days'] ?? 90)));

        return ['since' => $this->today()->subDays($days)->format('Y-m-d'), 'by_campaign' => UtmLink::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('campaign, source, count(*) as n')->groupBy('campaign', 'source')->orderBy('campaign')->get()->groupBy('campaign')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->source => (int) $r->n]))->all()];
    }

    private function toolRevenue(array $a): array
    {
        if (! Revenue::configured()) {
            return ['error' => 'GA4 isn’t connected yet.'];
        }
        $p = (string) ($a['period'] ?? 'mtd');
        if (preg_match('/^(\d{4})-(\d{2})$/', $p, $m)) {
            $from = CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, config('app.timezone'));
            $to = $from->endOfMonth()->startOfDay()->min(Report::today());
        } else {
            [$from, $to] = Report::range(array_key_exists($p, Report::PERIODS) ? $p : 'mtd');
        }
        $b = $this->brand($a['brand'] ?? null);
        $fig = fn ($f) => ['revenue' => Report::money($f['cents']), 'orders' => $f['orders'], 'sessions' => $f['sessions'], 'conversion' => Report::pct($f['conv'], 2),
            'avg_order' => Report::money($f['aov_cents'] !== null ? (int) round($f['aov_cents']) : null),
            'vs_last_year' => array_map(fn ($v) => $v === null ? 'no data' : sprintf('%+.1f', $v), $f['vs'])];

        return ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'note' => 'vs_last_year: percent change, conversion in points. GA4 runs a little below the store’s own totals.',
            'total' => $fig(Report::figures($from, $to, $b?->id)),
            'by_brand' => $b ? null : Revenue::brands()->mapWithKeys(fn ($br) => [$br->name => $fig(Report::figures($from, $to, $br->id))])->all(),
            'by_channel' => collect(Report::channels($from, $to, $b?->id)['rows'])->map(fn ($c) => ['channel' => $c['label'], 'revenue' => Report::money($c['cents']),
                'share' => Report::pct($c['share'], 0), 'orders' => $c['orders'], 'vs_last_year' => $c['vs'] === null ? 'no data' : sprintf('%+.1f%%', $c['vs'])])->all(),
            'pace_this_month' => Report::pace(Report::figures(Report::today()->startOfMonth(), Report::today(), $b?->id)['cents'], $b?->id)];
    }

    private function toolRevenueTrend(array $a): array
    {
        $b = $this->brand($a['brand'] ?? null);
        $from = $this->today()->startOfMonth()->subMonths(24);
        $rows = DB::table('revenue_days')->where('day', '>=', $from->format('Y-m-d'))->when($b, fn ($q) => $q->where('brand_id', $b->id))->get(['day', 'brand_id', 'sessions', 'orders', 'revenue_cents']);
        $names = Brand::pluck('name', 'id');
        $months = $rows->groupBy(fn ($r) => $names[$r->brand_id] ?? 'Unknown')->map(fn ($rs) => $rs->groupBy(fn ($r) => substr((string) $r->day, 0, 7))->sortKeys()
            ->map(fn ($m) => ['revenue' => Report::money((int) $m->sum('revenue_cents')), 'orders' => (int) $m->sum('orders'), 'sessions' => (int) $m->sum('sessions')]));

        return ['note' => 'Up to 24 months so the same month last year is included. The current month is partial.', 'by_brand' => $months->map->all()->all()];
    }

    private function toolAffiliates(array $a): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) ($a['month'] ?? '')) ? $a['month'].'-01' : $this->today()->startOfMonth()->format('Y-m-d');
        $q = DB::table('affiliate_months')->where('month', $month);

        return ['month' => substr($month, 0, 7), 'by_network' => (clone $q)->selectRaw('network, sum(sale_cents) as s, sum(orders) as o, sum(commission_cents) as c, sum(pending_cents) as p')->groupBy('network')->get()
            ->map(fn ($r) => ['network' => $r->network, 'sales' => Report::money((int) $r->s), 'orders' => (int) $r->o, 'commission' => Report::money((int) $r->c), 'pending' => Report::money((int) $r->p)])->all(),
            'top_publishers' => (clone $q)->orderByDesc('sale_cents')->limit(15)->get()->map(fn ($r) => ['publisher' => $r->publisher_name, 'network' => $r->network,
                'sales' => Report::money((int) $r->sale_cents), 'orders' => (int) $r->orders, 'commission' => Report::money((int) $r->commission_cents)])->all()];
    }

    private function toolEmail(array $a): array
    {
        if (! Klaviyo::configured()) {
            return ['error' => 'Klaviyo isn’t connected yet.'];
        }
        $r = Klaviyo::report();
        if (! $r) {
            return ['error' => 'No Klaviyo numbers yet. '.(Klaviyo::status()['error'] ?? 'They load on the first visit to Email & SMS.')];
        }
        $rate = fn ($s) => ['recipients' => $s['recipients'], 'open_rate' => $s['delivered'] ? round($s['opens'] / $s['delivered'] * 100, 1).'%' : null,
            'click_rate' => $s['delivered'] ? round($s['clicks'] / $s['delivered'] * 100, 2).'%' : null, 'unsub_rate' => $s['delivered'] ? round($s['unsubs'] / $s['delivered'] * 100, 2).'%' : null,
            'buyers' => $s['orders'], 'revenue' => $s['value_cents'] !== null ? Report::money($s['value_cents']) : 'not tracked'];

        return ['window' => 'last 30 days', 'order_values_tracked' => $r['values_ok'], 'list_size' => $r['profiles'],
            'campaigns' => collect($r['campaigns'])->take(25)->map(fn ($c) => ['name' => $c['name'], 'sent' => $c['sent'], 'channel' => $c['channel']] + $rate($c))->all(),
            'flows' => collect($r['flows'])->map(fn ($f) => ['name' => $f['name']] + $rate($f))->all(), 'flow_totals' => $rate($r['flow_totals'])];
    }
}
