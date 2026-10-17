<?php

namespace App\Http\Controllers;

use App\Models\AiCheck;
use App\Models\AiQuestion;
use App\Models\Brand;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * AI answers: each month the team asks AI assistants the questions shoppers ask and records whether
 * the answer recommended us, mentioned us, or left us out. The mention rate is the share of answers
 * that name us at all.
 */
class AiAnswersController extends Controller
{
    public function index(Request $request): View
    {
        $month = $this->month($request->query('month'));
        $engines = config('ai_answers.engines');
        $brands = Brand::orderBy('sort')->orderBy('name')->get();
        $brand = $brands->firstWhere('slug', $request->query('brand'));

        $questions = AiQuestion::with('brand')->where('active', true)
            ->when($brand, fn ($q) => $q->where('brand_id', $brand->id))
            ->orderBy('sort')->orderBy('id')->get();
        $checks = AiCheck::whereIn('ai_question_id', $questions->pluck('id'))->where('month', $month->format('Y-m-d'))->get();
        $grid = $checks->groupBy('ai_question_id')->map(fn ($c) => $c->keyBy('engine'));

        // Six months of history for the same questions.
        $from = $month->subMonths(5);
        $history = AiCheck::whereIn('ai_question_id', $questions->pluck('id'))
            ->whereBetween('month', [$from->format('Y-m-d'), $month->format('Y-m-d')])->get(['ai_question_id', 'engine', 'month', 'result']);
        $months = collect(range(0, 5))->map(fn ($i) => $from->addMonths($i));
        $byMonth = $history->groupBy(fn ($c) => $c->month->format('Y-m'));

        $now = $this->rates($checks);
        $prev = $this->rates($byMonth->get($month->subMonth()->format('Y-m'), collect()));

        return view('ai-answers.index', [
            'month' => $month,
            'isCurrent' => $month->isSameMonth(CarbonImmutable::now(config('app.timezone'))),
            'engines' => $engines,
            'results' => config('ai_answers.results'),
            'brands' => $brands,
            'brand' => $brand,
            'questions' => $questions,
            'groups' => $questions->groupBy(fn ($q) => $q->brand?->name ?? 'Any brand'),
            'grid' => $grid,
            'now' => $now,
            'prev' => $prev,
            'perEngine' => collect($engines)->map(fn ($e, $key) => $this->rates($checks->where('engine', $key))),
            'perBrand' => $questions->groupBy('brand_id')->map(fn ($qs) => [
                'brand' => $qs->first()->brand,
                'rates' => $this->rates($checks->whereIn('ai_question_id', $qs->pluck('id'))),
            ])->values(),
            'trend' => $months->map(fn ($m) => ['month' => $m] + $this->rates($byMonth->get($m->format('Y-m'), collect())))->all(),
            'allQuestions' => Gate::allows('admin') ? AiQuestion::with('brand')->orderBy('sort')->orderBy('id')->get() : collect(),
        ]);
    }

    /** Save the grid: one result per question and assistant, plus who was named instead. */
    public function save(Request $request): RedirectResponse
    {
        $month = $this->month($request->input('month'));
        $engines = array_keys(config('ai_answers.engines'));
        $valid = array_keys(config('ai_answers.results'));
        $request->validate([
            'results' => ['array'],
            'results.*' => ['array'],
            'results.*.*' => ['nullable', Rule::in($valid)],
            'competitors' => ['array'],
            'competitors.*' => ['nullable', 'string', 'max:300'],
        ]);

        $ids = AiQuestion::whereIn('id', array_keys($request->input('results', [])))->pluck('id');
        $saved = 0;
        DB::transaction(function () use ($request, $ids, $engines, $month, &$saved) {
            foreach ($ids as $qid) {
                $competitors = trim((string) $request->input("competitors.$qid")) ?: null;
                foreach ($engines as $engine) {
                    $result = $request->input("results.$qid.$engine");
                    $where = ['ai_question_id' => $qid, 'engine' => $engine, 'month' => $month->format('Y-m-d')];
                    if (! $result) {
                        AiCheck::where($where)->delete();

                        continue;
                    }
                    $check = AiCheck::firstOrNew($where);
                    if ($check->result !== $result || $check->competitors !== $competitors) {
                        $check->fill(['result' => $result, 'competitors' => $competitors, 'user_id' => $request->user()->id])->save();
                        $saved++;
                    }
                }
            }
        });
        if ($saved) {
            Activity::log('ai.checked', 'Recorded '.$saved.' AI '.($saved === 1 ? 'answer' : 'answers').' for '.$month->format('F Y'));
        }

        return redirect()->route('ai-answers', array_filter(['month' => $month->format('Y-m'), 'brand' => $request->input('brand')]))
            ->with('status', $saved ? 'Saved '.$saved.' '.($saved === 1 ? 'answer' : 'answers').'.' : 'Nothing changed.');
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        $data = $this->validateQuestion($request);
        AiQuestion::create($data + ['sort' => (int) AiQuestion::max('sort') + 10]);

        return redirect()->to(route('ai-answers').'#questions')->with('status', 'Question added.');
    }

    public function updateQuestion(Request $request, AiQuestion $question): RedirectResponse
    {
        $question->update($this->validateQuestion($request) + ['active' => $request->boolean('active')]);

        return redirect()->to(route('ai-answers').'#questions')->with('status', 'Question updated.');
    }

    public function destroyQuestion(AiQuestion $question): RedirectResponse
    {
        $question->delete();

        return redirect()->to(route('ai-answers').'#questions')->with('status', 'Question and its history removed.');
    }

    private function validateQuestion(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
        ]);
    }

    /** "2026-10" → the first of that month; never a future month. */
    private function month(?string $v): CarbonImmutable
    {
        $tz = config('app.timezone');
        $this_ = CarbonImmutable::now($tz)->startOfMonth();
        if (is_string($v) && preg_match('/^(\d{4})-(\d{2})$/', $v, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            $d = CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, $tz);

            return $d->gt($this_) ? $this_ : $d;
        }

        return $this_;
    }

    /** @return array{checked: int, mention: ?float, recommend: ?float} */
    private function rates(Collection $checks): array
    {
        $n = $checks->count();

        return [
            'checked' => $n,
            'mention' => $n ? $checks->whereIn('result', ['recommended', 'mentioned'])->count() / $n : null,
            'recommend' => $n ? $checks->where('result', 'recommended')->count() / $n : null,
        ];
    }
}
