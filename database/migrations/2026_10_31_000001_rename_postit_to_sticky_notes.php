<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// "PostIT notes" are now "Sticky notes". Reword earlier Activity entries to match.
// These descriptions never contain a note's own text, only fixed wording.
return new class extends Migration
{
    private const MAP = [
        'Created a PostIT note' => 'Created a sticky note',
        'Deleted a PostIT note' => 'Deleted a sticky note',
        'Stuck a PostIT note to the screen' => 'Stuck a sticky note to the screen',
        'Unstuck a PostIT note' => 'Unstuck a sticky note',
        'Added a PostIT to-do' => 'Added a sticky note to-do',
        'Checked off a PostIT to-do' => 'Checked off a sticky note to-do',
        'Unchecked a PostIT to-do' => 'Unchecked a sticky note to-do',
        'Cleared finished PostIT to-dos' => 'Cleared finished sticky note to-dos',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('activity_logs')->where('description', $old)->update(['description' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('activity_logs')->where('description', $new)->update(['description' => $old]);
        }
    }
};
