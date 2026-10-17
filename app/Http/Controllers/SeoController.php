<?php

namespace App\Http\Controllers;

use App\Support\Seo\SearchConsole;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Search: each brand's Google Search Console picture, last 28 days against the 28 before. */
class SeoController extends Controller
{
    public function index(Request $request): View
    {
        SearchConsole::refreshIfStale();
        $brands = SearchConsole::brands();
        $brand = $brands->get((string) $request->query('brand')) ?? $brands->first();

        return view('seo.index', [
            'configured' => SearchConsole::configured(),
            'status' => SearchConsole::status(),
            'brands' => $brands,
            'brand' => $brand,
            's' => $brand ? SearchConsole::summary($brand->slug) : null,
        ]);
    }
}
