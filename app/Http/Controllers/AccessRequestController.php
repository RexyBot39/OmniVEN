<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Models\Account;
use App\Models\Tool;
use App\Support\AccessNotifier;
use App\Support\Activity;
use App\Support\Launchpad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Request access (anyone signed in), grant or decline (admins), and the quarterly Access review. */
class AccessRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(AccessRequest::TYPES)],
            'id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $item = Launchpad::find($data['type'], $data['id']) ?? abort(404);
        $user = $request->user();

        $where = ['user_id' => $user->id, 'item_type' => $data['type'], 'item_id' => $item->getKey()];
        if (AccessRequest::open()->where($where)->exists()) {
            return $this->back($request)->with('status', 'You already asked for access to '.$this->name($item).'. An admin will get back to you.');
        }

        $req = AccessRequest::create($where + ['reason' => $data['reason'] ?? null, 'status' => 'open']);
        $req->setRelation('user', $user);
        Activity::log('access.requested', 'Requested access to “'.$this->name($item).'”', $item);
        AccessNotifier::requested($req);

        return $this->back($request)->with('status', 'Sent. An admin will share access to '.$this->name($item).' through the password manager.');
    }

    /** The person who asked can withdraw an open request. */
    public function destroy(Request $request, AccessRequest $accessRequest): RedirectResponse
    {
        abort_unless($accessRequest->user_id === $request->user()->id && $accessRequest->isOpen(), 403);
        $accessRequest->delete();

        return $this->back($request)->with('status', 'Request withdrawn.');
    }

    public function grant(Request $request, AccessRequest $accessRequest): RedirectResponse
    {
        return $this->decide($request, $accessRequest, 'granted');
    }

    public function decline(Request $request, AccessRequest $accessRequest): RedirectResponse
    {
        return $this->decide($request, $accessRequest, 'declined');
    }

    private function decide(Request $request, AccessRequest $req, string $status): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);
        if (! $req->isOpen()) {
            return $this->back($request)->with('status', 'That request was already handled.');
        }
        $req->update(['status' => $status, 'handled_by' => $request->user()->id, 'handled_at' => now(), 'decision_note' => $data['note'] ?? null]);
        $who = $req->user?->firstName() ?? 'Someone';
        Activity::log('access.'.$status, ucfirst($status).' '.$who.'’s access to “'.$req->itemName().'”', $req->item());

        return $this->back($request)->with('status', ($status === 'granted' ? 'Marked granted' : 'Declined').': '.$who.' · '.$req->itemName().'.');
    }

    /** Admins: one checklist of every tool and brand account, overdue first. */
    public function review(): View
    {
        $rank = fn ($i) => [$i->verified_on ? ($i->needsVerify() ? 1 : 2) : 0, $i->verified_on?->timestamp ?? 0];
        $tools = Tool::orderBy('name')->get()->sortBy($rank)->values();
        $accounts = Account::with('brand')->get()->sortBy(fn ($a) => [...$rank($a), $a->title()])->values();
        $quarterStart = now()->setTimezone(config('app.timezone'))->firstOfQuarter()->startOfDay();
        $all = $tools->concat($accounts);
        $doneThisQuarter = $all->filter(fn ($i) => $i->verified_on && $i->verified_on->gte($quarterStart))->count();

        return view('access.review', [
            'tools' => $tools, 'accounts' => $accounts, 'total' => $all->count(), 'done' => $doneThisQuarter,
            'quarter' => 'Q'.$quarterStart->quarter.' '.$quarterStart->year,
        ]);
    }

    private function name($item): string
    {
        return $item instanceof Account ? $item->title() : $item->name;
    }

    private function back(Request $request): RedirectResponse
    {
        $to = $request->input('return');
        if (is_string($to) && str_starts_with($to, 'hub:') && ($slug = substr($to, 4)) !== '') {
            return redirect()->route('brands.show', $slug);
        }

        return redirect()->to(route('dashboard').'#access');
    }
}
