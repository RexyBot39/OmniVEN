<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\Tool;
use Illuminate\Database\Seeder;

/**
 * The 39DollarGlasses and Ocusafe blogs are written and published in Contentful (Marc, Oct 2026).
 * Adds the Blog tool category with Contentful, a Contentful account on each brand's hub, and a
 * weekly blog routine. Never overwrites anything an admin has edited.
 */
class ContentfulSeeder extends Seeder
{
    public const ENTRIES = 'https://app.contentful.com/spaces/n0xgoet11vmg/views/entries';

    private const BRANDS = ['39dg', 'ocusafe'];

    public function run(): void
    {
        $brands = Brand::whereIn('slug', self::BRANDS)->get();
        // A fresh install gets this from the starter seed, which calls it after adding brands.
        if ($brands->isEmpty()) {
            return;
        }

        $tool = Tool::firstOrNew(['name' => 'Contentful']);
        if (! $tool->exists) {
            $tool->fill([
                'url' => self::ENTRIES, 'category' => 'Blog', 'status' => 'active', 'all_brands' => false, 'sort' => 1,
                'description' => 'Where the 39DollarGlasses and Ocusafe blog posts are written, edited and published.',
                'access_note' => 'Space n0xgoet11vmg. Ask an admin to invite you to the space.',
            ])->save();
            $tool->brands()->sync($brands->pluck('id'));
        }

        foreach ($brands as $brand) {
            Account::firstOrCreate(
                ['brand_id' => $brand->id, 'platform' => 'contentful'],
                ['handle' => $brand->name.' blog', 'url' => self::ENTRIES, 'account_ref' => 'n0xgoet11vmg', 'notes' => 'Blog posts for '.$brand->name.' are published from here.'],
            );
        }

        $routine = Routine::firstOrNew(['title' => 'Update the blogs in Contentful']);
        if (! $routine->exists) {
            $routine->fill([
                'cadence' => 'weekly', 'link' => self::ENTRIES, 'all_brands' => false, 'sort' => 20,
                'description' => 'Draft, review and publish this week\'s 39DollarGlasses and Ocusafe posts in Contentful, then check each one is live on the site.',
            ])->save();
            $routine->brands()->sync($brands->pluck('id'));
        }
    }
}
