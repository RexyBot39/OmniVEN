<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One day's position for a tracked search (null: not in the results checked). */
class SeoRankCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['seo_keyword_id', 'checked_on', 'position'];

    protected function casts(): array
    {
        return ['checked_on' => 'date'];
    }
}
