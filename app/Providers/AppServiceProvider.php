<?php

namespace App\Providers;

use App\Models\Note;
use App\Models\PostIt;
use App\Models\Task;
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
        Gate::define('complete-task', fn (User $user, Task $task) => $user->is_admin || $task->assignee_id === $user->id);
        Gate::define('own-postit', fn (User $user, PostIt $postit) => $postit->user_id === $user->id);
        Gate::define('manage-note', fn (User $user, Note $note) => $user->is_admin || $note->user_id === $user->id);
    }
}
