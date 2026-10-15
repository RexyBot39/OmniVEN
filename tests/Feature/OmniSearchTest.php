<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Tool;
use App\Models\User;
use App\Support\BrandIcon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmniSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->me = User::factory()->create(['name' => 'Pat Member', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function search(string $q, ?User $u = null): array
    {
        $groups = $this->actingAs($u ?? $this->me)->getJson('/search.json?q='.urlencode($q))->assertOk()->json('groups');

        return collect($groups)->mapWithKeys(fn ($g) => [$g['label'] => collect($g['items'])->pluck('t')->all()])->all();
    }

    public function test_finds_brands_and_their_accounts_tools_and_routines(): void
    {
        $r = $this->search('ocusafe');
        $this->assertContains('Ocusafe', $r['Brands']);
        $this->assertTrue(collect($r['Accounts'])->contains(fn ($t) => str_starts_with($t, 'Ocusafe · ')));
        $this->assertArrayHasKey('Help', $r);

        $r = $this->search('trailview');
        $this->assertContains('Trailview', $r['Brands']);
    }

    public function test_finds_pages_categories_routines_calendar_and_tasks(): void
    {
        $this->assertContains('Affiliate', $this->search('affiliate')['Pages']);
        $this->assertContains('Action calendar', $this->search('calendar')['Pages']);
        $this->assertContains('Update the blogs in Contentful', $this->search('blogs')['Routines']);
        $this->assertNotEmpty($this->search('boost')['Action calendar'] ?? []);
        $this->assertContains('Contentful', $this->search('blog')['Tools']);

        Task::create(['title' => 'Fix the Neurolux landing page', 'assignee_id' => $this->me->id, 'created_by' => $this->me->id]);
        $this->assertContains('Fix the Neurolux landing page', $this->search('neurolux landing')['Tasks']);
        // Every word has to match.
        $this->assertArrayNotHasKey('Tasks', $this->search('neurolux zebra'));
    }

    public function test_only_your_own_sticky_notes_and_people_only_for_admins(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $other->postits()->create(['title' => 'Secret launch plan', 'color' => 'pink']);
        $this->me->postits()->create(['title' => 'My launch plan', 'color' => 'yellow']);

        $r = $this->search('launch plan');
        $this->assertSame(['My launch plan'], $r['Your sticky notes']);
        $this->assertArrayNotHasKey('People', $this->search('pat'));

        $admin = User::factory()->admin()->create(['is_active' => true]);
        $this->assertContains('Pat Member', $this->search('pat member', $admin)['People']);
        $this->assertArrayNotHasKey('Your sticky notes', $this->search('launch plan', $admin));
    }

    public function test_short_queries_return_nothing_and_guests_are_turned_away(): void
    {
        $this->getJson('/search.json?q=ocusafe')->assertUnauthorized();
        $this->assertSame([], $this->search('o'));
    }

    public function test_dashboard_search_box_is_the_omni_search(): void
    {
        $this->actingAs($this->me)->get('/')->assertOk()
            ->assertSee('id="omni"', false)
            ->assertSee('data-src="'.route('search').'"', false)
            ->assertSee('Search everything');
    }

    public function test_pulsetopost_has_its_logo(): void
    {
        $tool = Tool::where('name', 'PulseToPost')->firstOrFail();
        $this->assertSame('pulsetopost', BrandIcon::forTool($tool));
        $this->assertStringContainsString('id="bi-pulsetopost"', file_get_contents(public_path('images/brand-icons.svg')));
        $this->actingAs($this->me)->get('/')->assertOk()->assertSee('#bi-pulsetopost', false);
    }
}
