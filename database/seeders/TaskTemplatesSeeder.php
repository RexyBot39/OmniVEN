<?php

namespace Database\Seeders;

use App\Models\TaskTemplate;
use Illuminate\Database\Seeder;

/** Two starter templates, added once. Admins edit them in the app. */
class TaskTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        if (TaskTemplate::query()->exists()) {
            return;
        }

        $templates = [
            ['Product launch', 'A new frame, lens or product page going live, across email, social, blog, ads and affiliates.', [
                [-21, 'Write the {name} brief: goal, audience, offer and key message', 'One page: who it’s for, why now, the offer, and how we’ll know it worked.'],
                [-14, 'Create {name} photos and video', 'Product shots, lifestyle shots and a short vertical video. Save them in Google Drive.'],
                [-14, 'Write the {name} product page and FAQ', 'Check spelling: Ocusafe, Ocusleep. Add the prescription and fit questions people ask.'],
                [-10, 'Draft the {name} blog post in Contentful', 'Link to the product page. Answer the questions shoppers ask AI assistants.'],
                [-7, 'Build the {name} email in Klaviyo', 'Send a test to the Just Marketing list before scheduling.'],
                [-7, 'Make campaign links for {name}', 'In OmniVEN → Links: one per email, social post and affiliate network, same campaign name.'],
                [-5, 'Schedule {name} social posts for launch week', null],
                [-5, 'Brief CJ and Awin publishers on {name}', 'Offer, dates, banners and tagged links.'],
                [-3, 'Set up Meta and Google ads for {name}', 'Use the campaign links; set start and end dates.'],
                [-2, 'Check {name} is approved in Merchant Center', 'Product in the feed, no disapprovals.'],
                [-1, 'Final check before {name} goes live', 'Links, prices, codes, site banner, mobile view.'],
                [0, 'Launch {name}: send the email, publish the blog, turn on ads', null, true],
                [3, 'Reply to comments and reviews about {name}', null],
                [7, 'Review {name} results on Sales and write what worked in the weekly review', null],
            ]],
            ['Seasonal sale', 'A dated promotion (Black Friday, Back to School, Halloween…) with codes, emails, banners and affiliate offers.', [
                [-14, 'Decide the {name} offer, dates and codes', null],
                [-10, 'Create {name} creative: banners, email header, social sizes', null],
                [-7, 'Build the {name} announcement and reminder emails in Klaviyo', 'Exclude anyone who bought in the last 4 days. Test to Just Marketing.'],
                [-7, 'Make campaign links for {name}', 'In OmniVEN → Links, for email, social and each affiliate network.'],
                [-7, 'Send the {name} offer, dates and codes to CJ and Awin publishers', null],
                [-5, 'Schedule {name} posts on every brand channel', null],
                [-3, 'Set up the {name} ad set with start and end dates', null],
                [-2, 'Test the {name} banner and code at checkout', 'Desktop and mobile.'],
                [0, '{name} starts: send the announcement email', null, true],
                [2, 'Send the {name} reminder to people who haven’t bought', null],
                [5, 'End {name}: remove banners, stop ads, expire codes', 'Change the dates if the sale runs longer.'],
                [7, 'Review {name} results on Sales and write what worked in the weekly review', null],
            ]],
        ];

        foreach ($templates as $i => [$name, $description, $items]) {
            $t = TaskTemplate::create(['name' => $name, 'description' => $description, 'sort' => ($i + 1) * 10]);
            foreach ($items as $j => $it) {
                $t->items()->create(['offset_days' => $it[0], 'title' => $it[1], 'details' => $it[2], 'hot' => $it[3] ?? false, 'sort' => $j]);
            }
        }
    }
}
