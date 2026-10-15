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
        $data = $this->target($request);
        if (in_array($data['type'], Launchpad::PLACES, true)) {
            abort_unless(Launchpad::placeExists($user, $data['type'], (int) $data['id']), 404);
            $starred = Launchpad::toggle($user, $data['type'], (int) $data['id']);
        } else {
            $starred = Launchpad::toggle($user, Launchpad::find($data['type'], $data['id']) ?? abort(404));
        }

        if (! $request->expectsJson()) {
            return back()->with('status', $starred ? 'Added to your launchpad.' : 'Removed from your launchpad.');
        }

        return response()->json([
            'starred' => $starred,
            'html' => view('partials.launchpad', ['launchpad' => Launchpad::forUser($user)])->render(),
        ]);
    }

    /** Save the order someone arranged their stars in. */
    public function order(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keys' => ['required', 'array', 'max:200'],
            'keys.*' => ['string', 'max:40', 'regex:/^[a-z]+:\d+$/'],
        ]);
        Launchpad::reorder($request->user(), $data['keys']);

        return response()->json([
            'html' => view('partials.launchpad', ['launchpad' => Launchpad::forUser($request->user()), 'arranging' => $request->boolean('arranging', true)])->render(),
        ]);
    }

    /** Called in the background when someone opens a tool, account or brand. Pages aren't tracked. */
    public function open(Request $request): Response
    {
        $data = $this->target($request);
        $item = Launchpad::find($data['type'], $data['id']);
        if ($item) {
            Launchpad::recordOpen($request->user(), $item);
        }

        return response()->noContent();
    }

    private function target(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', Rule::in([...array_keys(Launchpad::TYPES), ...Launchpad::PLACES])],
            'id' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ]);
    }
}
