<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * Where each smaller brand lives (confirmed by Marc, Oct 2026). Only fills a website or
 * notes that are still empty, so anything an admin has entered is kept.
 */
class BrandDetailsSeeder extends Seeder
{
    /** brand slug => [site, notes] */
    public const DETAILS = [
        'lumeo' => ['https://www.39dollarglasses.com', 'Part of 39dollarglasses.com. Its social accounts are the 39DollarGlasses ones.'],
        'lensreplacement' => ['https://www.39dollarglasses.com/help/re-lens/your-old-frame', 'Part of 39dollarglasses.com. Its social accounts are the 39DollarGlasses ones.'],
        'trailview' => ['https://trailview.co', 'trailview.co redirects to the Trailview pages on 39dollarglasses.com.'],
    ];

    public function run(): void
    {
        foreach (self::DETAILS as $slug => [$site, $notes]) {
            $brand = Brand::where('slug', $slug)->first();
            if (! $brand) {
                continue;
            }
            $brand->site = $brand->site ?: $site;
            $brand->notes = $brand->notes ?: $notes;
            $brand->save();
        }
    }
}
