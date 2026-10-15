<?php

namespace App\Models\Concerns;

use App\Models\Favorite;
use App\Models\RecentOpen;
use App\Support\Launchpad;

/** Tools, accounts and brands can be starred and show up as recent; clean up when one is deleted. */
trait OnLaunchpad
{
    protected static function bootOnLaunchpad(): void
    {
        static::deleted(function ($model) {
            $type = Launchpad::typeOf($model);
            Favorite::where('item_type', $type)->where('item_id', $model->getKey())->delete();
            RecentOpen::where('item_type', $type)->where('item_id', $model->getKey())->delete();
        });
    }

    /** e.g. "tool:12", the key the star buttons and launcher use. */
    public function launchKey(): string
    {
        return Launchpad::typeOf($this).':'.$this->getKey();
    }
}
