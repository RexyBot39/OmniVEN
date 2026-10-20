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
        // Your stuck sticky notes float on every page. Only ever your own.
        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('stuckPostits', $user ? $user->postits()->where('stuck', true)->with('items')->orderBy('id')->get() : collect());
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::define('admin', fn (User $user) => $user->is_admin);
        Gate::define('view-activity', fn (User $user) => in_array(strtolower($user->email), config('omniven.activity_viewers'), true));
        Gate::define('complete-task', fn (User $user, Task $task) => $user->is_admin || $task->assignee_id === $user->id);
        Gate::define('own-postit', fn (User $user, PostIt $postit) => $postit->user_id === $user->id);
        Gate::define('see-revenue', fn (User $user) => \App\Support\Revenue\Revenue::canSee($user));
        // The assistant is hidden until an OpenAI key is set (admins still see its setup steps).
        Gate::define('use-assistant', fn (User $user) => \App\Support\Assistant\Assistant::configured() || $user->is_admin);
        // Ocusafe opportunities are hidden until Tango is set up (admins still see its setup steps).
        Gate::define('see-opportunities', fn (User $user) => \App\Support\Opportunities\Tango::configured() || $user->is_admin);
        // Metabase holds sales data, so it's for admins and revenue viewers; hidden until its API key is set (admins see setup steps).
        Gate::define('see-metabase', fn (User $user) => $user->is_admin || (\App\Support\Metabase\Metabase::configured() && \App\Support\Revenue\Revenue::canSee($user)));
        // The SEO suite is hidden until DataForSEO is set up (admins still see its setup steps); Backlinks is its own subscription.
        Gate::define('use-seo', fn (User $user) => \App\Support\Seo\DataForSeo::configured() || $user->is_admin);
        Gate::define('use-seo-backlinks', fn (User $user) => \App\Support\Seo\DataForSeo::backlinks() || $user->is_admin);
        Gate::define('manage-note', fn (User $user, Note $note) => $user->is_admin || $note->user_id === $user->id);
    }
}
