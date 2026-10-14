<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** Records who did what in OmniVEN, for the admin Activity report. Never breaks the request it's logging. */
class Activity
{
    public static function log(string $action, string $description, ?Model $subject = null, ?User $user = null): void
    {
        try {
            ActivityLog::create([
                'user_id' => ($user ?? Auth::user())?->id,
                'action' => $action,
                'description' => Str::limit($description, 495),
                'subject_type' => $subject ? Str::snake(class_basename($subject)) : null,
                'subject_id' => $subject?->getKey(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Activity log write failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /** One "visited" entry per person per hour, so the log shows presence without flooding. */
    public static function visit(User $user): void
    {
        $recent = ActivityLog::where('user_id', $user->id)->where('action', 'visited')
            ->where('created_at', '>=', now()->subHour())->exists();
        if (! $recent) {
            self::log('visited', 'Opened OmniVEN', null, $user);
        }
    }
}
