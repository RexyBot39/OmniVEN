<?php

namespace App\Support\Opportunities;

use App\Models\Opportunity;
use App\Models\OpportunityTerm;
use App\Support\AccessNotifier;
use App\Support\Slack;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Finds government bids for safety glasses once a day: every search phrase (and the federal ophthalmic and
 * safety-equipment codes) against SAM.gov, state and local portals, and DLA DIBBS, through Tango. Each hit is
 * scored for how likely it is to be ours, new ones are saved for the team to pursue or pass, and the good new
 * ones go to Slack. A source that fails or isn't in the Tango plan is skipped and reported to admins.
 */
class OpportunityFinder
{
    private const SAM_SHAPE = 'opportunity_id,title,description,solicitation_number,response_deadline,first_notice_date,active,naics_code,psc_code,set_aside,sam_url,office(*),place_of_performance(*)';

    private const SLED_SHAPE = 'opportunity_id,solicitation_number,solicitation_type,title,state,jurisdiction,agency,status,posted_date,response_deadline,source_url';

    private const RFQ_SHAPE = 'uuid,solicitation,solicitation_formatted,nsn,part_number,nomenclature,quantity,unit_of_issue,issue_date,return_by_date,set_aside,is_open,document_url';

    private const RFP_SHAPE = 'uuid,solicitation,nsn,part_number,nomenclature,issued_date,closes_date,is_open';

    /** The search phrases, starting from the config list the first time. */
    public static function terms(): array
    {
        if (! OpportunityTerm::query()->exists() && ! Cache::get('opps:terms-seeded')) {
            foreach (config('tango.terms') as $t) {
                OpportunityTerm::firstOrCreate(['term' => mb_strtolower($t)]);
            }
            Cache::forever('opps:terms-seeded', 1);
        }

        return OpportunityTerm::orderBy('term')->pluck('term')->all();
    }

    public static function status(): array
    {
        $t = Cache::get('opps:last');

        return ['last' => is_numeric($t) ? CarbonImmutable::createFromTimestamp((int) $t, config('app.timezone')) : null, 'errors' => Cache::get('opps:errors', []), 'running' => Cache::has('opps:busy')];
    }

    public static function refreshIfStale(bool $force = false): void
    {
        if (! Tango::configured()) {
            return;
        }
        $last = Cache::get('opps:last');
        if (! $force && is_numeric($last) && now()->timestamp - (int) $last < (int) config('tango.refresh_hours') * 3600) {
            return;
        }
        if (! Cache::add('opps:busy', 1, 900)) {
            return;
        }
        dispatch(function () {
            try {
                self::run();
            } finally {
                Cache::forget('opps:busy');
            }
        })->afterResponse();
    }

    /** One search pass. Returns how many new opportunities were saved and any source errors. */
    public static function run(): array
    {
        $since = Cache::get('opps:since');
        $since = is_string($since) ? CarbonImmutable::parse($since)->subDays(2) : now()->subDays((int) config('tango.lookback_days'))->toImmutable();
        $day = $since->format('Y-m-d');
        $terms = self::terms();
        $hits = [];
        $errors = [];

        $sources = [
            'sam' => function () use ($terms, $day, &$hits) {
                $base = ['active' => 'true', 'first_notice_date_after' => $day, 'limit' => 100, 'shape' => self::SAM_SHAPE];
                foreach ($terms as $t) {
                    self::collect($hits, 'sam', Tango::list('/api/opportunities/', $base + ['search' => $t]), $t);
                }
                foreach (config('tango.psc') as $code) {
                    self::collect($hits, 'sam', Tango::list('/api/opportunities/', $base + ['psc' => $code]), 'PSC '.$code);
                }
                foreach (config('tango.naics') as $code) {
                    self::collect($hits, 'sam', Tango::list('/api/opportunities/', $base + ['naics' => $code]), 'NAICS '.$code);
                }
            },
            'sled' => function () use ($terms, $day, &$hits) {
                foreach ($terms as $t) {
                    self::collect($hits, 'sled', Tango::list('/api/sled/opportunities/', ['search' => $t, 'first_seen_after' => $day, 'limit' => 100, 'shape' => self::SLED_SHAPE]), $t);
                }
            },
            'dibbs' => function () use ($terms, $day, &$hits) {
                foreach (array_unique(array_merge(config('tango.dibbs_terms'), $terms)) as $t) {
                    self::collect($hits, 'dibbs_rfq', Tango::list('/api/dibbs/rfqs/', ['search' => $t, 'open' => 'true', 'issue_date_after' => $day, 'limit' => 100, 'shape' => self::RFQ_SHAPE]), $t);
                    self::collect($hits, 'dibbs_rfp', Tango::list('/api/dibbs/rfps/', ['search' => $t, 'open' => 'true', 'issued_date_after' => $day, 'limit' => 100, 'shape' => self::RFP_SHAPE]), $t);
                }
            },
        ];
        foreach ($sources as $key => $search) {
            if (! (config("tango.sources.$key") ?? false)) {
                continue;
            }
            try {
                $search();
            } catch (Throwable $e) {
                $errors[$key] = $e instanceof RuntimeException ? $e->getMessage() : 'Searching failed ('.class_basename($e).').';
                Log::warning('Tango search failed', ['source' => $key, 'error' => $errors[$key]]);
            }
        }

        $new = [];
        foreach ($hits as $h) {
            $row = Opportunity::where('source', $h['source'])->where('external_id', $h['external_id'])->first();
            $fields = array_diff_key($h, ['terms' => 1]) + ['matched' => mb_substr(implode(', ', array_unique($h['terms'])), 0, 300), 'last_seen_at' => now()];
            if ($row) {
                $row->update($fields);
            } else {
                $new[] = Opportunity::create($fields + ['status' => 'new', 'first_seen_at' => now()]);
            }
        }
        Cache::forever('opps:last', now()->timestamp);
        Cache::forever('opps:errors', $errors);
        // Only move the window forward when every source answered, so a failed day is searched again.
        if (! $errors) {
            Cache::forever('opps:since', now()->format('Y-m-d'));
        }
        self::digest(array_filter($new, fn ($o) => $o->score >= (int) config('tango.good_score')));

        return ['new' => count($new), 'found' => count($hits), 'errors' => $errors];
    }

    /** Merge one search's results into $hits, keyed by source and id, remembering which searches found each. */
    private static function collect(array &$hits, string $source, array $rows, string $term): void
    {
        foreach ($rows as $r) {
            $h = self::normalize($source, $r);
            if (! $h) {
                continue;
            }
            $key = $source.':'.$h['external_id'];
            $terms = array_merge($hits[$key]['terms'] ?? [], [$term]);
            // Searches return the same bid with more or less detail (a code search can carry less): keep every field any of them had.
            if (isset($hits[$key])) {
                foreach ($h as $f => $v) {
                    if ($v === null && $hits[$key][$f] !== null) {
                        $h[$f] = $hits[$key][$f];
                    }
                }
            }
            $hits[$key] = $h + ['terms' => $terms];
            $hits[$key]['score'] = self::score($hits[$key]['title'], (string) $hits[$key]['summary'], $terms);
        }
    }

    public static function normalize(string $source, array $r): ?array
    {
        $date = fn ($v) => is_string($v) && $v !== '' ? CarbonImmutable::parse($v) : null;
        $clip = fn ($v, $n) => ($v = trim(preg_replace('/\s+/', ' ', strip_tags((string) $v)))) === '' ? null : mb_substr($v, 0, $n);

        switch ($source) {
            case 'sam':
                if (empty($r['opportunity_id']) || empty($r['title'])) {
                    return null;
                }
                $office = is_array($r['office'] ?? null) ? $r['office'] : [];
                $pop = is_array($r['place_of_performance'] ?? null) ? $r['place_of_performance'] : [];
                $aside = $r['set_aside'] ?? null;

                return [
                    'source' => 'sam', 'external_id' => (string) $r['opportunity_id'], 'title' => $clip($r['title'], 500),
                    'agency' => $clip(implode(' · ', array_filter([$office['agency'] ?? null, $office['name'] ?? null], fn ($v) => is_string($v) && $v !== '')), 300),
                    'solicitation_number' => $clip($r['solicitation_number'] ?? null, 120), 'kind' => null,
                    'set_aside' => $clip(is_array($aside) ? ($aside['description'] ?? $aside['code'] ?? null) : $aside, 200),
                    'codes' => $clip(implode(' · ', array_filter([isset($r['naics_code']) ? 'NAICS '.$r['naics_code'] : null, isset($r['psc_code']) ? 'PSC '.$r['psc_code'] : null])), 200),
                    'place' => $clip(implode(', ', array_filter([$pop['city_name'] ?? null, $pop['state_code'] ?? null])), 200),
                    'summary' => $clip($r['description'] ?? null, 1000), 'url' => $clip($r['sam_url'] ?? null, 1000),
                    'posted_at' => $date($r['first_notice_date'] ?? null), 'deadline_at' => $date($r['response_deadline'] ?? null), 'open' => (bool) ($r['active'] ?? true),
                ];
            case 'sled':
                if (empty($r['opportunity_id']) || empty($r['title'])) {
                    return null;
                }

                return [
                    'source' => 'sled', 'external_id' => (string) $r['opportunity_id'], 'title' => $clip($r['title'], 500),
                    'agency' => $clip($r['agency'] ?? null, 300), 'solicitation_number' => $clip($r['solicitation_number'] ?? null, 120),
                    'kind' => $clip(strtoupper((string) ($r['solicitation_type'] ?? '')), 60), 'set_aside' => null, 'codes' => null,
                    'place' => $clip(implode(', ', array_filter([$r['state'] ?? null, isset($r['jurisdiction']) ? Str::title((string) $r['jurisdiction']) : null])), 200),
                    'summary' => null, 'url' => $clip($r['source_url'] ?? null, 1000),
                    'posted_at' => $date($r['posted_date'] ?? null), 'deadline_at' => $date($r['response_deadline'] ?? null), 'open' => ($r['status'] ?? 'open') === 'open',
                ];
            case 'dibbs_rfq':
            case 'dibbs_rfp':
                if (empty($r['uuid']) || empty($r['solicitation'])) {
                    return null;
                }
                $rfq = $source === 'dibbs_rfq';
                $item = Str::title(strtolower((string) ($r['nomenclature'] ?? 'Item')));
                $qty = isset($r['quantity']) ? number_format((int) $r['quantity']).' '.($r['unit_of_issue'] ?? '') : null;

                return [
                    'source' => $source, 'external_id' => (string) $r['uuid'], 'title' => $clip($item.(isset($r['nsn']) ? ' (NSN '.$r['nsn'].')' : ''), 500),
                    'agency' => 'Defense Logistics Agency', 'solicitation_number' => $clip($r['solicitation_formatted'] ?? $r['solicitation'], 120), 'kind' => $rfq ? 'RFQ' : 'RFP',
                    'set_aside' => ($r['set_aside'] ?? null) === 'Y' ? 'Small business set-aside' : null,
                    'codes' => $clip(implode(' · ', array_filter([isset($r['part_number']) ? 'Part '.$r['part_number'] : null, $qty ? 'Qty '.trim($qty) : null])), 200),
                    'place' => null, 'summary' => null,
                    // The RFQ's own document when Tango has it; otherwise DIBBS itself, where the solicitation number finds it.
                    'url' => $clip($r['document_url'] ?? 'https://www.dibbs.bsm.dla.mil/', 1000),
                    'posted_at' => $date($r[$rfq ? 'issue_date' : 'issued_date'] ?? null), 'deadline_at' => $date($r[$rfq ? 'return_by_date' : 'closes_date'] ?? null), 'open' => (bool) ($r['is_open'] ?? true),
                ];
        }

        return null;
    }

    /**
     * How likely a bid is to be for safety glasses: our words in the title count most, in the description less,
     * a match on the ophthalmic or safety codes a little; words like "night vision" pull it down.
     */
    public static function score(string $title, string $summary, array $terms): int
    {
        $t = mb_strtolower($title);
        $s = mb_strtolower($summary);
        $score = 0;
        foreach (config('tango.strong') as $w) {
            $score += str_contains($t, $w) ? 3 : (str_contains($s, $w) ? 1 : 0);
        }
        $byCode = false;
        foreach (array_unique($terms) as $term) {
            $term = mb_strtolower($term);
            if (str_starts_with($term, 'psc ') || str_starts_with($term, 'naics ')) {
                $byCode = true;
            } elseif (str_contains($t, $term)) {
                $score += 2;
            }
        }
        // Being under an ophthalmic or safety code is a hint, not proof: one point however many codes matched.
        $score += $byCode ? 1 : 0;
        foreach (config('tango.weak') as $w) {
            if (str_contains($t, $w)) {
                $score -= 4;
            }
        }

        return max(-20, min(30, $score));
    }

    /** Post the day's good new bids to Slack (TANGO_SLACK_CHANNEL, or the admin channel if that's all there is). */
    private static function digest(array $good): void
    {
        if (! $good) {
            return;
        }
        usort($good, fn ($a, $b) => [$b->score, $a->deadline_at?->timestamp ?? PHP_INT_MAX] <=> [$a->score, $b->deadline_at?->timestamp ?? PHP_INT_MAX]);
        $lines = array_map(fn (Opportunity $o) => '• <'.route('opportunities', ['o' => $o->id]).'|'.AccessNotifier::clean(Str::limit($o->title, 90)).'>'
            .($o->agency ? ' · '.AccessNotifier::clean(Str::limit($o->agency, 50)) : '').($o->deadline_at ? ' · due '.$o->deadline_at->format('M j') : ''), array_slice($good, 0, 8));
        $text = ':safety_vest: *'.count($good).' new '.(count($good) === 1 ? 'bid' : 'bids').' for safety glasses* in OmniVEN:'."\n".implode("\n", $lines);
        $channel = trim((string) config('tango.slack_channel'));
        if ($channel !== '' && Slack::configured()) {
            try {
                $id = Cache::remember('slack.opps-channel.'.md5($channel), now()->addDay(), fn () => Slack::findChannel($channel)['id'] ?? null);
                if ($id) {
                    Slack::post($id, $text, 'OmniVEN');

                    return;
                }
            } catch (Throwable $e) {
                Log::warning('Opportunities Slack post failed', ['error' => $e->getMessage()]);
            }
        }
        AccessNotifier::postToChannel($text);
    }
}
