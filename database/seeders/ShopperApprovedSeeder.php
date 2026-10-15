<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\Tool;
use Illuminate\Database\Seeder;

/**
 * Shopper Approved is our main third-party review platform (confirmed by Marc, Oct 2026).
 * Points the tool and the daily reviews routine at its dashboard and adds it as a
 * 39DollarGlasses account. Only changes values that are still the original starter ones.
 */
class ShopperApprovedSeeder extends Seeder
{
    public const DASHBOARD = 'https://www.shopperapproved.com/account/dashboard';

    private const OLD_ROUTINE = 'Trustpilot, ConsumerAffairs, Shopper Approved. Escalate any chargeback, BBB, legal or prescription-error mention the same day.';

    private const NEW_ROUTINE = 'Shopper Approved first (our main review platform), then Trustpilot and ConsumerAffairs. Escalate any chargeback, BBB, legal or prescription-error mention the same day.';

    public function run(): void
    {
        $tool = Tool::where('name', 'Shopper Approved')->first();
        if ($tool) {
            if (in_array($tool->url, [null, '', 'https://www.shopperapproved.com'], true)) {
                $tool->url = self::DASHBOARD;
            }
            if ($tool->description === 'Post-purchase site and product reviews; seller ratings feed.') {
                $tool->description = 'Our main third-party review platform: post-purchase site and product reviews, and the seller ratings feed for Google.';
            }
            $tool->featured = true;
            $tool->sort = 0;
            $tool->save();
        }

        $routine = Routine::where('title', 'Check new reviews and escalate')->first();
        if ($routine && $routine->description === self::OLD_ROUTINE) {
            $routine->description = self::NEW_ROUTINE;
            if ($routine->link === 'https://business.trustpilot.com') {
                $routine->link = self::DASHBOARD;
            }
            $routine->save();
        }

        $brand = Brand::where('slug', '39dg')->first();
        if ($brand) {
            Account::firstOrCreate(
                ['brand_id' => $brand->id, 'platform' => 'shopper_approved'],
                ['handle' => 'Merchant dashboard', 'url' => self::DASHBOARD, 'notes' => 'Our main third-party review platform. Reviews here feed Google seller ratings.'],
            );
        }
    }
}
