<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A platform's star rating and review count on one day, read automatically or entered by hand. */
class ReviewSnapshot extends Model
{
    protected $fillable = ['platform', 'brand_id', 'day', 'rating', 'review_count', 'manual', 'user_id'];

    protected function casts(): array
    {
        return ['rating' => 'float', 'review_count' => 'integer', 'manual' => 'boolean'];
    }
}
