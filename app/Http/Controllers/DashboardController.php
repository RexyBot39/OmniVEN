<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Note;
use App\Models\Routine;
use App\Models\Tool;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
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

        return view('dashboard', compact('brands', 'tools', 'routines', 'notes', 'categories', 'uncategorized'));
    }
}
