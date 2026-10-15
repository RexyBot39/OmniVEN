<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\Tool;
use App\Models\User;
use Database\Seeders\BrandLinkedInSeeder;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandLinkedInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    private function brand(string $slug): Brand
    {
        return Brand::where('slug', $slug)->firstOrFail();
    }

    public function test_each_linkedin_page_is_a_brand_account_with_its_admin_link(): void
    {
        $expect = [
            '39dg' => 'https://www.linkedin.com/company/1945733/admin/dashboard/',
            'ocusafe' => 'https://www.linkedin.com/company/93311139/admin/dashboard/',
            'ocusleep' => 'https://www.linkedin.com/company/95589338/admin/dashboard/',
            'neurolux' => 'https://www.linkedin.com/showcase/104508825/admin/dashboard/',
        ];
        foreach ($expect as $slug => $url) {
            $a = Account::where(['brand_id' => $this->brand($slug)->id, 'platform' => 'linkedin'])->firstOrFail();
            $this->assertSame($url, $a->url);
        }
        $this->assertSame('104508825', Account::where('url', $expect['neurolux'])->value('account_ref'));
        $this->assertSame('Showcase page', Account::where('url', $expect['neurolux'])->value('handle'));
        $this->assertSame(4, Account::where('platform', 'linkedin')->count());
        $this->assertFalse(Account::where(['brand_id' => $this->brand('trailview')->id, 'platform' => 'linkedin'])->exists());
    }

    public function test_public_page_and_channel_added_to_the_brand(): void
    {
        $dg = $this->brand('39dg');
        $this->assertContains('https://www.linkedin.com/company/1945733/', collect($dg->socials)->pluck('url')->all());
        $this->assertCount(6, $dg->socials); // the 5 footer profiles + LinkedIn
        $this->assertStringContainsString('LinkedIn', $dg->channels);
        $this->assertSame('LinkedIn', $this->brand('ocusleep')->channels);
        $this->assertStringNotContainsString('LinkedIn', (string) $this->brand('trailview')->channels);
    }

    public function test_tool_routine_and_grid_column(): void
    {
        $tool = Tool::where('name', 'LinkedIn')->firstOrFail();
        $this->assertEqualsCanonicalizing(['39dg', 'ocusafe', 'ocusleep', 'neurolux'], $tool->brands->pluck('slug')->all());
        $this->assertStringContainsString('LinkedIn', Routine::where('title', 'Answer comments and DMs')->value('description'));
        $this->assertTrue(config('omniven.platforms.linkedin.grid'));

        $admin = User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        $html = $this->actingAs($admin)->get('/')->assertOk()->getContent();
        preg_match('#<tr data-brand-row="neurolux".*?</tr>#s', $html, $row);
        $this->assertStringContainsString('https://www.linkedin.com/showcase/104508825/admin/dashboard/', $row[0]);
        // Lumeo shares 39DollarGlasses' accounts, so its row links to the 39DollarGlasses page.
        preg_match('#<tr data-brand-row="lumeo".*?</tr>#s', $html, $row);
        $this->assertStringContainsString('https://www.linkedin.com/company/1945733/admin/dashboard/', $row[0]);

        $this->actingAs($admin)->get('/brands/ocusafe')->assertOk()->assertSee('https://www.linkedin.com/company/93311139/admin/dashboard/', false)->assertDontSee('+ LinkedIn');
        $this->actingAs($admin)->get('/brands/trailview')->assertOk()->assertSee('+ LinkedIn');
    }

    public function test_running_again_keeps_admin_edits(): void
    {
        $ocusafe = $this->brand('ocusafe');
        $acct = Account::where(['brand_id' => $ocusafe->id, 'platform' => 'linkedin'])->first();
        $acct->update(['handle' => 'Ocusafe Safety Glasses', 'owner' => 'Leona']);
        $ocusafe->update(['channels' => 'Instagram only']);
        Routine::where('title', 'Answer comments and DMs')->update(['description' => 'Custom text']);

        (new BrandLinkedInSeeder)->run();

        $this->assertSame(4, Account::where('platform', 'linkedin')->count());
        $this->assertSame('Ocusafe Safety Glasses', $acct->fresh()->handle);
        $this->assertSame('Instagram only, LinkedIn', $ocusafe->fresh()->channels);
        $this->assertSame(1, collect($ocusafe->fresh()->socials)->filter(fn ($s) => str_contains($s['url'], 'linkedin'))->count());
        $this->assertSame('Custom text', Routine::where('title', 'Answer comments and DMs')->value('description'));
        $this->assertSame(1, Tool::where('name', 'LinkedIn')->count());
    }
}
