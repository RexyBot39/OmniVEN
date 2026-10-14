<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Pre-creates two admins so tasks can be assigned to them before their first sign-in.
// Their names fill in from Google the first time they sign in.
return new class extends Migration
{
    private array $admins = [
        'drmarlow@39dollarglasses.com' => 'Dr. Marlow',
        'leona.p@39dollarglasses.com' => 'Leona P.',
    ];

    public function up(): void
    {
        foreach ($this->admins as $email => $name) {
            $existing = DB::table('users')->where('email', $email)->first();
            if ($existing) {
                DB::table('users')->where('id', $existing->id)->update(['is_admin' => true, 'is_active' => true]);
            } else {
                DB::table('users')->insert([
                    'name' => $name, 'email' => $email, 'is_admin' => true, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Leave the accounts in place; access can be changed on the People page.
    }
};
