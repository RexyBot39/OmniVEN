<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\Tool;
use Illuminate\Database\Seeder;

/**
 * 39DollarGlasses' Klaviyo account (confirmed by Marc, Oct 2026). Points the Klaviyo tool and the
 * weekly email routine at the dashboard and fills the brand's Klaviyo square in the grid.
 * Only changes values that are still the original starter ones.
 */
class KlaviyoSeeder extends Seeder
{
    public const DASHBOARD = 'https://www.klaviyo.com/dashboard';

    private const OLD_LINK = 'https://www.klaviyo.com/login';

    public function run(): void
    {
        $tool = Tool::where('name', 'Klaviyo')->first();
        if ($tool && in_array($tool->url, [null, '', self::OLD_LINK], true)) {
            $tool->update(['url' => self::DASHBOARD]);
        }

        $routine = Routine::where('title', 'Review email performance')->first();
        if ($routine && in_array($routine->link, [null, '', self::OLD_LINK], true)) {
            $routine->update(['link' => self::DASHBOARD]);
        }

        $brand = Brand::where('slug', '39dg')->first();
        if ($brand) {
            $acct = Account::firstOrCreate(
                ['brand_id' => $brand->id, 'platform' => 'klaviyo'],
                ['handle' => '39DollarGlasses', 'url' => self::DASHBOARD, 'account_ref' => 'WQR2ZT', 'notes' => 'Email and SMS: campaigns, flows, sign-up forms and segments.'],
            );
            if (in_array($acct->url, [null, '', self::OLD_LINK], true)) {
                $acct->update(['url' => self::DASHBOARD]);
            }
            if (! $acct->account_ref) {
                $acct->update(['account_ref' => 'WQR2ZT']);
            }
        }
    }
}
