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

    private function form(Tool $tool, Request $request): View
    {
        return view('tools.form', ['tool' => $tool, 'brands' => Brand::orderBy('sort')->get(), 'return' => $this->returnTab($request)]);
    }

    /** Which tab to go back to: the Access tab when editing from there, otherwise Tools. */
    private function returnTab(Request $request): string
    {
        return $request->input('return') === 'access' ? 'access' : 'tools';
    }

    private function back(Request $request, string $msg): RedirectResponse
    {
        return redirect()->to(route('dashboard').'#'.$this->returnTab($request))->with('status', $msg);
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
