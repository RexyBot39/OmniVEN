<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Database\Seeder;

/** A weekly routine to read the Sales page and write what moved and why. Added once. */
class WeeklyRevenueReviewSeeder extends Seeder
{
    public function run(): void
    {
        // A fresh install gets this from the starter seed, which calls it after adding brands.
        if (Brand::query()->doesntExist() || Routine::where('title', 'Weekly revenue review')->exists()) {
            return;
        }
        $url = rtrim((string) config('app.url'), '/');
        $owner = User::where('email', 'drmarcweinstein@39dollarglasses.com')->where('is_active', true)->first();
        Routine::create([
            'title' => 'Weekly revenue review',
            'description' => 'Open Sales: check each brand against its monthly goal and the channels that moved, then write this week’s note on what changed and why.',
            'cadence' => 'weekly',
            'link' => ($url === '' || str_contains($url, 'localhost') ? 'https://omniven.cc' : $url).'/sales',
            'all_brands' => true,
            'sort' => 5,
            'assignee_id' => $owner?->id,
            'assigned_at' => $owner ? now() : null,
        ]);
    }
}
