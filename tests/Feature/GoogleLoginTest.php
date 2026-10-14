<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(string $email, string $hd = '39dollarglasses.com', bool $verified = true, string $id = 'g-123', string $avatar = 'https://lh3.googleusercontent.com/a/x'): void
    {
        $gUser = (new GoogleUser)->map(['id' => $id, 'name' => 'Pat Lee', 'email' => $email, 'avatar' => $avatar])
            ->setRaw(['email' => $email, 'email_verified' => $verified, 'hd' => $hd]);

        $provider = new class($gUser) {
            public function __construct(private $u) {}
            public function user() { return $this->u; }
        };
        $factory = new class($provider) implements Socialite {
            public function __construct(private $p) {}
            public function driver($driver = null) { return $this->p; }
        };
        $this->app->instance(Socialite::class, $factory);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Sign in with Google');
    }

    public function test_company_account_can_sign_in(): void
    {
        $this->fakeGoogle('pat@39dollarglasses.com');
        $this->get('/auth/google/callback')->assertRedirect('/');
        $this->assertAuthenticated();
        $user = User::where('email', 'pat@39dollarglasses.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_admin);
    }

    public function test_configured_admin_email_becomes_admin(): void
    {
        $this->fakeGoogle('drmarcweinstein@39dollarglasses.com');
        $this->get('/auth/google/callback');
        $this->assertTrue(User::where('email', 'drmarcweinstein@39dollarglasses.com')->first()->is_admin);
    }

    public function test_gmail_account_is_rejected(): void
    {
        $this->fakeGoogle('someone@gmail.com', hd: '');
        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertSame(0, User::where('email', 'someone@gmail.com')->count());
    }

    public function test_lookalike_domain_is_rejected(): void
    {
        $this->fakeGoogle('pat@evil39dollarglasses.com', hd: 'evil39dollarglasses.com');
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_email_must_match_hosted_domain_claim(): void
    {
        // Email on our domain but token not issued by our Workspace (no hd claim).
        $this->fakeGoogle('pat@39dollarglasses.com', hd: '');
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_unverified_email_is_rejected(): void
    {
        $this->fakeGoogle('pat@39dollarglasses.com', verified: false);
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_turned_off_user_cannot_sign_in_or_keep_session(): void
    {
        $user = User::factory()->create(['email' => 'pat@39dollarglasses.com', 'google_id' => 'g-123', 'is_active' => false]);
        $this->fakeGoogle('pat@39dollarglasses.com');
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();

        $this->actingAs($user)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_very_long_google_avatar_url_is_stored(): void
    {
        $long = 'https://lh3.googleusercontent.com/a-/'.str_repeat('A', 1500).'=s96-c';
        $this->fakeGoogle('pat@39dollarglasses.com', avatar: $long);
        $this->get('/auth/google/callback')->assertRedirect('/');
        $this->assertSame($long, User::where('email', 'pat@39dollarglasses.com')->first()->avatar);
    }

    public function test_other_hostnames_redirect_to_app_url_in_production(): void
    {
        config(['app.url' => 'https://omniven.cc']);
        $this->app['env'] = 'production';
        $this->get('https://omniven-production.up.railway.app/login?x=1')
            ->assertRedirect('https://omniven.cc/login?x=1');
        $this->get('https://omniven-production.up.railway.app/up')->assertOk();
        $this->app['env'] = 'testing';
    }

    public function test_preloaded_admin_keeps_admin_on_first_sign_in(): void
    {
        $this->fakeGoogle('leona.p@39dollarglasses.com', id: 'g-leona');
        $this->get('/auth/google/callback')->assertRedirect('/');
        $leona = User::where('email', 'leona.p@39dollarglasses.com')->first();
        $this->assertSame('g-leona', $leona->google_id);
        $this->assertTrue($leona->is_admin);
        $this->assertSame(1, User::where('email', 'leona.p@39dollarglasses.com')->count());
    }
}
