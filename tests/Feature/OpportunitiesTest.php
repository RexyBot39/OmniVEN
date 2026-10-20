<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use App\Support\Opportunities\OpportunityFinder;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpportunitiesTest extends TestCase
{
    use RefreshDatabase;

    private const T = 'https://tango.makegov.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        Http::preventStrayRequests();
        config(['tango.api_key' => null, 'tango.slack_channel' => '', 'omniven.access_slack_channel' => '',
            'tango.terms' => ['safety glasses'], 'tango.dibbs_terms' => ['spectacles'], 'tango.psc' => ['6540'], 'tango.naics' => []]);
    }

    private function member(): User
    {
        return User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private static function page(array $results, ?string $next = null): array
    {
        return ['count' => count($results), 'next' => $next, 'previous' => null, 'results' => $results];
    }

    private function fakeTango(): void
    {
        Http::fake([
            self::T.'/api/opportunities/*' => fn (Request $r) => Http::response(self::page(str_contains($r->url(), 'psc=6540') ? [
                ['opportunity_id' => 'sam-2', 'title' => 'Night vision goggles, thermal', 'active' => true, 'psc_code' => '6540', 'response_deadline' => now()->addDays(20)->toIso8601String()],
                ['opportunity_id' => 'sam-1', 'title' => 'Prescription Safety Glasses for shipyard workers', 'active' => true, 'psc_code' => '6540', 'naics_code' => 339115],
            ] : [
                ['opportunity_id' => 'sam-1', 'title' => 'Prescription Safety Glasses for shipyard workers', 'description' => '<p>ANSI Z87.1 rated prescription safety eyewear, 400 pairs.</p>',
                    'solicitation_number' => 'N00024-26-Q-0101', 'response_deadline' => now()->addDays(10)->toIso8601String(), 'first_notice_date' => now()->subDay()->toIso8601String(),
                    'active' => true, 'naics_code' => 339115, 'psc_code' => '6540', 'set_aside' => ['code' => 'SBA', 'description' => 'Total Small Business Set-Aside'],
                    'sam_url' => 'https://sam.gov/opp/sam-1/view', 'office' => ['agency' => 'Department of the Navy', 'name' => 'Norfolk Naval Shipyard'],
                    'place_of_performance' => ['city_name' => 'Portsmouth', 'state_code' => 'VA']],
            ])),
            self::T.'/api/sled/opportunities/*' => Http::response(self::page([
                ['opportunity_id' => 'sled-9', 'title' => 'Safety glasses and PPE annual contract', 'agency' => 'City of Houston', 'state' => 'TX', 'jurisdiction' => 'local',
                    'solicitation_type' => 'rfp', 'status' => 'open', 'response_deadline' => now()->addDays(2)->toIso8601String(), 'source_url' => 'https://purchasing.houstontx.gov/bid/9'],
            ])),
            self::T.'/api/dibbs/rfqs/*' => Http::response(self::page([
                ['uuid' => 'rfq-1', 'solicitation' => 'SPE1C126T1234', 'solicitation_formatted' => 'SPE1C1-26-T-1234', 'nsn' => '4240-01-504-0479', 'nomenclature' => 'SPECTACLES,INDUSTRIAL',
                    'quantity' => 2500, 'unit_of_issue' => 'PR', 'return_by_date' => now()->addDays(14)->toDateString(), 'set_aside' => 'Y', 'is_open' => true, 'document_url' => 'https://dibbs.example/rfq-1.pdf'],
            ])),
            self::T.'/api/dibbs/rfps/*' => Http::response(['detail' => 'Your plan does not include DIBBS RFPs.'], 403),
        ]);
    }

    public function test_it_is_hidden_until_tango_is_set_up_and_admins_get_steps(): void
    {
        $this->actingAs($this->member())->get('/opportunities')->assertForbidden();
        $this->actingAs($this->admin())->get('/opportunities')->assertOk()->assertSee('Connect Tango')->assertSee('TANGO_API_KEY')->assertSee('omniven:opportunities-sync');
    }

    public function test_the_daily_search_finds_scores_and_saves_bids_from_every_source(): void
    {
        config(['tango.api_key' => 'tk_secret']);
        $this->fakeTango();

        $r = OpportunityFinder::run();
        $this->assertSame(4, $r['new']);
        $this->assertSame(['dibbs' => 'The Tango plan doesn’t include this source (Your plan does not include DIBBS RFPs.). Upgrade the plan at tango.makegov.com, or turn the source off.'], $r['errors']);

        $sam = Opportunity::where('external_id', 'sam-1')->firstOrFail();
        $this->assertSame('Department of the Navy · Norfolk Naval Shipyard', $sam->agency);
        $this->assertSame('Total Small Business Set-Aside', $sam->set_aside);
        $this->assertSame('Portsmouth, VA', $sam->place);
        $this->assertSame('NAICS 339115 · PSC 6540', $sam->codes);
        $this->assertStringStartsWith('ANSI Z87.1 rated', $sam->summary);
        $this->assertSame('safety glasses, PSC 6540', $sam->matched);
        $this->assertGreaterThanOrEqual(config('tango.good_score') + 3, $sam->score);
        $this->assertLessThan(config('tango.good_score'), Opportunity::where('external_id', 'sam-2')->value('score'));

        $rfq = Opportunity::where('source', 'dibbs_rfq')->firstOrFail();
        $this->assertSame('Spectacles,Industrial (NSN 4240-01-504-0479)', $rfq->title);
        $this->assertSame('Qty 2,500 PR', str_replace('Part ', '', $rfq->codes));
        $this->assertSame('Small business set-aside', $rfq->set_aside);
        $this->assertSame('Houston', explode(' ', Opportunity::where('source', 'sled')->value('agency'))[2]);

        Http::assertSent(fn (Request $q) => str_contains($q->url(), '/api/opportunities/') && $q->hasHeader('X-API-KEY', 'tk_secret')
            && str_contains($q->url(), 'search=safety%20glasses') && str_contains($q->url(), 'active=true') && str_contains($q->url(), 'first_notice_date_after='.now()->subDays(30)->format('Y-m-d')));
        Http::assertSent(fn (Request $q) => str_contains($q->url(), '/api/sled/opportunities/') && str_contains($q->url(), 'first_seen_after='));
        // A source that failed keeps the window where it was, so tomorrow searches those days again.
        $this->assertNull(Cache::get('opps:since'));

        // Running again updates what's there instead of adding duplicates, and keeps the team's decision.
        $sam->update(['status' => 'pursuing']);
        $this->assertSame(0, OpportunityFinder::run()['new']);
        $this->assertSame(4, Opportunity::count());
        $this->assertSame('pursuing', $sam->fresh()->status);
    }

    public function test_staff_see_good_matches_and_can_pursue_pass_and_make_a_task(): void
    {
        config(['tango.api_key' => 'tk']);
        $this->fakeTango();
        OpportunityFinder::run();
        $member = $this->member();
        $sam = Opportunity::where('external_id', 'sam-1')->first();
        $sled = Opportunity::where('external_id', 'sled-9')->first();

        $this->actingAs($member)->get('/opportunities')->assertOk()
            ->assertSee('Prescription Safety Glasses for shipyard workers')->assertSee('https://sam.gov/opp/sam-1/view', false)
            ->assertSee('Strong match')->assertSee('2 days left')->assertDontSee('Night vision goggles')
            ->assertSee('DLA DIBBS')->assertDontSee('Your plan does not include');
        $this->actingAs($member)->get('/opportunities?view=all')->assertOk()->assertSee('Night vision goggles');

        $this->actingAs($member)->patch("/opportunities/{$sled->id}", ['status' => 'passed'])->assertRedirect();
        $this->assertSame('passed', $sled->fresh()->status);
        $this->actingAs($member)->get('/opportunities')->assertDontSee('Safety glasses and PPE annual contract');

        $this->actingAs($member)->post("/opportunities/{$sam->id}/task")->assertRedirect();
        $sam->refresh();
        $this->assertSame('pursuing', $sam->status);
        $this->assertSame($member->id, $sam->owner_id);
        $task = $sam->task;
        $this->assertSame($member->id, $task->assignee_id);
        $this->assertSame('ocusafe', $task->brand->slug);
        $this->assertSame($sam->deadline_at->copy()->subDays(2)->toDateString(), $task->due_on->toDateString());
        $this->assertStringContainsString('N00024-26-Q-0101', $task->details);
        $this->actingAs($member)->post("/opportunities/{$sam->id}/task")->assertSessionHas('status', 'This bid already has an open task.');
        $this->assertDatabaseHas('activity_logs', ['action' => 'opportunity.tasked']);

        // Staff can't change the search phrases.
        $this->actingAs($member)->post('/opportunities/terms', ['term' => 'anti-fog goggles'])->assertForbidden();
    }

    public function test_admins_tune_the_search_phrases(): void
    {
        config(['tango.api_key' => 'tk']);
        Http::fake(['*' => Http::response(self::page([]))]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/opportunities')->assertOk()->assertSee('safety glasses');
        $this->actingAs($admin)->post('/opportunities/terms', ['term' => '  Anti-Fog   Goggles '])->assertRedirect();
        $this->assertDatabaseHas('opportunity_terms', ['term' => 'anti-fog goggles']);
        $this->assertContains('anti-fog goggles', OpportunityFinder::terms());

        Cache::flush();
        OpportunityFinder::run();
        Http::assertSent(fn (Request $q) => str_contains($q->url(), 'search=anti-fog%20goggles'));
    }

    public function test_scores_favor_our_words_in_the_title(): void
    {
        $good = config('tango.good_score');
        $this->assertGreaterThanOrEqual($good, OpportunityFinder::score('ANSI Z87 safety glasses', '', ['safety glasses']));
        $this->assertGreaterThanOrEqual($good, OpportunityFinder::score('Spectacles, industrial', '', ['spectacles']));
        $this->assertLessThan($good, OpportunityFinder::score('Night vision goggle repair', '', ['PSC 6540', 'PSC 4240', 'NAICS 339115', 'NAICS 423460']));
        $this->assertLessThan($good, OpportunityFinder::score('Window glass replacement, Building 4', '', ['safety glasses']));
        $this->assertLessThan($good, OpportunityFinder::score('Janitorial services', 'includes safety glasses for staff', ['safety glasses']));
    }
}
