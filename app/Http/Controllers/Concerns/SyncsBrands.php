<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait SyncsBrands
{
    protected function brandRules(): array
    {
        return [
            'brands' => ['nullable', 'array'],
            'brands.*' => ['string', 'max:100'],
        ];
    }

    /** "all" in the brand checklist means the item applies to every brand. */
    protected function applyBrands(Model $model, Request $request): void
    {
        $selected = collect($request->input('brands', []));
        $model->all_brands = $selected->contains('all');
        $model->save();
        $ids = \App\Models\Brand::whereIn('slug', $selected->reject(fn ($s) => $s === 'all'))->pluck('id');
        $model->brands()->sync($model->all_brands ? [] : $ids);
    }
}
