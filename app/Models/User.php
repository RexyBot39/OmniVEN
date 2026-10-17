<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'google_id', 'avatar', 'is_admin', 'is_active', 'board_position', 'last_login_at', 'hotlist_popup_on'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'hotlist_popup_on' => 'date',
            'myday_slack' => 'boolean',
            'myday_sent_on' => 'date',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'board_position' => 'integer',
        ];
    }

    public function isConfiguredAdmin(): bool
    {
        return in_array(strtolower($this->email), config('omniven.admin_emails'), true);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function postits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PostIt::class)->orderBy('id');
    }

    /** Leona, Chanley and Kaylie get their task board column as soon as they're added or first sign in. */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->board_position === null) {
                $user->board_position = self::boardPositionFor((string) $user->name, (string) $user->email);
            }
        });
    }

    public static function boardPositionFor(string $name, string $email): ?int
    {
        foreach (config('omniven.task_board') as $first => $pos) {
            if (str_starts_with(strtolower(trim($name)), $first) || str_starts_with(strtolower($email), $first)) {
                return $pos;
            }
        }

        return null;
    }

    public function onBoard(): bool
    {
        return $this->board_position !== null;
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?: $this->email;
    }
}
