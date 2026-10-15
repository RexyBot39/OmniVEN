<?php

namespace Database\Seeders;

use App\Models\CalendarItem;
use App\Models\CalendarWeek;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The Q4 2026 action calendar, moved in from the artifact version. Weeks, their phase and
 * seasonal angle, the recurring runbook (every week) and the one-off items for specific weeks.
 * Matched by date and key, so running it again adds what's missing and leaves edits alone.
 */
class ActionCalendarSeeder extends Seeder
{
    /** The artifact's brand codes => OmniVEN brand slugs (null = all brands). */
    private const BRANDS = ['dg' => '39dg', 'os' => 'ocusafe', 'nx' => 'neurolux', 'all' => null];

    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/action-calendar-q4-2026.json')), true);

        $weeks = [];
        foreach ($data['WEEKS'] as $w) {
            $phase = $data['PHASE'][(string) $w['n']] ?? null;
            $monday = Carbon::parse($w['id'])->toDateString();
            $weeks[$w['n']] = CalendarWeek::whereDate('starts_on', $monday)->first() ?? CalendarWeek::create([
                'starts_on' => $monday, 'number' => $w['n'], 'range_label' => $w['range'],
                'phase_label' => $phase['l'] ?? null, 'phase_text' => $phase['p'] ?? null,
                'season_text' => $data['SEASON'][(string) $w['n']] ?? null,
            ]);
        }

        foreach ($data['BASE'] as $i => $t) {
            $this->item(null, $t, $i);
        }
        foreach ($data['EXTRA'] as $n => $items) {
            if (isset($weeks[(int) $n])) {
                foreach ($items as $i => $t) {
                    $this->item($weeks[(int) $n]->id, $t, $i);
                }
            }
        }
    }

    private function item(?int $weekId, array $t, int $sort): void
    {
        CalendarItem::firstOrCreate(['calendar_week_id' => $weekId, 'key' => $t['id']], [
            'day' => $t['d'], 'brand_slug' => self::BRANDS[$t['b']] ?? null, 'title' => $t['t'],
            'keyword' => $t['kw'] ?: null, 'owner' => $t['o'] === 'marc' ? 'Marc' : $t['o'],
            'done_when' => $t['dw'] ?: null, 'sort' => $sort,
        ]);
    }
}
