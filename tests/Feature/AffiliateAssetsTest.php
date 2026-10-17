<?php

namespace Tests\Feature;

use App\Models\AffiliateAsset;
use App\Models\Brand;
use App\Models\Publisher;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AffiliateAssetsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $kay;
    private Brand $dg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->kay = User::factory()->create(['email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->dg = Brand::where('slug', '39dg')->first();
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(300, 250);
        ob_start();
        imagepng($im);
        $bytes = ob_get_clean();

        return UploadedFile::fake()->createWithContent('Holiday banner.png', $bytes);
    }

    public function test_admins_add_assets_and_everyone_copies_them(): void
    {
        $this->actingAs($this->kay)->post('/assets', ['kind' => 'code', 'title' => 'x', 'body' => 'X'])->assertForbidden();

        $this->actingAs($this->admin)->post('/assets', ['kind' => 'image', 'title' => 'Holiday banner 300×250', 'brand_id' => $this->dg->id, 'file' => $this->png()])->assertSessionHasNoErrors();
        $img = AffiliateAsset::first();
        $this->assertSame([300, 250, 'image/png'], [$img->width, $img->height, $img->file_mime]);
        $this->actingAs($this->admin)->post('/assets', ['kind' => 'code', 'title' => '15% off frames', 'body' => ' save15 ', 'brand_id' => $this->dg->id, 'ends_on' => '2026-12-31']);
        $this->actingAs($this->admin)->post('/assets', ['kind' => 'code', 'title' => 'Summer', 'body' => 'SUMMER', 'ends_on' => '2026-08-31']);
        $this->actingAs($this->admin)->post('/assets', ['kind' => 'copy', 'title' => 'Short description', 'body' => '39DollarGlasses has sold prescription glasses online for 26 years.']);
        $this->assertSame('SAVE15', AffiliateAsset::where('title', '15% off frames')->value('body'));

        $html = $this->actingAs($this->kay)->get('/assets')->assertOk()->getContent();
        $this->assertStringContainsString('value="SAVE15"', $html);
        $this->assertStringContainsString('data-copy="#as-', $html);
        $this->assertStringContainsString('300×250', $html);
        $this->assertStringNotContainsString('SUMMER', $html, 'ended codes hidden by default');
        $this->assertStringContainsString('SUMMER', $this->actingAs($this->kay)->get('/assets?ended=1')->getContent());

        $file = $this->actingAs($this->kay)->get("/assets/{$img->id}/file?download=1")->assertOk();
        $this->assertSame('image/png', $file->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="Holiday-banner.png"', $file->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $file->headers->get('X-Content-Type-Options'));
        $this->assertSame(imagecreatefromstring($file->getContent()) !== false, true, 'bytes round-trip');
    }

    public function test_only_real_images_are_accepted(): void
    {
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($this->admin)->post('/assets', ['kind' => 'image', 'title' => 'Bad', 'file' => $svg])->assertSessionHasErrors('file');
        $fake = UploadedFile::fake()->createWithContent('x.png', 'not really a png');
        $this->actingAs($this->admin)->post('/assets', ['kind' => 'image', 'title' => 'Bad', 'file' => $fake])->assertSessionHasErrors('file');
        $this->actingAs($this->admin)->post('/assets', ['kind' => 'code', 'title' => 'No code'])->assertSessionHasErrors('body');
        $this->assertSame(0, AffiliateAsset::count());
    }

    public function test_a_shared_kit_shows_the_right_assets_without_signing_in(): void
    {
        $aav = Publisher::create(['name' => 'All About Vision', 'type' => 'review', 'stage' => 'replied']);
        $other = Publisher::create(['name' => 'Other Site', 'type' => 'blog', 'stage' => 'replied']);
        $this->actingAs($this->admin)->post('/assets', ['kind' => 'image', 'title' => 'Banner', 'brand_id' => $this->dg->id, 'file' => $this->png()]);
        $banner = AffiliateAsset::first();
        foreach ([['Shared code', 'SAVE15', null, false], ['AAV only', 'AAV20', $aav->id, false], ['Other only', 'OTHER20', $other->id, false], ['Staff note', 'INTERNAL', null, true]] as [$t, $code, $pid, $internal]) {
            $this->actingAs($this->admin)->post('/assets', ['kind' => 'code', 'title' => $t, 'body' => $code, 'brand_id' => $this->dg->id, 'publisher_id' => $pid, 'internal' => $internal ? 1 : null]);
        }
        $ocusafe = Brand::where('slug', 'ocusafe')->first();
        $this->actingAs($this->admin)->post('/assets', ['kind' => 'code', 'title' => 'Ocusafe', 'body' => 'SAFE10', 'brand_id' => $ocusafe->id]);

        $this->actingAs($this->admin)->post('/assets/share', ['brand_id' => $this->dg->id, 'publisher_id' => $aav->id, 'days' => 30])->assertSessionHas('kit_link');
        $link = session('kit_link');
        $this->assertStringContainsString('/kit?', $link);

        auth()->logout();
        $html = $this->get($link)->assertOk()->getContent();
        $this->assertStringContainsString('SAVE15', $html);
        $this->assertStringContainsString('AAV20', $html);
        $this->assertStringNotContainsString('OTHER20', $html);
        $this->assertStringNotContainsString('INTERNAL', $html);
        $this->assertStringNotContainsString('SAFE10', $html, 'other brands left out');
        $this->assertStringNotContainsString('Edit', $html);

        // The banner loads through its own signed link; a tampered or unsigned one doesn't.
        preg_match('#src="([^"]+/kit/'.$banner->id.'[^"]*)"#', $html, $m);
        $this->get(html_entity_decode($m[1]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get("/kit/{$banner->id}")->assertForbidden();
        $this->get(str_replace('p='.$aav->id, 'p='.$other->id, $link))->assertForbidden();
        $this->get("/assets/{$banner->id}/file")->assertRedirect();

        // Expired.
        $this->travel(31)->days();
        $this->get($link)->assertForbidden();
    }

    public function test_kit_file_refuses_assets_outside_the_kit(): void
    {
        $this->actingAs($this->admin)->post('/assets', ['kind' => 'image', 'title' => 'Staff mockup', 'file' => $this->png(), 'internal' => 1]);
        $a = AffiliateAsset::first();
        $url = URL::temporarySignedRoute('kit.file', now()->addDay(), ['asset' => $a->id]);
        auth()->logout();
        $this->get($url)->assertNotFound();
    }

    public function test_publisher_codes_show_on_their_outreach_page(): void
    {
        $aav = Publisher::create(['name' => 'All About Vision', 'type' => 'review', 'stage' => 'replied']);
        $this->actingAs($this->admin)->post('/assets', ['kind' => 'code', 'title' => 'Their readers', 'body' => 'AAV20', 'publisher_id' => $aav->id]);
        $html = $this->actingAs($this->kay)->get("/outreach/{$aav->id}")->getContent();
        $this->assertStringContainsString('AAV20', $html);
        $this->assertStringNotContainsString('Make a kit link', $html, 'admins make links');
        $this->assertStringContainsString('Make a kit link', $this->actingAs($this->admin)->get("/outreach/{$aav->id}")->getContent());
    }
}
