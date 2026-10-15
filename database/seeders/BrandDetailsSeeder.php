<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * Where each smaller brand lives and whose accounts it uses (confirmed by Marc, Oct 2026).
 * Only fills what is still empty, so anything an admin has entered is kept.
 */
class BrandDetailsSeeder extends Seeder
{
    /** brand slug => [site, notes] */
    public const DETAILS = [
        'lumeo' => ['https://www.39dollarglasses.com', 'Part of 39dollarglasses.com. Its social accounts are the 39DollarGlasses ones.'],
        'lensreplacement' => ['https://www.39dollarglasses.com/help/re-lens/your-old-frame', 'Part of 39dollarglasses.com. Its social accounts are the 39DollarGlasses ones.'],
        'trailview' => ['https://trailview.co', 'trailview.co redirects to the Trailview pages on 39dollarglasses.com.'],
    ];

    /** brand slug => slug of the brand whose accounts it uses */
    public const SHARES_ACCOUNTS_WITH = [
        'lumeo' => '39dg',
        'lensreplacement' => '39dg',
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

        foreach (self::SHARES_ACCOUNTS_WITH as $slug => $fromSlug) {
            $brand = Brand::where('slug', $slug)->first();
            $from = Brand::where('slug', $fromSlug)->first();
            if ($brand && $from && ! $brand->accounts_from_id && ! $brand->accounts()->exists()) {
                $brand->update(['accounts_from_id' => $from->id]);
            }
        }
    }
}
