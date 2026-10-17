<?php

namespace Tests\Feature;

use App\Models\AiCheck;
use App\Models\AiQuestion;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAnswersTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $this->kay = User::factory()->create(['email' => 'kaylie@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    public function test_starter_questions_and_a_monthly_routine_are_seeded(): void
    {
        $this->assertGreaterThanOrEqual(10, AiQuestion::count());
        $this->assertTrue(AiQuestion::where('question', 'Is 39DollarGlasses legit?')->exists());
        $this->assertSame('ocusafe', AiQuestion::where('question', 'like', '%ANSI Z87%')->first()->brand->slug);
        $r = Routine::where('title', 'Check what AI assistants say about us')->firstOrFail();
        $this->assertSame('monthly', $r->cadence);
        $this->assertStringEndsWith('/ai-answers', $r->link);
    }

    public function test_the_grid_opens_each_assistant_with_the_question(): void
    {
        $q = AiQuestion::where('question', 'Is 39DollarGlasses legit?')->first();
        $html = $this->actingAs($this->kay)->get('/ai-answers')->assertOk()->getContent();
        $this->assertStringContainsString('October answers', $html);
        $this->assertStringContainsString('href="https://chatgpt.com/?q=Is%2039DollarGlasses%20legit%3F"', $html);
        $this->assertStringContainsString('href="https://www.perplexity.ai/search?q=Is%2039DollarGlasses%20legit%3F"', $html);
        $this->assertStringContainsString('name="results['.$q->id.'][gemini]"', $html);
        $this->assertStringNotContainsString('id="questions"', $html, 'only admins manage questions');
    }

    public function test_saving_records_results_and_rates_add_up(): void
    {
        [$a, $b] = AiQuestion::orderBy('sort')->take(2)->get();
        $this->actingAs($this->kay)->post('/ai-answers', ['month' => '2026-10', 'results' => [
            $a->id => ['chatgpt' => 'recommended', 'google_ai' => 'mentioned', 'perplexity' => 'absent', 'gemini' => '', 'copilot' => ''],
            $b->id => ['chatgpt' => 'absent', 'google_ai' => '', 'perplexity' => '', 'gemini' => '', 'copilot' => ''],
        ], 'competitors' => [$a->id => 'Zenni, Warby Parker']])->assertRedirect()->assertSessionHas('status', 'Saved 4 answers.');

        $this->assertSame(4, AiCheck::count());
        $this->assertSame('Zenni, Warby Parker', AiCheck::where('ai_question_id', $a->id)->first()->competitors);
        $this->assertSame($this->kay->id, AiCheck::first()->user_id);

        $html = $this->actingAs($this->kay)->get('/ai-answers')->getContent();
        // 2 of 4 answers mention us, 1 of 4 recommends.
        $this->assertMatchesRegularExpression('#Mention rate</span><span class="tv">50%</span>#', $html);
        $this->assertMatchesRegularExpression('#Recommended us</span><span class="tv">25%</span>#', $html);
        $this->assertStringContainsString('class="aians-cell r-recommended"', $html);
        $this->assertStringContainsString('value="Zenni, Warby Parker"', $html);

        // Clearing a cell removes that answer; saving nothing new says so.
        $this->actingAs($this->kay)->post('/ai-answers', ['month' => '2026-10', 'results' => [$b->id => ['chatgpt' => '']]]);
        $this->assertSame(3, AiCheck::count());
        $this->actingAs($this->kay)->post('/ai-answers', ['month' => '2026-10', 'results' => [$a->id => ['chatgpt' => 'recommended', 'google_ai' => 'mentioned', 'perplexity' => 'absent']], 'competitors' => [$a->id => 'Zenni, Warby Parker']])
            ->assertSessionHas('status', 'Nothing changed.');
    }

    public function test_months_compare_and_the_future_is_off_limits(): void
    {
        $q = AiQuestion::first();
        AiCheck::create(['ai_question_id' => $q->id, 'engine' => 'chatgpt', 'month' => '2026-09-01', 'result' => 'absent']);
        AiCheck::create(['ai_question_id' => $q->id, 'engine' => 'google_ai', 'month' => '2026-09-01', 'result' => 'absent']);
        AiCheck::create(['ai_question_id' => $q->id, 'engine' => 'chatgpt', 'month' => '2026-10-01', 'result' => 'mentioned']);

        $html = $this->actingAs($this->kay)->get('/ai-answers')->getContent();
        $this->assertStringContainsString('▲ 100 pts', $html);
        $this->assertStringContainsString('Sep 2026', $html);

        $sep = $this->actingAs($this->kay)->get('/ai-answers?month=2026-09')->getContent();
        $this->assertStringContainsString('September answers', $sep);
        $this->assertStringContainsString('Back to this month', $sep);
        $this->assertStringContainsString('October answers', $this->actingAs($this->kay)->get('/ai-answers?month=2027-03')->getContent());

        $this->actingAs($this->kay)->post('/ai-answers', ['month' => '2030-01', 'results' => [$q->id => ['perplexity' => 'mentioned']]]);
        $this->assertSame('2026-10-01', AiCheck::where('engine', 'perplexity')->first()->month->format('Y-m-d'));
        $this->actingAs($this->kay)->post('/ai-answers', ['results' => [$q->id => ['chatgpt' => 'great']]])->assertSessionHasErrors();
    }

    public function test_admins_manage_questions_and_brand_filter_narrows_the_grid(): void
    {
        $ocusafe = Brand::where('slug', 'ocusafe')->first();
        $this->actingAs($this->kay)->post('/ai-answers/questions', ['question' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->get('/ai-answers')->assertSee('id="questions"', false);
        $this->actingAs($this->admin)->post('/ai-answers/questions', ['question' => 'Best safety glasses that fit over prescription glasses?', 'brand_id' => $ocusafe->id])->assertRedirect();
        $q = AiQuestion::where('question', 'like', 'Best safety glasses that fit%')->firstOrFail();

        $html = $this->actingAs($this->kay)->get('/ai-answers?brand=ocusafe')->getContent();
        $this->assertStringContainsString('Best safety glasses that fit over prescription glasses?', $html);
        $this->assertStringNotContainsString('Is 39DollarGlasses legit?', $html);

        $this->actingAs($this->admin)->patch("/ai-answers/questions/{$q->id}", ['question' => $q->question, 'brand_id' => $ocusafe->id])->assertRedirect();
        $this->assertFalse($q->fresh()->active);
        $this->assertStringNotContainsString('fit over prescription', $this->actingAs($this->kay)->get('/ai-answers?brand=ocusafe')->getContent());
        $this->actingAs($this->admin)->delete("/ai-answers/questions/{$q->id}")->assertRedirect();
        $this->assertNull($q->fresh());
    }
}
