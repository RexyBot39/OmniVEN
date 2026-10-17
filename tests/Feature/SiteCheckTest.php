<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\SitePage;
use App\Models\User;
use App\Support\Seo\ProductFeed;
use App\Support\Seo\SiteCheck;
use App\Support\Seo\StructuredData;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SiteCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->staff = User::factory()->create(['email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function page(array $ld, string $extra = ''): string
    {
        return '<html><head>'.$extra.'<script type="application/ld+json">'.json_encode($ld).'</script></head><body></body></html>';
    }

    private function product(array $over = []): array
    {
        return array_replace_recursive(['@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'Round frames', 'image' => 'https://x/a.jpg',
            'description' => 'Light round frames', 'sku' => 'RF1', 'brand' => ['@type' => 'Brand', 'name' => '39DollarGlasses'],
            'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => '4.7', 'reviewCount' => '210'],
            'offers' => ['@type' => 'Offer', 'price' => '39.00', 'priceCurrency' => 'USD', 'availability' => 'https://schema.org/InStock',
                'hasMerchantReturnPolicy' => ['@type' => 'MerchantReturnPolicy']]], $over);
    }

    public function test_product_pages(): void
    {
        $url = 'https://www.39dollarglasses.com/frames/round';
        $this->assertSame('ok', StructuredData::analyze($this->page($this->product()), 'product', $url)['status']);

        $bad = $this->product(['offers' => ['price' => '', 'availability' => 'in stock']]);
        unset($bad['aggregateRating']);
        $a = StructuredData::analyze($this->page(['@context' => 'https://schema.org', '@graph' => [['@type' => 'WebSite'], $bad]]), 'product', $url);
        $this->assertSame('fail', $a['status']);
        $texts = array_column($a['issues'], 1);
        $this->assertContains('The offer has no usable price.', $texts);
        $this->assertContains('No rating (aggregateRating), so no stars in search results.', $texts);
        $this->assertStringContainsString('isn’t a value Google knows', implode(' ', $texts));
        $this->assertSame(['WebSite', 'Product'], $a['found'], '@graph is read');

        $none = StructuredData::analyze('<html><div itemscope itemtype="https://schema.org/Product"></div></html>', 'product', $url);
        $this->assertStringContainsString('microdata', $none['issues'][0][1]);

        $broken = StructuredData::analyze('<script type="application/ld+json">{"@type": "Product",}</script>', 'product', $url);
        $this->assertStringContainsString('syntax error', implode(' ', array_column($broken['issues'], 1)));

        $hidden = StructuredData::analyze($this->page($this->product(), '<meta name="robots" content="noindex,follow"><link rel="canonical" href="https://www.39dollarglasses.com/frames">'), 'product', $url);
        $this->assertSame('fail', $hidden['status']);
        $this->assertStringContainsString('robots noindex', $hidden['issues'][0][1]);
        $this->assertStringContainsString('canonical tag points to another page (/frames)', $hidden['issues'][1][1]);
    }

    public function test_home_and_article_pages(): void
    {
        $home = StructuredData::analyze($this->page(['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'Organization', 'name' => '39DG', 'logo' => 'https://x/logo.png', 'sameAs' => ['https://instagram.com/39dg']], ['@type' => 'WebSite', 'name' => '39DG']]]), 'home', 'https://www.39dollarglasses.com/');
        $this->assertSame('ok', $home['status']);
        $article = StructuredData::analyze($this->page(['@type' => 'BlogPosting', 'headline' => 'Blue light, explained', 'image' => 'x', 'datePublished' => '2026-10-01', 'author' => ['name' => 'Marc']]), 'article', 'https://x/blog/a');
        $this->assertSame('ok', $article['status'], 'tips alone don’t lower the status');
        $this->assertSame('info', $article['issues'][0][0]);
    }

    public function test_feed_xml_and_sheet(): void
    {
        $xml = '<?xml version="1.0"?><rss xmlns:g="http://base.google.com/ns/1.0" version="2.0"><channel>'
            .'<item><g:id>A1</g:id><title>Round frames</title><link>https://x/a</link><g:image_link>https://x/a.jpg</g:image_link><g:price>39.00 USD</g:price><g:availability>in_stock</g:availability><description>d</description><g:brand>39DG</g:brand><g:mpn>RF1</g:mpn><g:condition>new</g:condition><g:shipping><g:price>0 USD</g:price></g:shipping></item>'
            .'<item><g:id>A2</g:id><title>Square</title><link>http://x/b</link><g:price>$39</g:price><g:availability>available</g:availability></item>'
            .'<item><g:id>A1</g:id><title>Dup</title><link>https://x/c</link><g:image_link>https://x/c.jpg</g:image_link><g:price>12.5 USD</g:price><g:availability>out_of_stock</g:availability><g:identifier_exists>no</g:identifier_exists></item>'
            .'</channel></rss>';
        $f = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($f, $xml);
        $r = ProductFeed::check($f);
        $this->assertSame(3, $r['items']);
        $issues = collect($r['issues'])->keyBy('key');
        $this->assertSame(1, $issues['image_link']['count']);
        $this->assertSame(['A2'], $issues['price_format']['examples']);
        $this->assertSame(1, $issues['availability_value']['count']);
        $this->assertSame(['A1'], $issues['duplicate_id']['examples']);
        $this->assertSame(1, $issues['identifier']['count'], 'identifier_exists=no excuses A1 dup; A2 has none');
        $this->assertSame(1, $issues['http_link']['count']);
        $this->assertSame('fail', $r['issues'][0]['level'], 'rejections first');
        $this->assertSame(['in_stock' => 1, 'out_of_stock' => 1], $r['availability']);

        file_put_contents($f, "id\ttitle\tlink\timage_link\tprice\tavailability\tdescription\tbrand\tgtin\tcondition\nB1\tFrames\thttps://x\thttps://x.jpg\t39.00 USD\tin stock\td\tb\t0123456789012\tnew\n");
        $this->assertSame([], ProductFeed::check($f)['issues']);
        unlink($f);
    }

    public function test_admins_keep_the_page_list_and_everyone_sees_results(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn (Request $r) => str_contains($r->url(), '39dollarglasses.com') ? Http::response($this->page($this->product(['offers' => ['price' => '0']]))) : Http::response('nope', 404));
        $brand = Brand::where('slug', '39dg')->first();

        $this->actingAs($this->staff)->post('/site-check/pages', ['url' => 'https://www.39dollarglasses.com/frames/round', 'kind' => 'product'])->assertForbidden();
        $this->actingAs($this->admin)->post('/site-check/pages', ['url' => 'https://www.39dollarglasses.com/frames/round', 'kind' => 'product', 'brand_id' => $brand->id])->assertSessionHas('status', 'Added and checked.');
        $this->assertSame('fail', SitePage::first()->status);
        $this->actingAs($this->admin)->post('/site-check/pages', ['url' => 'https://www.39dollarglasses.com/frames/round', 'kind' => 'product'])->assertSessionHasErrors('url');

        $this->actingAs($this->admin)->post('/site-check/homes');
        $this->assertTrue(SitePage::where('kind', 'home')->exists());
        $this->assertTrue(SitePage::where('kind', 'home')->where('status', 'error')->where('error', 'The page answered HTTP 404.')->exists(), 'other brands’ sites 404 in this fake');

        $html = $this->actingAs($this->staff)->get('/site-check')->assertOk()->getContent();
        $this->assertStringContainsString('The price is 0.', $html);
        $this->assertStringContainsString('www.39dollarglasses.com/frames/round', $html);
        $this->assertStringContainsString('search.google.com/test/rich-results?url=', $html);
        $this->assertStringContainsString('Make a task', $html);
        $this->assertStringNotContainsString('Add page', $html, 'staff don’t edit the list');
        $this->assertStringContainsString('No product feeds connected', $html);
    }

    public function test_feed_check_keeps_counts_and_explains_failures(): void
    {
        config(['site_check.feeds' => ['39dg' => 'https://www.39dollarglasses.com/feeds/google.xml']]);
        $status = 200;
        Http::preventStrayRequests();
        Http::fake(function () use (&$status) {
            return $status === 200
                ? Http::response('<rss xmlns:g="http://base.google.com/ns/1.0"><channel><item><g:id>A1</g:id><title>T</title><link>https://x</link><g:price>39.00 USD</g:price><g:availability>in_stock</g:availability></item></channel></rss>')
                : Http::response('no', $status);
        });
        SiteCheck::run();
        $r = Cache::get('site-check:feed:39dg');
        $this->assertSame(1, $r['items']);
        $this->assertNull($r['error']);
        $html = $this->actingAs($this->staff)->get('/site-check')->getContent();
        $this->assertStringContainsString('1 problem Google rejects products for.', $html);
        $this->assertStringContainsString('No image', $html);

        $status = 403;
        SiteCheck::run();
        $r = Cache::get('site-check:feed:39dg');
        $this->assertSame(1, $r['items'], 'last good numbers kept');
        $this->assertStringContainsString('HTTP 403', $r['error']);
        $this->assertStringContainsString('The last check failed', $this->actingAs($this->admin)->get('/site-check')->getContent());
    }
}
