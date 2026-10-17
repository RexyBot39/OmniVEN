<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PostItController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [GoogleController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/ai-answers', [\App\Http\Controllers\AiAnswersController::class, 'index'])->name('ai-answers');
    Route::post('/ai-answers', [\App\Http\Controllers\AiAnswersController::class, 'save'])->name('ai-answers.save');
    Route::middleware('can:admin')->group(function () {
        Route::post('/ai-answers/questions', [\App\Http\Controllers\AiAnswersController::class, 'storeQuestion'])->name('ai-answers.questions.store');
        Route::patch('/ai-answers/questions/{question}', [\App\Http\Controllers\AiAnswersController::class, 'updateQuestion'])->name('ai-answers.questions.update');
        Route::delete('/ai-answers/questions/{question}', [\App\Http\Controllers\AiAnswersController::class, 'destroyQuestion'])->name('ai-answers.questions.destroy');
    });
    Route::get('/templates', [\App\Http\Controllers\TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/{template}/run', [\App\Http\Controllers\TemplateController::class, 'runForm'])->name('templates.run-form');
    Route::post('/templates/{template}/run', [\App\Http\Controllers\TemplateController::class, 'run'])->name('templates.run');
    Route::get('/templates/runs/{run}', [\App\Http\Controllers\TemplateController::class, 'showRun'])->name('templates.show-run');
    Route::middleware('can:admin')->group(function () {
        Route::post('/templates', [\App\Http\Controllers\TemplateController::class, 'store'])->name('templates.store');
        Route::get('/templates/{template}/edit', [\App\Http\Controllers\TemplateController::class, 'edit'])->name('templates.edit');
        Route::put('/templates/{template}', [\App\Http\Controllers\TemplateController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{template}', [\App\Http\Controllers\TemplateController::class, 'destroy'])->name('templates.destroy');
    });
    Route::get('/my-day', [\App\Http\Controllers\MyDayController::class, 'show'])->name('my-day');
    Route::post('/my-day/toggle', [\App\Http\Controllers\MyDayController::class, 'toggle'])->name('my-day.toggle');
    Route::post('/my-day/send', [\App\Http\Controllers\MyDayController::class, 'send'])->middleware('throttle:5,10')->name('my-day.send');
    Route::get('/seo', [\App\Http\Controllers\SeoController::class, 'index'])->name('seo');
    Route::get('/reviews', [\App\Http\Controllers\ReviewsController::class, 'index'])->name('reviews');
    Route::post('/reviews/rating', [\App\Http\Controllers\ReviewsController::class, 'snapshot'])->name('reviews.rating');
    Route::get('/links', [\App\Http\Controllers\LinkController::class, 'index'])->name('links.index');
    Route::post('/links', [\App\Http\Controllers\LinkController::class, 'store'])->name('links.store');
    Route::delete('/links/{link}', [\App\Http\Controllers\LinkController::class, 'destroy'])->name('links.destroy');
    Route::get('/affiliates', [\App\Http\Controllers\AffiliatesController::class, 'index'])->middleware('can:see-revenue')->name('affiliates');
    Route::get('/email', [\App\Http\Controllers\EmailController::class, 'index'])->middleware('can:see-revenue')->name('email');
    Route::get('/sales', [\App\Http\Controllers\SalesController::class, 'index'])->middleware('can:see-revenue')->name('sales');
    Route::post('/sales/notes', [\App\Http\Controllers\SalesController::class, 'note'])->middleware('can:see-revenue')->name('sales.note');
    Route::post('/sales/goals', [\App\Http\Controllers\SalesController::class, 'goals'])->middleware('can:admin')->name('sales.goals');

    // Everyone signed in: tick routines, post and manage their own notes.
    Route::post('/routines/{routine}/toggle', [RoutineController::class, 'toggle'])->name('routines.toggle');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->name('notes.edit');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    // Tasks: anyone can create one for anyone; the assignee (or an admin) marks it done.
    // The same "New task" form also makes routines, for admins only.
    Route::get('/work/new', [\App\Http\Controllers\WorkController::class, 'create'])->name('work.create');
    Route::post('/work', [\App\Http\Controllers\WorkController::class, 'store'])->name('work.store');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('/tasks/{task}/toggle', [TaskController::class, 'toggle'])->name('tasks.toggle');

    // Sticky notes: personal to-do lists.
    Route::post('/postits', [PostItController::class, 'store'])->name('postits.store');
    Route::put('/postits/{postit}', [PostItController::class, 'update'])->name('postits.update');
    Route::delete('/postits/{postit}', [PostItController::class, 'destroy'])->name('postits.destroy');
    Route::post('/postits/{postit}/stick', [PostItController::class, 'stick'])->name('postits.stick');
    Route::post('/postits/{postit}/items', [PostItController::class, 'addItem'])->name('postits.items.store');
    Route::post('/postits/{postit}/clear', [PostItController::class, 'clearDone'])->name('postits.clear');
    Route::post('/postit-items/{item}/toggle', [PostItController::class, 'toggleItem'])->name('postits.items.toggle');
    Route::delete('/postit-items/{item}', [PostItController::class, 'destroyItem'])->name('postits.items.destroy');

    // Activity report: only the people named in OMNIVEN_ACTIVITY_VIEWERS.
    Route::middleware('can:view-activity')->group(function () {
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
        Route::get('/activity/export', [ActivityController::class, 'export'])->name('activity.export');
    });

    // Slack tab.
    Route::get('/slack/feed', [SlackController::class, 'feed'])->name('slack.feed');
    Route::get('/slack/unread', [SlackController::class, 'unread'])->name('slack.unread');
    Route::post('/slack/channels/{channel}/post', [SlackController::class, 'post'])->middleware('throttle:20,1')->name('slack.post');

    // Admins only.
    Route::middleware('can:admin')->group(function () {
        Route::resource('tools', ToolController::class)->except(['index', 'show']);
        Route::resource('brands', BrandController::class)->except(['index', 'show']);
        Route::resource('routines', RoutineController::class)->except(['index', 'show']);
        Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('/people', [UserController::class, 'store'])->name('people.store');
        Route::resource('accounts', AccountController::class)->except(['index', 'show']);
        Route::post('/accounts/{account}/verify', [AccountController::class, 'verify'])->name('accounts.verify');
        Route::post('/tools/{tool}/verify', [ToolController::class, 'verify'])->name('tools.verify');
        Route::get('/access/review', [AccessRequestController::class, 'review'])->name('access.review');
        Route::get('/calendar/{week}/items/create', [\App\Http\Controllers\CalendarItemController::class, 'create'])->name('calendar.items.create');
        Route::post('/calendar/{week}/items', [\App\Http\Controllers\CalendarItemController::class, 'store'])->name('calendar.items.store');
        Route::get('/calendar/{week}/items/{item}/edit', [\App\Http\Controllers\CalendarItemController::class, 'edit'])->name('calendar.items.edit');
        Route::put('/calendar/{week}/items/{item}', [\App\Http\Controllers\CalendarItemController::class, 'update'])->name('calendar.items.update');
        Route::delete('/calendar/{week}/items/{item}', [\App\Http\Controllers\CalendarItemController::class, 'destroy'])->name('calendar.items.destroy');
        Route::post('/links/check', [\App\Http\Controllers\LinkCheckController::class, 'run'])->middleware('throttle:3,10')->name('links.check');
        Route::post('/access-requests/{accessRequest}/grant', [AccessRequestController::class, 'grant'])->name('access-requests.grant');
        Route::post('/access-requests/{accessRequest}/decline', [AccessRequestController::class, 'decline'])->name('access-requests.decline');
        Route::post('/slack/channels', [SlackController::class, 'store'])->name('slack.channels.store');
        Route::delete('/slack/channels/{channel}', [SlackController::class, 'destroy'])->name('slack.channels.destroy');
        Route::get('/people', [UserController::class, 'index'])->name('people.index');
        Route::get('/people/{user}', [UserController::class, 'show'])->name('people.show');
        Route::patch('/people/{user}', [UserController::class, 'update'])->name('people.update');
    });

    // Brand hubs (after the admin routes so /brands/create isn't read as a brand name).
    Route::get('/brands/{brand:slug}', [BrandController::class, 'show'])->name('brands.show');

    // Request access to a tool or brand account (admins are told on the Access tab and in Slack).
    Route::post('/access-requests', [AccessRequestController::class, 'store'])->middleware('throttle:10,1')->name('access-requests.store');
    Route::delete('/access-requests/{accessRequest}', [AccessRequestController::class, 'destroy'])->name('access-requests.destroy');

    // Help: the field guide, with search.
    Route::view('/help', 'help.index')->name('help');

    // Weekly action calendar: shared checkmarks, boost log and Sunday review note.
    Route::get('/calendar', [\App\Http\Controllers\CalendarController::class, 'show'])->name('calendar');
    Route::get('/calendar/export/boosts.csv', [\App\Http\Controllers\CalendarController::class, 'exportBoosts'])->name('calendar.export.boosts');
    Route::get('/calendar/export/tasks.csv', [\App\Http\Controllers\CalendarController::class, 'exportTasks'])->name('calendar.export.tasks');
    Route::get('/calendar/{week}', [\App\Http\Controllers\CalendarController::class, 'show'])->name('calendar.week');
    Route::post('/calendar/{week}/items/{item}/toggle', [\App\Http\Controllers\CalendarController::class, 'toggle'])->name('calendar.toggle');
    Route::post('/calendar/{week}/boosts', [\App\Http\Controllers\CalendarController::class, 'storeBoost'])->name('calendar.boosts.store');
    Route::delete('/calendar/boosts/{boost}', [\App\Http\Controllers\CalendarController::class, 'destroyBoost'])->name('calendar.boosts.destroy');
    Route::put('/calendar/{week}/notes', [\App\Http\Controllers\CalendarController::class, 'notes'])->name('calendar.notes');

    // My launchpad: personal stars and recently opened items.
    Route::post('/launchpad/favorite', [\App\Http\Controllers\LaunchpadController::class, 'favorite'])->middleware('throttle:60,1')->name('launchpad.favorite');
    Route::post('/launchpad/order', [\App\Http\Controllers\LaunchpadController::class, 'order'])->middleware('throttle:60,1')->name('launchpad.order');
    Route::post('/launchpad/open', [\App\Http\Controllers\LaunchpadController::class, 'open'])->middleware('throttle:120,1')->name('launchpad.open');

    // Omni search behind the dashboard search box.
    Route::get('/search.json', [\App\Http\Controllers\SearchController::class, 'index'])->middleware('throttle:120,1')->name('search');

    // Quick launcher index (⌘K).
    Route::get('/launcher.json', [\App\Http\Controllers\LauncherController::class, 'index'])->name('launcher');
});
