<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    private function brand(): Brand
    {
        return Brand::where('slug', 'ocusafe')->first() ?? Brand::first();
    }

    public function test_admin_can_create_edit_verify_and_delete_account(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $brand = $this->brand();

        $this->actingAs($admin)->get("/accounts/create?brand={$brand->slug}&platform=instagram")
            ->assertOk()->assertSee('Instagram');

        $this->actingAs($admin)->post('/accounts', [
            'brand_id' => $brand->id, 'platform' => 'instagram', 'handle' => '@testhandle',
            'url' => 'https://www.instagram.com/testhandle/', 'owner' => 'Leona', 'return' => 'hub',
        ])->assertRedirect(route('brands.show', $brand));

        $acct = Account::where('handle', '@testhandle')->firstOrFail();
        $this->assertSame('instagram', $acct->platform);

        $this->actingAs($admin)->put("/accounts/{$acct->id}", [
            'brand_id' => $brand->id, 'platform' => 'instagram', 'handle' => '@renamed',
            'url' => 'https://www.instagram.com/renamed/',
        ])->assertRedirect();
        $this->assertSame('@renamed', $acct->fresh()->handle);

        $this->actingAs($admin)->post("/accounts/{$acct->id}/verify")->assertRedirect();
        $this->assertTrue($acct->fresh()->verified_on->isToday());
        $this->assertFalse($acct->fresh()->needsVerify());

        $this->actingAs($admin)->delete("/accounts/{$acct->id}")->assertRedirect();
        $this->assertNull(Account::find($acct->id));
    }

    public function test_account_validation_rejects_bad_platform_and_url(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $before = Account::count();
        $this->actingAs($admin)->post('/accounts', ['platform' => 'myspace', 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors(['platform', 'url']);
        $this->assertSame($before, Account::count());
    }

    public function test_staff_cannot_manage_accounts_but_can_view_hub(): void
    {
        $staff = User::factory()->create(['is_active' => true]);
        $brand = $this->brand();
        $acct = Account::create(['brand_id' => $brand->id, 'platform' => 'tiktok', 'handle' => '@ocu']);

        $this->actingAs($staff)->get('/accounts/create')->assertForbidden();
        $this->actingAs($staff)->post('/accounts', ['platform' => 'tiktok'])->assertForbidden();
        $this->actingAs($staff)->delete("/accounts/{$acct->id}")->assertForbidden();
        $this->actingAs($staff)->post("/accounts/{$acct->id}/verify")->assertForbidden();

        $this->actingAs($staff)->get("/brands/{$brand->slug}")
            ->assertOk()->assertSee($brand->name)->assertSee('@ocu')
            ->assertDontSee('+ Add account')->assertDontSee('Mark verified');
    }

    public function test_hub_shows_gaps_and_links_and_dashboard_shows_grid(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $brand = $this->brand();
        Account::create(['brand_id' => $brand->id, 'platform' => 'instagram', 'handle' => '@ocusafe',
            'url' => 'https://www.instagram.com/ocusafe/', 'verified_on' => now()->toDateString()]);

        $this->actingAs($admin)->get("/brands/{$brand->slug}")
            ->assertOk()
            ->assertSee('https://www.instagram.com/ocusafe/', false)
            ->assertSee('Not set up yet');

        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertSee('bp-grid', false)
            ->assertSee('https://www.instagram.com/ocusafe/', false)
            ->assertSee('Brand accounts');
    }

    public function test_launcher_lists_everything_and_respects_permissions(): void
    {
        $brand = $this->brand();
        Account::create(['brand_id' => $brand->id, 'platform' => 'meta_ads', 'account_ref' => 'act_123']);

        $staff = User::factory()->create(['is_active' => true]);
        $items = collect($this->actingAs($staff)->getJson('/launcher.json')->assertOk()->json());
        $titles = $items->pluck('t');
        $this->assertTrue($titles->contains('Routines'));
        $this->assertTrue($titles->contains(fn ($t) => str_contains($t, 'Meta Ads')));
        $this->assertTrue($titles->contains('Google Merchant Center'));
        $this->assertFalse($titles->contains('Activity report'));
        $this->assertFalse($titles->contains('People'));
        $this->assertTrue($items->contains(fn ($i) => $i['u'] === route('brands.show', $brand)));

        $marc = User::where('email', 'drmarcweinstein@39dollarglasses.com')->first()
            ?? User::factory()->admin()->create(['email' => 'drmarcweinstein@39dollarglasses.com']);
        $marc->forceFill(['is_active' => true, 'is_admin' => true])->save();
        $titles = collect($this->actingAs($marc)->getJson('/launcher.json')->json())->pluck('t');
        $this->assertTrue($titles->contains('Activity report'));
        $this->assertTrue($titles->contains('People'));
    }

    public function test_guests_cannot_see_launcher_or_hub(): void
    {
        $this->get('/launcher.json')->assertRedirect();
        $this->get('/brands/'.$this->brand()->slug)->assertRedirect();
    }
}
