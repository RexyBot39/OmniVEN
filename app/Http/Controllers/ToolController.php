<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Http\Controllers\Concerns\SyncsBrands;
use App\Models\Brand;
use App\Models\Tool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ToolController extends Controller
{
    use SyncsBrands;

    public function create(Request $request): View
    {
        return $this->form(new Tool(['status' => 'active', 'all_brands' => true, 'sort' => 100]), $request);
    }

    public function store(Request $request): RedirectResponse
    {
        $tool = new Tool($this->validated($request));
        $this->applyBrands($tool, $request);
        Activity::log('tool.created', "Added tool “{$tool->name}”", $tool);

        return $this->back($request, "Added {$tool->name}.");
    }

    public function edit(Request $request, Tool $tool): View
    {
        return $this->form($tool->load('brands'), $request);
    }

    public function update(Request $request, Tool $tool): RedirectResponse
    {
        $tool->fill($this->validated($request));
        $this->applyBrands($tool, $request);
        Activity::log('tool.updated', "Edited tool “{$tool->name}”", $tool);

        return $this->back($request, "Saved {$tool->name}.");
    }

    public function destroy(Request $request, Tool $tool): RedirectResponse
    {
        Activity::log('tool.deleted', "Removed tool “{$tool->name}”", $tool);
        $tool->delete();

        return $this->back($request, "Removed {$tool->name}.");
    }

    /** "I just checked this login and owner are still right." From the Access review. */
    public function verify(Request $request, Tool $tool): RedirectResponse
    {
        $tool->update(['verified_on' => today()]);
        Activity::log('tool.verified', "Verified access to “{$tool->name}”", $tool);

        return $this->back($request, "Marked {$tool->name} as verified today.");
    }

    private function form(Tool $tool, Request $request): View
    {
        return view('tools.form', ['tool' => $tool, 'brands' => Brand::orderBy('sort')->get(), 'return' => $this->returnTab($request)]);
    }

    /** Where to go back to: the Access tab or Access review when editing from there, otherwise Tools. */
    private function returnTab(Request $request): string
    {
        return in_array($request->input('return'), ['access', 'review'], true) ? $request->input('return') : 'tools';
    }

    private function back(Request $request, string $msg): RedirectResponse
    {
        $tab = $this->returnTab($request);
        $url = $tab === 'review' ? route('access.review') : route('dashboard').'#'.$tab;

        return redirect()->to($url)->with('status', $msg);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['nullable', 'url:http,https', 'max:1024'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(array_keys(config('omniven.statuses')))],
            'login' => ['nullable', 'string', 'max:200'],
            'owner' => ['nullable', 'string', 'max:200'],
            'access_note' => ['nullable', 'string', 'max:1000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ] + $this->brandRules());
        unset($data['brands']);
        $data['featured'] = $request->boolean('featured');
        $data['sort'] ??= 100;

        return $data;
    }
}
