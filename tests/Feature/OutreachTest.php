<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\OutreachEvent;
use App\Models\Publisher;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OutreachTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $this->kay = User::factory()->create(['name' => 'Kay Lee', 'email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function add(array $over = []): Publisher
    {
        $this->actingAs($this->kay)->post('/outreach', $over + ['name' => 'All About Vision', 'site' => 'https://www.allaboutvision.com', 'type' => 'review', 'stage' => 'idea', 'owner_id' => $this->kay->id]);

        return Publisher::latest('id')->first();
    }

    public function test_a_pitch_moves_through_its_stages_with_follow_ups(): void
    {
        $p = $this->add();
        $this->assertSame('idea', $p->stage);

        $this->actingAs($this->kay)->post("/outreach/{$p->id}/act", ['action' => 'pitched', 'body' => 'Offered a frame for review'])->assertSessionHas('status', 'Logged. Follow up on Fri Oct 23.');
        $p->refresh();
        $this->assertSame('contacted', $p->stage);
        $this->assertSame('2026-10-23', $p->follow_up_on->format('Y-m-d'));
        $this->assertSame('2026-10-23', DB::table('publishers')->value('follow_up_on'), 'stored as a plain date');

        // A week later it's due, at the top of Outreach.
        $this->travelTo(Carbon::parse('2026-10-23 09:00', config('app.timezone')));
        $this->flushSession();
        $html = $this->actingAs($this->kay)->withSession(['signed_in_at' => now()->timestamp])->get('/outreach')->assertOk()->getContent();
        $this->assertStringContainsString('Follow up now', $html);
        $this->assertStringContainsString('Follow up today', $html);

        $this->actingAs($this->kay)->post("/outreach/{$p->id}/act", ['action' => 'replied', 'body' => 'Wants two frames'])->assertSessionHas('status', 'Logged. Answer them by Mon Oct 26.');
        $this->assertSame('replied', $p->refresh()->stage);

        $this->actingAs($this->kay)->post("/outreach/{$p->id}/act", ['action' => 'live'])->assertSessionHasErrors('result_url');
        $this->actingAs($this->kay)->post("/outreach/{$p->id}/act", ['action' => 'live', 'result_url' => 'https://www.allaboutvision.com/best-online-glasses/']);
        $p->refresh();
        $this->assertSame('live', $p->stage);
        $this->assertNull($p->follow_up_on);
        $this->assertSame(['live', 'replied', 'pitched', 'note'], $p->events()->pluck('kind')->all());

        $show = $this->actingAs($this->kay)->get("/outreach/{$p->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Wants two frames', $show);
        $this->assertStringContainsString('allaboutvision.com/best-online-glasses/', $show);
        $this->assertStringContainsString('Reopen', $show);
    }

    public function test_three_tries_suggest_closing_and_no_reply_closes(): void
    {
        $p = $this->add();
        foreach (['pitched', 'nudged', 'nudged'] as $a) {
            $this->actingAs($this->kay)->post("/outreach/{$p->id}/act", ['action' => $a]);
        }
        $this->assertStringContainsString('3 tries and no answer', $this->actingAs($this->kay)->get("/outreach/{$p->id}")->getContent());
        $this->actingAs($this->kay)->post("/outreach/{$p->id}/act", ['action' => 'no_reply']);
        $this->assertSame('no_reply', $p->refresh()->stage);
        $list = $this->actingAs($this->kay)->get('/outreach')->getContent();
        $this->assertStringNotContainsString('All About Vision', substr($list, strpos($list, 'In progress')), 'closed ones leave the open list');
        $this->assertStringContainsString('All About Vision', $this->actingAs($this->kay)->get('/outreach?stage=no_reply')->getContent());
    }

    public function test_paste_a_list_skips_ones_already_there(): void
    {
        $this->add();
        $this->actingAs($this->kay)->post('/outreach/bulk', ['type' => 'blog', 'lines' => "Glasses Compare, https://www.glassescompare.com\nhttps://www.allaboutvision.com/frames\nhttps://www.eyeweartips.net\nNot a site, nonsense\n\n"])
            ->assertSessionHas('status', 'Added 3. Skipped 1 already on the list.');
        $this->assertSame(['All About Vision', 'Glasses Compare', 'eyeweartips.net', 'Not a site'], Publisher::orderBy('id')->pluck('name')->all());
        $this->assertNull(Publisher::where('name', 'Not a site')->value('site'));
        $this->assertSame($this->kay->id, Publisher::where('name', 'Glasses Compare')->value('owner_id'));
    }

    public function test_affiliate_sales_only_for_revenue_viewers_and_only_admins_delete(): void
    {
        $p = $this->add(['network' => 'cj', 'network_id' => '7', 'type' => 'affiliate']);
        DB::table('affiliate_months')->insert(['network' => 'cj', 'month' => '2026-10-01', 'publisher_id' => '7', 'publisher_name' => 'AAV', 'orders' => 12, 'sale_cents' => 250_000, 'commission_cents' => 25_000, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertStringContainsString('$2,500', $this->actingAs($this->admin)->get("/outreach/{$p->id}")->getContent());
        $this->assertStringNotContainsString('$2,500', $this->actingAs($this->kay)->get("/outreach/{$p->id}")->getContent());

        $this->actingAs($this->kay)->delete("/outreach/{$p->id}")->assertForbidden();
        $this->actingAs($this->admin)->delete("/outreach/{$p->id}")->assertRedirect('/outreach');
        $this->assertSame(0, Publisher::count());
        $this->assertSame(0, OutreachEvent::count());
    }

    public function test_the_monthly_report_counts_outreach(): void
    {
        $p = $this->add();
        $this->actingAs($this->kay)->post("/outreach/{$p->id}/act", ['action' => 'pitched']);
        $this->actingAs($this->kay)->post("/outreach/{$p->id}/act", ['action' => 'live', 'result_url' => 'https://www.allaboutvision.com/x/']);
        $html = $this->actingAs($this->admin)->get('/reports/monthly')->getContent();
        $this->assertMatchesRegularExpression('/<b>1<\/b> pitched, <b>0<\/b> replied, <b>1<\/b> went live/', $html);
        $this->assertStringContainsString('allaboutvision.com/x/', $html);
    }
}
