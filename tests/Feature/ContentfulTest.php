<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\Tool;
use App\Models\User;
use App\Support\BrandIcon;
use App\Support\Launchpad;
use Database\Seeders\ContentfulSeeder;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentfulTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://app.contentful.com/spaces/n0xgoet11vmg/views/entries';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    public function test_tool_accounts_and_routine_open_the_entries_list(): void
    {
        $tool = Tool::where('name', 'Contentful')->firstOrFail();
        $this->assertSame([self::URL, 'Blog'], [$tool->url, $tool->category]);
        $this->assertEqualsCanonicalizing(['39dg', 'ocusafe'], $tool->brands->pluck('slug')->all());

        foreach (['39dg', 'ocusafe'] as $slug) {
            $acct = Account::where(['brand_id' => Brand::where('slug', $slug)->value('id'), 'platform' => 'contentful'])->firstOrFail();
            $this->assertSame(self::URL, $acct->openUrl());
            $this->assertSame('Blog & Content', config('omniven.platform_groups.'.$acct->group()));
        }

        $r = Routine::where('title', 'Update the blogs in Contentful')->firstOrFail();
        $this->assertSame(['weekly', self::URL], [$r->cadence, $r->link]);
        $this->assertEqualsCanonicalizing(['39dg', 'ocusafe'], $r->brands->pluck('slug')->all());
    }

    public function test_logo_shows_on_the_tools_tab_hubs_and_launcher(): void
    {
        $this->assertSame('contentful', BrandIcon::forUrl(self::URL));
        $this->assertSame('contentful', BrandIcon::forPlatform('contentful'));
        $this->assertSame(['contentful'], BrandIcon::mentions('Update the blogs in Contentful'));
        $this->assertStringContainsString('id="bi-contentful"', file_get_contents(public_path('images/brand-icons.svg')));

        $user = User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('#bi-contentful', false)
            ->assertSee('data-cat="blog"', false)
            ->assertSee('data-star="category:'.Launchpad::categoryId('Blog').'"', false);
        $this->actingAs($user)->get('/brands/ocusafe')->assertOk()->assertSee('Blog &amp; Content', false)->assertSee(e(self::URL), false);

        $items = collect($this->actingAs($user)->getJson('/launcher.json')->json());
        $this->assertTrue($items->contains(fn ($i) => $i['t'] === 'Contentful' && $i['u'] === self::URL && ($i['ic']['s'] ?? null) === 'contentful'));
        $this->assertTrue($items->contains(fn ($i) => $i['k'] === 'Category' && $i['t'] === 'Blog'));
    }

    public function test_help_explains_the_blog_workflow(): void
    {
        $user = User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($user)->get('/help')->assertOk()->assertSee('Publishing a blog post')->assertSee('contentful');
    }

    public function test_running_again_keeps_admin_edits(): void
    {
        Tool::where('name', 'Contentful')->update(['url' => 'https://app.contentful.com/custom']);
        (new ContentfulSeeder)->run();
        $this->assertSame('https://app.contentful.com/custom', Tool::where('name', 'Contentful')->value('url'));
        $this->assertSame(2, Account::where('platform', 'contentful')->count());
        $this->assertSame(1, Routine::where('title', 'Update the blogs in Contentful')->count());
    }
}
