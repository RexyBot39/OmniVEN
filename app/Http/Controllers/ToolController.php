<?php

namespace App\Http\Controllers;

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

    public function create(): View
    {
        return $this->form(new Tool(['status' => 'active', 'all_brands' => true, 'sort' => 100]));
    }

    public function store(Request $request): RedirectResponse
    {
        $tool = new Tool($this->validated($request));
        $this->applyBrands($tool, $request);

        return redirect()->route('dashboard')->with('status', "Added {$tool->name}.");
    }

    public function edit(Tool $tool): View
    {
        return $this->form($tool->load('brands'));
    }

    public function update(Request $request, Tool $tool): RedirectResponse
    {
        $tool->fill($this->validated($request));
        $this->applyBrands($tool, $request);

        return redirect()->route('dashboard')->with('status', "Saved {$tool->name}.");
    }

    public function destroy(Tool $tool): RedirectResponse
    {
        $tool->delete();

        return redirect()->route('dashboard')->with('status', "Removed {$tool->name}.");
    }

    private function form(Tool $tool): View
    {
        return view('tools.form', ['tool' => $tool, 'brands' => Brand::orderBy('sort')->get()]);
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
