<?php

namespace App\Providers;

use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::define('admin', fn (User $user) => $user->is_admin);
        Gate::define('manage-note', fn (User $user, Note $note) => $user->is_admin || $note->user_id === $user->id);
    }
}
