<?php

namespace Database\Seeders;

use App\Models\AiQuestion;
use App\Models\Brand;
use App\Models\Routine;
use Illuminate\Database\Seeder;

/**
 * Starter questions for AI answer tracking, and a monthly routine to check them. Idempotent: only
 * adds what's missing, so questions edited or removed in the app aren't brought back.
 */
class AiAnswersSeeder extends Seeder
{
    private const QUESTIONS = [
        '39dg' => [
            'Where is the best place to buy cheap prescription glasses online?',
            'What is the most affordable online glasses store with good quality?',
            'Where can I get prescription glasses for under $50?',
            'Is 39DollarGlasses legit?',
            'What are the best cheap progressive glasses to buy online?',
            'Where can I get prescription blue light glasses online?',
            'Can I get new prescription lenses put in my own frames online?',
        ],
        'ocusafe' => [
            'What are the best prescription safety glasses for work?',
            'Where can I buy ANSI Z87 prescription safety glasses online?',
            'Where can I get affordable prescription safety glasses with side shields?',
        ],
        'neurolux' => [
            'What are the best glasses for light sensitivity and migraines?',
            'Where can I buy FL-41 tinted glasses with my prescription?',
        ],
    ];

    /** The live site, unless APP_URL is set to something real. */
    private static function base(): string
    {
        $url = rtrim((string) config('app.url'), '/');

        return $url === '' || str_contains($url, 'localhost') ? 'https://omniven.cc' : $url;
    }

    public function run(): void
    {
        $brands = Brand::whereIn('slug', array_keys(self::QUESTIONS))->get()->keyBy('slug');
        // A fresh install gets this from the starter seed, which calls it after adding brands.
        if ($brands->isEmpty()) {
            return;
        }

        if (AiQuestion::query()->doesntExist()) {
            $sort = 0;
            foreach (self::QUESTIONS as $slug => $questions) {
                foreach ($questions as $q) {
                    AiQuestion::create(['brand_id' => $brands[$slug]->id ?? null, 'question' => $q, 'sort' => $sort += 10]);
                }
            }
        }

        $routine = Routine::firstOrNew(['title' => 'Check what AI assistants say about us']);
        if (! $routine->exists) {
            $routine->fill([
                'description' => 'Ask ChatGPT, Google, Perplexity, Gemini and Copilot each question on the AI answers page and record whether they recommend or mention us.',
                'cadence' => 'monthly', 'link' => self::base().'/ai-answers', 'all_brands' => true, 'sort' => 40,
            ])->save();
        }
    }
}
