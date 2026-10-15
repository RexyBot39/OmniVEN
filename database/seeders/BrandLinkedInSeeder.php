<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\Tool;
use Illuminate\Database\Seeder;

/**
 * The brands' LinkedIn pages (confirmed by Marc, Oct 2026). Adds each as a brand account that
 * opens the page's admin dashboard, adds the public page to the brand's Social links and
 * channels, adds a LinkedIn tool, and mentions LinkedIn in the comments-and-DMs routine.
 * Only adds what's missing; anything an admin has changed is left alone.
 */
class BrandLinkedInSeeder extends Seeder
{
    /** brand slug => [page type, LinkedIn ID] */
    public const PAGES = [
        '39dg' => ['company', '1945733'],
        'ocusafe' => ['company', '93311139'],
        'ocusleep' => ['company', '95589338'],
        'neurolux' => ['showcase', '104508825'],
    ];

    private const OLD_DM_ROUTINE = 'Instagram, Facebook and TikTok inboxes for every brand.';

    public static function adminUrl(string $type, string $id): string
    {
        return "https://www.linkedin.com/{$type}/{$id}/admin/dashboard/";
    }

    public static function publicUrl(string $type, string $id): string
    {
        return "https://www.linkedin.com/{$type}/{$id}/";
    }

    public function run(): void
    {
        $brandIds = [];
        foreach (self::PAGES as $slug => [$type, $id]) {
            $brand = Brand::where('slug', $slug)->first();
            if (! $brand) {
                continue;
            }
            $brandIds[] = $brand->id;

            Account::firstOrCreate(
                ['brand_id' => $brand->id, 'platform' => 'linkedin'],
                [
                    'handle' => $type === 'showcase' ? 'Showcase page' : 'Company page',
                    'url' => self::adminUrl($type, $id),
                    'account_ref' => $id,
                    'notes' => 'Opens the page admin dashboard (you need to be a page admin). Public page: '.self::publicUrl($type, $id)
                        .($type === 'showcase' ? ' · A showcase page under the 39DollarGlasses company page.' : ''),
                ],
            );

            // Brands tab "Social" line: add the public page if no LinkedIn link is there yet.
            $socials = $brand->socials ?? [];
            if (! collect($socials)->contains(fn ($s) => str_contains((string) ($s['url'] ?? ''), 'linkedin.com'))) {
                $socials[] = ['label' => 'LinkedIn', 'url' => self::publicUrl($type, $id)];
                $brand->socials = $socials;
            }
            // "Main channels": add LinkedIn if it isn't listed.
            if (! str_contains(strtolower((string) $brand->channels), 'linkedin')) {
                $brand->channels = $brand->channels ? rtrim($brand->channels, " ,").', LinkedIn' : 'LinkedIn';
            }
            $brand->save();
        }

        if (! Tool::where('name', 'LinkedIn')->exists() && $brandIds) {
            $tool = Tool::create([
                'name' => 'LinkedIn', 'url' => 'https://www.linkedin.com', 'category' => 'Social & Community',
                'description' => 'Company and showcase pages for 39DollarGlasses, Ocusafe, Ocusleep and Neurolux. Each brand hub opens its page admin dashboard.',
                'status' => 'verify', 'all_brands' => false, 'sort' => 6,
                'access_note' => 'Ask a page Super admin to add you under Admin tools → Manage admins on that brand\'s page.',
            ]);
            $tool->brands()->sync($brandIds);
        }

        Routine::where('title', 'Answer comments and DMs')->where('description', self::OLD_DM_ROUTINE)
            ->update(['description' => 'Instagram, Facebook and TikTok inboxes for every brand, plus LinkedIn page comments and messages.']);
    }
}
