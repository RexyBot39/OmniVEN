<?php

namespace App\Models\Concerns;

use App\Models\AccessRequest;
use App\Support\Launchpad;

/** Tools and brand accounts people can request access to and admins re-verify each quarter. */
trait HasAccessRequests
{
    protected static function bootHasAccessRequests(): void
    {
        static::deleted(function ($model) {
            AccessRequest::where('item_type', Launchpad::typeOf($model))->where('item_id', $model->getKey())->delete();
        });
    }

    /** Never verified, or not in the last 90 days. */
    public function needsVerify(): bool
    {
        return ! $this->verified_on || $this->verified_on->lt(today()->subDays(config('omniven.verify_days')));
    }
}
