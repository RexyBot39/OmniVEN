<?php

namespace App\Http\Controllers;

use App\Support\LinkChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use function Illuminate\Support\defer;

/** Admins: run the broken-link check now instead of waiting for tonight. */
class LinkCheckController extends Controller
{
    public function run(Request $request): RedirectResponse
    {
        defer(fn () => LinkChecker::run());
        $to = $request->input('return') === 'review' ? route('access.review') : route('dashboard').'#access';

        return redirect()->to($to)->with('status', 'Checking every link now. Refresh in a minute or two to see the results.');
    }
}
