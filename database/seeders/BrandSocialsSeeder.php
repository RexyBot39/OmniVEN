<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * Public social profiles linked from each brand site's footer (confirmed by Marc, Oct 2026).
 * Only adds what is missing: an account already on that brand + platform, or social links an
 * admin has already entered, are left alone. Verified dates stay empty for the Access review.
 */
class BrandSocialsSeeder extends Seeder
{
    /** brand slug => [platform, handle, url] */
    public const SOCIALS = [
        '39dg' => [
            ['facebook', '39dollarglasses', 'https://www.facebook.com/39dollarglasses/'],
            ['instagram', '@39dollarglasses', 'https://www.instagram.com/39dollarglasses/'],
            ['tiktok', '@39dollarglasses', 'https://www.tiktok.com/@39dollarglasses'],
            ['youtube', '39dollarglassesVideo', 'https://www.youtube.com/user/39dollarglassesVideo'],
            ['pinterest', '39dollarglasses', 'https://www.pinterest.com/39dollarglasses/'],
        ],
        'ocusafe' => [
            ['facebook', 'OcusafeGlasses', 'https://www.facebook.com/OcusafeGlasses'],
            ['instagram', '@ocusafeglasses', 'https://www.instagram.com/ocusafeglasses'],
        ],
        'ocusleep' => [
            ['facebook', 'Ocusleep', 'https://www.facebook.com/Ocusleep'],
            ['x', '@ocusleep', 'https://x.com/ocusleep'],
            ['instagram', '@ocusleep', 'https://www.instagram.com/ocusleep'],
        ],
        'trailview' => [
            ['instagram', '@trailview.co', 'https://www.instagram.com/trailview.co/'],
        ],
    ];

    public function run(): void
    {
        $labels = array_map(fn ($p) => $p['label'], config('omniven.platforms'));

        foreach (self::SOCIALS as $slug => $profiles) {
            $brand = Brand::where('slug', $slug)->first();
            if (! $brand) {
                continue;
            }
            foreach ($profiles as [$platform, $handle, $url]) {
                Account::firstOrCreate(
                    ['brand_id' => $brand->id, 'platform' => $platform],
                    ['handle' => $handle, 'url' => $url, 'notes' => 'Linked from the brand website footer.'],
                );
            }
            // The Brands tab "Social" line: fill only if nobody has entered any yet.
            if (empty($brand->socials)) {
                $brand->update(['socials' => array_map(fn ($p) => ['label' => $labels[$p[0]] ?? $p[0], 'url' => $p[2]], $profiles)]);
            }
        }
    }
}
