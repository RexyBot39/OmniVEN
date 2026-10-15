<?php

namespace App\Http\Controllers;

use App\Support\Launchpad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Each person's own stars and recently opened items. Nobody sees or changes anyone else's. */
class LaunchpadController extends Controller
{
    public function favorite(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $starred = Launchpad::toggle($user, $this->item($request));

        if (! $request->expectsJson()) {
            return back()->with('status', $starred ? 'Added to your launchpad.' : 'Removed from your launchpad.');
        }

        return response()->json([
            'starred' => $starred,
            'html' => view('partials.launchpad', ['launchpad' => Launchpad::forUser($user)])->render(),
        ]);
    }

    /** Called in the background when someone opens a tool, account or brand. */
    public function open(Request $request): Response
    {
        Launchpad::recordOpen($request->user(), $this->item($request));

        return response()->noContent();
    }

    private function item(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(Launchpad::TYPES))],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        return Launchpad::find($data['type'], $data['id']) ?? abort(404);
    }
}
