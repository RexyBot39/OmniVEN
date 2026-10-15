<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Brand;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admins add and maintain each brand's platform accounts. */
class AccountController extends Controller
{
    public function create(Request $request): View
    {
        $brand = Brand::where('slug', $request->input('brand'))->first();
        $platform = array_key_exists($request->input('platform'), config('omniven.platforms')) ? $request->input('platform') : 'instagram';

        return $this->form(new Account(['brand_id' => $brand?->id, 'platform' => $platform]), $request);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = Account::create($this->validated($request));
        Activity::log('account.created', 'Added account “'.$account->title().'”', $account);

        return $this->back($request, $account, 'Added '.$account->title().'.');
    }

    public function edit(Request $request, Account $account): View
    {
        return $this->form($account, $request);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $account->update($this->validated($request));
        Activity::log('account.updated', 'Edited account “'.$account->title().'”', $account);

        return $this->back($request, $account, 'Saved '.$account->title().'.');
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        Activity::log('account.deleted', 'Removed account “'.$account->title().'”', $account);
        $account->delete();

        return $this->back($request, $account, 'Removed '.$account->title().'.');
    }

    /** "I just checked this login still works." */
    public function verify(Request $request, Account $account): RedirectResponse
    {
        $account->update(['verified_on' => today()]);
        Activity::log('account.verified', 'Verified access to “'.$account->title().'”', $account);

        return $this->back($request, $account, 'Marked '.$account->title().' as verified today.');
    }

    private function form(Account $account, Request $request): View
    {
        return view('accounts.form', [
            'account' => $account,
            'brands' => Brand::orderBy('sort')->orderBy('name')->get(),
            'return' => $this->returnTo($request),
        ]);
    }

    private function returnTo(Request $request): string
    {
        return in_array($request->input('return'), ['hub', 'access', 'brands'], true) ? $request->input('return') : 'hub';
    }

    private function back(Request $request, Account $account, string $msg): RedirectResponse
    {
        $to = $this->returnTo($request);
        $brand = $account->brand;
        $url = match (true) {
            $to === 'hub' && $brand !== null => route('brands.show', $brand),
            $to === 'access' => route('dashboard').'#access',
            default => route('dashboard').'#brands',
        };

        return redirect()->to($url)->with('status', $msg);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'brand_id' => ['nullable', 'exists:brands,id'],
            'platform' => ['required', Rule::in(array_keys(config('omniven.platforms')))],
            'handle' => ['nullable', 'string', 'max:160'],
            'url' => ['nullable', 'url:http,https', 'max:1024'],
            'account_ref' => ['nullable', 'string', 'max:120'],
            'login' => ['nullable', 'string', 'max:200'],
            'owner' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'verified_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
    }
}
