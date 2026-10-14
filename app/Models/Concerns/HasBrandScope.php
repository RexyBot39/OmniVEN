<?php

namespace App\Models\Concerns;

trait HasBrandScope
{
    /** Space-separated brand slugs for client-side filtering ("all" when it applies to every brand). */
    public function brandFilterAttr(): string
    {
        return $this->all_brands ? 'all' : $this->brands->pluck('slug')->implode(' ');
    }

    public function brandLabels(): array
    {
        return $this->all_brands ? ['All brands'] : $this->brands->pluck('name')->all();
    }
}
