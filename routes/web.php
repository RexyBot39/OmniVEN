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
    Route::get('/content-gaps', [\App\Http\Controllers\ContentGapController::class, 'index'])->name('content-gaps');
    Route::post('/content-gaps/task', [\App\Http\Controllers\ContentGapController::class, 'task'])->middleware('throttle:60,1')->name('content-gaps.task');
    Route::post('/content-gaps/dismiss', [\App\Http\Controllers\ContentGapController::class, 'dismiss'])->middleware('throttle:60,1')->name('content-gaps.dismiss');
    Route::delete('/content-gaps/{gap}', [\App\Http\Controllers\ContentGapController::class, 'restore'])->name('content-gaps.restore');
    Route::middleware('can:use-assistant')->group(function () {
        Route::get('/assistant', [\App\Http\Controllers\AssistantController::class, 'index'])->name('assistant');
        Route::post('/assistant', [\App\Http\Controllers\AssistantController::class, 'ask'])->middleware('throttle:12,1')->name('assistant.ask');
        Route::get('/assistant/c/{conversation}', [\App\Http\Controllers\AssistantController::class, 'show'])->name('assistant.show');
        Route::delete('/assistant/c/{conversation}', [\App\Http\Controllers\AssistantController::class, 'destroy'])->name('assistant.destroy');
        Route::get('/assistant/taskbuddy', [\App\Http\Controllers\TaskBuddyController::class, 'index'])->name('taskbuddy');
        Route::post('/assistant/taskbuddy', [\App\Http\Controllers\TaskBuddyController::class, 'start'])->middleware('throttle:10,1')->name('taskbuddy.start');
        Route::get('/assistant/taskbuddy/{run}', [\App\Http\Controllers\TaskBuddyController::class, 'show'])->name('taskbuddy.show');
        Route::get('/assistant/taskbuddy/{run}/status', [\App\Http\Controllers\TaskBuddyController::class, 'status'])->middleware('throttle:240,1')->name('taskbuddy.status');
        Route::post('/assistant/taskbuddy/{run}/answer', [\App\Http\Controllers\TaskBuddyController::class, 'answer'])->middleware('throttle:20,1')->name('taskbuddy.answer');
        Route::post('/assistant/taskbuddy/{run}/add', [\App\Http\Controllers\TaskBuddyController::class, 'add'])->middleware('throttle:20,1')->name('taskbuddy.add');
        Route::delete('/assistant/taskbuddy/{run}', [\App\Http\Controllers\TaskBuddyController::class, 'destroy'])->name('taskbuddy.destroy');
        Route::get('/assistant/m/{message}', [\App\Http\Controllers\AssistantController::class, 'poll'])->middleware('throttle:240,1')->name('assistant.poll');
    });
    Route::get('/coverage', [\App\Http\Controllers\CoverageController::class, 'index'])->name('coverage');
    Route::post('/coverage/skip', [\App\Http\Controllers\CoverageController::class, 'skip'])->middleware(['can:admin', 'throttle:60,1'])->name('coverage.skip');
    Route::get('/campaigns', [\App\Http\Controllers\CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/new', [\App\Http\Controllers\CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [\App\Http\Controllers\CampaignController::class, 'store'])->middleware('throttle:30,1')->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [\App\Http\Controllers\CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('/campaigns/{campaign}/edit', [\App\Http\Controllers\CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('/campaigns/{campaign}', [\App\Http\Controllers\CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('/campaigns/{campaign}', [\App\Http\Controllers\CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::get('/assets', [\App\Http\Controllers\AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/{asset}/file', [\App\Http\Controllers\AssetController::class, 'file'])->name('assets.file');
    Route::middleware('can:admin')->group(function () {
        Route::get('/assets/new', [\App\Http\Controllers\AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [\App\Http\Controllers\AssetController::class, 'store'])->middleware('throttle:30,1')->name('assets.store');
        Route::post('/assets/share', [\App\Http\Controllers\AssetController::class, 'share'])->middleware('throttle:20,1')->name('assets.share');
        Route::get('/assets/{asset}/edit', [\App\Http\Controllers\AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [\App\Http\Controllers\AssetController::class, 'update'])->name('assets.update');
        Route::delete('/assets/{asset}', [\App\Http\Controllers\AssetController::class, 'destroy'])->name('assets.destroy');
    });
    Route::get('/outreach', [\App\Http\Controllers\OutreachController::class, 'index'])->name('outreach.index');
    Route::get('/outreach/new', [\App\Http\Controllers\OutreachController::class, 'create'])->name('outreach.create');
    Route::post('/outreach', [\App\Http\Controllers\OutreachController::class, 'store'])->middleware('throttle:60,1')->name('outreach.store');
    Route::post('/outreach/bulk', [\App\Http\Controllers\OutreachController::class, 'bulk'])->middleware('throttle:10,1')->name('outreach.bulk');
    Route::get('/outreach/{publisher}', [\App\Http\Controllers\OutreachController::class, 'show'])->name('outreach.show');
    Route::get('/outreach/{publisher}/edit', [\App\Http\Controllers\OutreachController::class, 'edit'])->name('outreach.edit');
    Route::put('/outreach/{publisher}', [\App\Http\Controllers\OutreachController::class, 'update'])->name('outreach.update');
    Route::post('/outreach/{publisher}/act', [\App\Http\Controllers\OutreachController::class, 'act'])->middleware('throttle:60,1')->name('outreach.act');
    Route::delete('/outreach/{publisher}', [\App\Http\Controllers\OutreachController::class, 'destroy'])->name('outreach.destroy');
    Route::get('/reddit', [\App\Http\Controllers\RedditController::class, 'index'])->name('reddit');
    Route::post('/reddit/{thread}/task', [\App\Http\Controllers\RedditController::class, 'task'])->middleware('throttle:60,1')->name('reddit.task');
    Route::post('/reddit/{thread}/skip', [\App\Http\Controllers\RedditController::class, 'skip'])->middleware('throttle:60,1')->name('reddit.skip');
    Route::post('/reddit/{thread}/restore', [\App\Http\Controllers\RedditController::class, 'restore'])->name('reddit.restore');
    Route::get('/site-check', [\App\Http\Controllers\SiteCheckController::class, 'index'])->name('site-check');
    Route::middleware('can:admin')->group(function () {
        Route::post('/site-check/pages', [\App\Http\Controllers\SiteCheckController::class, 'store'])->middleware('throttle:30,1')->name('site-check.store');
        Route::post('/site-check/homes', [\App\Http\Controllers\SiteCheckController::class, 'addHomes'])->middleware('throttle:10,1')->name('site-check.homes');
        Route::delete('/site-check/pages/{page}', [\App\Http\Controllers\SiteCheckController::class, 'destroy'])->name('site-check.destroy');
        Route::post('/site-check/run', [\App\Http\Controllers\SiteCheckController::class, 'run'])->middleware('throttle:6,10')->name('site-check.run');
    });
    Route::get('/reviews', [\App\Http\Controllers\ReviewsController::class, 'index'])->name('reviews');
    Route::post('/reviews/rating', [\App\Http\Controllers\ReviewsController::class, 'snapshot'])->name('reviews.rating');
    Route::get('/links', [\App\Http\Controllers\LinkController::class, 'index'])->name('links.index');
    Route::post('/links', [\App\Http\Controllers\LinkController::class, 'store'])->name('links.store');
    Route::delete('/links/{link}', [\App\Http\Controllers\LinkController::class, 'destroy'])->name('links.destroy');
    Route::get('/affiliates', [\App\Http\Controllers\AffiliatesController::class, 'index'])->middleware('can:see-revenue')->name('affiliates');
    Route::get('/reports/monthly', [\App\Http\Controllers\MonthlyReportController::class, 'show'])->middleware('can:see-revenue')->name('reports.monthly');
    Route::get('/email', [\App\Http\Controllers\EmailController::class, 'index'])->middleware('can:see-revenue')->name('email');
    Route::get('/sales', [\App\Http\Controllers\SalesController::class, 'index'])->middleware('can:see-revenue')->name('sales');
    Route::post('/sales/notes', [\App\Http\Controllers\SalesController::class, 'note'])->middleware('can:see-revenue')->name('sales.note');
    Route::post('/sales/goals', [\App\Http\Controllers\SalesController::class, 'goals'])->middleware('can:admin')->name('sales.goals');

    // Everyone signed in: tick routines, post and manage their own notes.
    Route::post('/routines/{routine}/toggle', [RoutineController::class, 'toggle'])->name('routines.toggle');
    Route::post('/routines/{routine}/brands/{brand}', [RoutineController::class, 'toggleBrand'])->name('routines.toggle-brand');
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
    Route::post('/tasks/{task}/outcome', [TaskController::class, 'outcome'])->middleware('throttle:60,1')->name('tasks.outcome');

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

// A publisher's asset kit: no sign-in; the link is signed and expires (made on the Affiliate assets page).
Route::middleware(['signed', 'throttle:120,1'])->group(function () {
    Route::get('/kit', [\App\Http\Controllers\AssetController::class, 'kit'])->name('kit');
    Route::get('/kit/{asset}', [\App\Http\Controllers\AssetController::class, 'kitFile'])->name('kit.file');
});
