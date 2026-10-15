<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\LinkCheck;
use App\Models\Routine;
use App\Models\Tool;
use App\Models\User;
use App\Support\LinkChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkCheckTest extends TestCase
{
    use RefreshDatabase;

    private Tool $good;
    private Tool $gone;
    private Tool $down;
    private Account $ig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->good = Tool::create(['name' => 'Good Tool', 'url' => 'https://good.example.com/', 'status' => 'active']);
        $this->gone = Tool::create(['name' => 'Gone Tool', 'url' => 'https://gone.example.com/page', 'status' => 'active']);
        $this->down = Tool::create(['name' => 'Down Tool', 'url' => 'https://down.example.com/', 'status' => 'active']);
        $brand = Brand::create(['slug' => 'ocusafe', 'name' => 'Ocusafe', 'site' => 'https://good.example.com/', 'socials' => [['label' => 'Instagram', 'url' => 'https://www.instagram.com/ocusafeglasses']], 'sort' => 1]);
        $this->ig = Account::create(['brand_id' => $brand->id, 'platform' => 'instagram', 'handle' => '@ocusafeglasses', 'url' => 'https://www.instagram.com/ocusafeglasses']);
        // The migration's "Quarterly access review" routine links to OmniVEN itself, which is never checked.
        $this->assertStringStartsWith(rtrim(config('app.url'), '/'), Routine::where('title', 'Quarterly access review')->value('link'));
    }

    private function fakeSites(): void
    {
        Http::fake(function (HttpRequest $r) {
            return match (parse_url($r->url(), PHP_URL_HOST)) {
                'good.example.com' => Http::response('ok', 200),
                'gone.example.com' => Http::response('nope', 404),
                'www.instagram.com' => Http::response('login', 429),
                'slack.com' => Http::response(['ok' => true, 'channels' => [['id' => 'C1', 'name' => 'marketing']], 'ts' => '1.1']),
                default => throw new ConnectionException('cURL error 6: Could not resolve host: down.example.com'),
            };
        });
    }

    public function test_classifies_links_and_skips_omniven_itself(): void
    {
        $this->fakeSites();
        LinkChecker::run();

        $byUrl = LinkCheck::all()->groupBy('url');
        $this->assertSame('ok', $byUrl['https://good.example.com/']->first()->status);
        $this->assertSame('failing', $byUrl['https://gone.example.com/page']->first()->status);
        $this->assertSame(404, $byUrl['https://gone.example.com/page']->first()->http_code);
        $this->assertSame('Website not found', $byUrl['https://down.example.com/']->first()->error);
        $this->assertSame('blocked', $byUrl['https://www.instagram.com/ocusafeglasses']->first()->status);
        $this->assertFalse(LinkCheck::where('item_type', 'routine')->exists());
        // The brand site and the tool share a URL: fetched once, recorded for both.
        $this->assertCount(2, $byUrl['https://good.example.com/']);
        $this->assertCount(1, collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'good.example.com')));
    }

    public function test_only_broken_after_two_nights_in_a_row(): void
    {
        $this->fakeSites();
        LinkChecker::run();
        $this->assertSame(0, LinkCheck::broken()->count());

        LinkChecker::run();
        $this->assertEqualsCanonicalizing(["tool:{$this->gone->id}", "tool:{$this->down->id}"], LinkCheck::broken()->get()->map->key()->all());

        // Fixed: back to OK and the streak resets.
        $this->gone->update(['url' => 'https://good.example.com/new']);
        $this->down->update(['url' => 'https://good.example.com/other']);
        LinkChecker::run();
        $this->assertSame(0, LinkCheck::broken()->count());
        $this->assertFalse(LinkCheck::where('url', 'https://gone.example.com/page')->exists());
    }

    public function test_slack_note_only_when_links_newly_break(): void
    {
        config(['omniven.access_slack_channel' => 'marketing', 'services.slack_app.bot_token' => 'xoxb-test']);
        $this->fakeSites();
        $posts = fn () => collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'chat.postMessage'));

        LinkChecker::run();
        $this->assertCount(0, $posts());

        LinkChecker::run();
        $this->assertCount(1, $posts());
        $text = $posts()->first()[0]['text'];
        $this->assertStringContainsString('2 links stopped working', $text);
        $this->assertStringContainsString('Gone Tool', $text);

        // Still broken the next night: no repeat note.
        LinkChecker::run();
        $this->assertCount(1, $posts());
    }

    public function test_admins_see_the_panel_and_marks_members_do_not(): void
    {
        $this->fakeSites();
        LinkChecker::run();
        LinkChecker::run();

        $admin = User::factory()->admin()->create(['is_active' => true]);
        $this->actingAs($admin)->get('/')->assertOk()
            ->assertSee('Broken links')->assertSee('Gone Tool')->assertSee('Page not found (404)')->assertSee('Link broken')
            ->assertSee('couldn’t be checked', false);
        $this->actingAs($admin)->get('/access/review')->assertOk()->assertSee('Website not found');

        $member = User::factory()->create(['is_active' => true]);
        $this->actingAs($member)->get('/')->assertOk()->assertDontSee('Broken links')->assertDontSee('Link broken');
        $this->actingAs($member)->get('/brands/ocusafe')->assertOk()->assertDontSee('Link broken');
    }

    public function test_check_now_is_admin_only_and_runs_the_check(): void
    {
        $this->fakeSites();
        $this->withoutDefer();
        $this->actingAs(User::factory()->create(['is_active' => true]))->post('/links/check')->assertForbidden();
        $this->assertSame(0, LinkCheck::count());

        $this->actingAs(User::factory()->admin()->create(['is_active' => true]))->post('/links/check')->assertRedirect(route('dashboard').'#access');
        $this->assertGreaterThan(0, LinkCheck::count());
    }

    public function test_artisan_command_reports_a_summary(): void
    {
        $this->fakeSites();
        $this->assertSame(0, Artisan::call('omniven:check-links'));
        $this->assertStringContainsString('Checked 6 links', Artisan::output());
    }

    public function test_panel_works_before_any_check(): void
    {
        $this->actingAs(User::factory()->admin()->create(['is_active' => true]))->get('/')->assertOk()->assertSee('Not checked yet');
    }
}
