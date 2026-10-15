<?php

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

    // Everyone signed in: tick routines, post and manage their own notes.
    Route::post('/routines/{routine}/toggle', [RoutineController::class, 'toggle'])->name('routines.toggle');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->name('notes.edit');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    // Tasks: the assignee (or an admin) marks them done.
    Route::post('/tasks/{task}/toggle', [TaskController::class, 'toggle'])->name('tasks.toggle');

    // PostIT notes: personal to-do lists.
    Route::post('/postits', [PostItController::class, 'store'])->name('postits.store');
    Route::put('/postits/{postit}', [PostItController::class, 'update'])->name('postits.update');
    Route::delete('/postits/{postit}', [PostItController::class, 'destroy'])->name('postits.destroy');
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
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('/people', [UserController::class, 'store'])->name('people.store');
        Route::resource('accounts', AccountController::class)->except(['index', 'show']);
        Route::post('/accounts/{account}/verify', [AccountController::class, 'verify'])->name('accounts.verify');
        Route::post('/slack/channels', [SlackController::class, 'store'])->name('slack.channels.store');
        Route::delete('/slack/channels/{channel}', [SlackController::class, 'destroy'])->name('slack.channels.destroy');
        Route::get('/people', [UserController::class, 'index'])->name('people.index');
        Route::patch('/people/{user}', [UserController::class, 'update'])->name('people.update');
    });

    // Brand hubs (after the admin routes so /brands/create isn't read as a brand name).
    Route::get('/brands/{brand:slug}', [BrandController::class, 'show'])->name('brands.show');

    // Quick launcher index (⌘K).
    Route::get('/launcher.json', [\App\Http\Controllers\LauncherController::class, 'index'])->name('launcher');
});
