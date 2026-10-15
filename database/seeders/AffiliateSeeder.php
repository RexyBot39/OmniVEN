<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Tool;
use Illuminate\Database\Seeder;

/**
 * The affiliate program runs through two networks, CJ (Commission Junction) and Awin
 * (links from Marc, Oct 2026). Adds both under the Affiliate category; never overwrites a tool an admin has edited.
 */
class AffiliateSeeder extends Seeder
{
    public const CJ = 'https://members.cj.com/member/advertiser/home.do#/';

    public const AWIN = 'https://app.awin.com/en/awin/advertiser/95501/home';

    public function run(): void
    {
        $tools = [
            ['CJ Affiliate (Commission Junction)', self::CJ, 'Affiliate network: publishers, commissions, transactions and payouts.', 1, null],
            ['Awin', self::AWIN, 'Affiliate network: publishers, commissions, transactions and payouts.', 2, 'Advertiser ID: 95501.'],
        ];
        // A fresh install gets these from the starter seed, which calls this seeder after adding brands.
        $brand = Brand::where('slug', '39dg')->value('id');
        if (! $brand) {
            return;
        }

        foreach ($tools as [$name, $url, $desc, $sort, $note]) {
            $tool = Tool::firstOrNew(['name' => $name]);
            if ($tool->exists) {
                continue;
            }
            $tool->fill(['url' => $url, 'category' => 'Affiliate', 'description' => $desc, 'status' => 'active', 'all_brands' => false, 'sort' => $sort, 'access_note' => $note])->save();
            $tool->brands()->sync([$brand]);
        }
    }
}
