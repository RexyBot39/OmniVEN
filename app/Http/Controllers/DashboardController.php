<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Models\Brand;
use App\Models\Note;
use App\Models\Routine;
use App\Models\Task;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();
        Activity::visit($me);

        $brands = Brand::orderBy('sort')->orderBy('name')->get();
        $tools = Tool::with('brands')->orderBy('sort')->orderBy('name')->get();
        $routines = Routine::with(['brands', 'latestCompletion.user'])->orderBy('sort')->orderBy('title')->get();
        $notes = Note::with('user')->orderByDesc('pinned')->latest()->limit(12)->get();

        $categories = collect(config('omniven.categories'))
            ->merge($tools->pluck('category')->filter()->unique()->diff(config('omniven.categories'))->sort())
            ->map(fn ($c) => ['name' => $c, 'tools' => $tools->where('category', $c)->values()])
            ->filter(fn ($c) => $c['tools']->isNotEmpty())
            ->values();
        $uncategorized = $tools->whereNull('category')->values();

        // Tasks: everything open, plus the last 14 days of finished work.
        $openTasks = Task::with(['assignee', 'creator'])->open()
            ->orderByDesc('hot')->orderByRaw('due_on is null')->orderBy('due_on')->latest()->get();
        $doneTasks = Task::with(['assignee', 'completer'])->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(14))->latest('completed_at')->limit(50)->get();
        $hotlist = $openTasks->where('hot', true)->values();

        // Once a day, pop up the hot items assigned to the person signing in.
        $popup = collect();
        if (! $me->hotlist_popup_on?->isToday()) {
            $popup = $hotlist->where('assignee_id', $me->id)->values();
            if ($popup->isNotEmpty()) {
                $me->forceFill(['hotlist_popup_on' => today()])->save();
            }
        }

        $people = User::where('is_active', true)->orderBy('name')->get();
        $postits = $me->postits()->with('items')->get();

        // Fresh sign-in, or the logo link (/?home=1): reset to Routines, all brands.
        $goHome = $request->session()->get('omniven_home', false) || $request->boolean('home');

        $sessionEndsAt = (int) $request->session()->get('signed_in_at', now()->timestamp) + config('omniven.session_hours') * 3600;

        return view('dashboard', compact(
            'brands', 'tools', 'routines', 'notes', 'categories', 'uncategorized',
            'openTasks', 'doneTasks', 'hotlist', 'popup', 'people', 'postits', 'sessionEndsAt', 'goHome'
        ));
    }
}
