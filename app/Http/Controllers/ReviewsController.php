<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Review;
use App\Models\ReviewSnapshot;
use App\Support\Activity;
use App\Support\Reviews\ReviewSync;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Reviews: star ratings across platforms, low reviews still waiting on a reply, and recent reviews. */
class ReviewsController extends Controller
{
    public function index(Request $request): View
    {
        ReviewSync::refreshIfStale();
        $today = CarbonImmutable::now(config('app.timezone'));
        $monthAgo = $today->subDays(30)->format('Y-m-d');

        $cards = collect(config('reviews.platforms'))->map(function ($p, $key) use ($monthAgo) {
            $latest = ReviewSnapshot::where('platform', $key)->orderByDesc('day')->first();
            $before = ReviewSnapshot::where('platform', $key)->where('day', '<=', $monthAgo)->orderByDesc('day')->first();

            return $p + [
                'key' => $key,
                'latest' => $latest,
                'change' => $latest && $before && $latest->rating !== null && $before->rating !== null ? $latest->rating - $before->rating : null,
                'new' => $latest && $before && $latest->review_count !== null && $before->review_count !== null ? $latest->review_count - $before->review_count : null,
                'connected' => $p['auto'] && ReviewSync::configured($key),
                'status' => $p['auto'] ? ReviewSync::status($key) : null,
            ];
        });

        $stars = in_array($request->query('stars'), ['low', '5', '4', '3', '2', '1'], true) ? $request->query('stars') : null;
        $recent = Review::with('task')->where('reviewed_at', '>=', $today->subDays(30))
            ->when($stars === 'low', fn ($q) => $q->where('stars', '<=', 2))
            ->when($stars && $stars !== 'low', fn ($q) => $q->where('stars', (int) $stars))
            ->orderByDesc('reviewed_at')->limit(100)->get();

        $months = collect(range(5, 0))->map(fn ($i) => $today->startOfMonth()->subMonths($i));

        return view('reviews.index', [
            'cards' => $cards,
            'stars' => $stars,
            'recent' => $recent,
            'needsReply' => Review::with('task')->where('stars', '<=', config('reviews.task_max_stars'))
                ->where('reviewed_at', '>=', $today->subDays(30))
                ->where(fn ($q) => $q->whereNull('task_id')->orWhereHas('task', fn ($t) => $t->whereNull('completed_at')))
                ->orderByDesc('reviewed_at')->get(),
            'counts' => Review::where('reviewed_at', '>=', $today->subDays(30))->selectRaw('stars, count(*) as n')->groupBy('stars')->pluck('n', 'stars'),
            'months' => $months,
            // Each platform's rating at the end of each of the last six months (its last snapshot in that month).
            'trend' => collect(config('reviews.platforms'))->map(fn ($p, $key) => $months->map(fn ($m) => ReviewSnapshot::where('platform', $key)
                ->whereBetween('day', [$m->format('Y-m-d'), $m->endOfMonth()->format('Y-m-d')])->orderByDesc('day')->value('rating'))->all()),
            'anyAuto' => ReviewSync::anyConfigured(),
        ]);
    }

    /** Record a platform's current rating and review count by hand (for platforms without an API). */
    public function snapshot(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(array_keys(config('reviews.platforms')))],
            'rating' => ['required', 'numeric', 'min:0', 'max:5'],
            'review_count' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);
        $brand = Brand::where('slug', config('reviews.platforms.'.$data['platform'].'.brand'))->first();
        ReviewSnapshot::updateOrCreate(
            ['platform' => $data['platform'], 'brand_id' => $brand?->id, 'day' => CarbonImmutable::now(config('app.timezone'))->format('Y-m-d')],
            ['rating' => round((float) $data['rating'], 2), 'review_count' => $data['review_count'] ?? null, 'manual' => true, 'user_id' => $request->user()->id],
        );
        $label = config('reviews.platforms.'.$data['platform'].'.label');
        Activity::log('review.rating', 'Updated the '.$label.' rating to '.$data['rating'].'★');

        return redirect()->route('reviews')->with('status', $label.' rating saved.');
    }
}
