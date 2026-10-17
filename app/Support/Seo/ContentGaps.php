<?php

namespace App\Support\Seo;

use App\Models\AiCheck;
use App\Models\AiQuestion;
use App\Models\Brand;
use App\Models\ContentGap;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The content gap queue: searches and AI questions where better content could win visits, each with
 * what to do about it. Worked out fresh from the Search Console summary and the latest AI answer
 * checks; only the decisions (made into a task, or set aside) are stored, in content_gaps.
 */
class ContentGaps
{
    public const KINDS = [
        'ctr' => 'Few clicks',
        'striking' => 'Almost there',
        'question' => 'Unanswered question',
        'ai' => 'AI leaves us out',
    ];

    /** Every open gap (not yet tasked or set aside), most promising first. */
    public static function open(): Collection
    {
        $decided = ContentGap::pluck('key')->flip();

        return self::all()->reject(fn ($g) => isset($decided[$g['key']]))->values();
    }

    public static function find(string $key): ?array
    {
        return self::all()->firstWhere('key', $key);
    }

    public static function all(): Collection
    {
        return self::fromSearch()->concat(self::fromAi())->sortByDesc('score')->values();
    }

    public static function key(string $kind, ?Brand $brand, string $query): string
    {
        return sha1($kind.'|'.($brand?->slug ?? '').'|'.mb_strtolower(trim($query)));
    }

    /** Does this search read like a question someone wants answered? */
    public static function isQuestion(string $q): bool
    {
        $q = mb_strtolower(trim($q));

        return str_ends_with($q, '?')
            || (bool) preg_match('/^(how|what|why|which|when|where|who|can|do|does|is|are|should|will|best|vs)\b/u', $q)
            || (preg_match('/\b(vs|versus|or|worth it|difference between)\b/u', $q) && str_word_count($q) >= 3);
    }

    private static function fromSearch(): Collection
    {
        $c = config('search_console.gaps');
        $out = collect();
        foreach (SearchConsole::configured() ? SearchConsole::brands() : [] as $slug => $brand) {
            $rows = SearchConsole::summary($slug)['gap_rows'] ?? [];
            $n = 0;
            foreach ($rows as $r) {
                $ctr = $r['impressions'] > 0 ? $r['clicks'] / $r['impressions'] : 0;
                $kind = match (true) {
                    $r['position'] <= $c['ctr']['max_position'] && $r['impressions'] >= $c['ctr']['min_impressions'] && $ctr < $c['ctr']['below_ctr'] => 'ctr',
                    $r['position'] >= $c['striking']['from'] && $r['position'] <= $c['striking']['to'] && $r['impressions'] >= $c['striking']['min_impressions'] => 'striking',
                    $r['position'] > $c['question']['beyond'] && $r['impressions'] >= $c['question']['min_impressions'] && self::isQuestion($r['query']) => 'question',
                    default => null,
                };
                if (! $kind || $n >= $c['per_brand']) {
                    continue;
                }
                $n++;
                $out->push(self::searchGap($kind, $brand, $r, $ctr));
            }
        }

        return $out;
    }

    private static function searchGap(string $kind, Brand $brand, array $r, float $ctr): array
    {
        $q = $r['query'];
        $page = $r['page'];
        $path = $page ? (parse_url($page, PHP_URL_PATH) ?: '/') : null;
        $stats = number_format($r['impressions']).' searches in 28 days · position '.$r['position'].' · '.number_format($ctr * 100, 1).'% clicked';

        [$why, $title, $steps] = match ($kind) {
            'ctr' => [
                'We show near the top, but few people click.',
                "Rewrite the Google title and description of {$path} for “{$q}”",
                "- Put “{$q}” (or close to it) near the start of the page title.\n- Give a reason to click in the description: price, free shipping, prescription included.\n- Keep the title under 60 characters and the description under 155.",
            ],
            'striking' => [
                'On the first or second page of Google. A stronger page could reach the top 5.',
                $path ? "Improve {$path} so it ranks for “{$q}”" : "Improve the page that ranks for “{$q}”",
                "- Add a section that answers “{$q}” directly, with the words people search.\n- Add a short FAQ at the end.\n- Link to this page from 2–3 related pages and blog posts.",
            ],
            default => [
                'People ask this and we aren’t in Google’s top 10.',
                "Write an answer to “{$q}”",
                "- A blog post or FAQ that answers the question in the first two sentences.\n- Then the detail: comparison table, prices, how to choose.\n- Link to the products it mentions, and link to it from the related category page.",
            ],
        };

        return [
            'key' => self::key($kind, $brand, $q),
            'kind' => $kind,
            'brand' => $brand,
            'query' => $q,
            'page' => $page,
            'stats' => $stats,
            'why' => $why,
            'score' => $r['impressions'] * ($kind === 'ctr' ? 1.0 : ($kind === 'striking' ? 0.8 : 0.5)),
            'title' => mb_substr($title, 0, 200),
            'details' => "{$why}\n{$stats}".($page ? "\nPage: {$page}" : '')."\n\n{$steps}\n\nWhen it’s live, add the link as this task’s result.",
        ];
    }

    /** Active AI questions where the latest month's checks didn't mention us anywhere. */
    private static function fromAi(): Collection
    {
        $latest = AiCheck::max('month');
        if (! $latest) {
            return collect();
        }
        $month = CarbonImmutable::parse(substr((string) $latest, 0, 10), config('app.timezone'));
        $checks = AiCheck::where('month', $month->format('Y-m-d'))->get(['ai_question_id', 'engine', 'result'])->groupBy('ai_question_id');
        $engines = config('ai_answers.engines');

        return AiQuestion::with('brand')->where('active', true)->whereIn('id', $checks->keys())->get()
            ->filter(fn ($q) => $checks[$q->id]->every(fn ($c) => $c->result === 'absent'))
            ->map(function ($q) use ($checks, $month, $engines) {
                $asked = $checks[$q->id]->map(fn ($c) => $engines[$c->engine]['label'] ?? $c->engine)->implode(', ');
                $stats = 'Checked in '.$month->format('F').': '.$asked;
                $why = 'No AI assistant we asked mentioned us.';

                return [
                    'key' => self::key('ai', $q->brand, $q->question),
                    'kind' => 'ai',
                    'brand' => $q->brand,
                    'query' => $q->question,
                    'page' => null,
                    'stats' => $stats,
                    'why' => $why,
                    // AI questions are hand-picked, so they rank with a mid-sized search gap.
                    'score' => 400 * $checks[$q->id]->count(),
                    'title' => mb_substr("Write a page AI assistants can quote for “{$q->question}”", 0, 200),
                    'details' => "{$why}\n{$stats}\n\n- Answer the question plainly in the first paragraph, naming the brand.\n- Add a comparison table and a short FAQ.\n- Get the page cited elsewhere: ask a review or comparison site that the assistants quote to include us.\n\nWhen it’s live, add the link as this task’s result. Next month’s AI answers check will show whether it worked.",
                ];
            })->values();
    }
}
