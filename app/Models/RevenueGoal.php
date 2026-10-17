<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A brand's revenue goal for one month (stored as the first of the month). */
class RevenueGoal extends Model
{
    protected $fillable = ['brand_id', 'month', 'goal_cents', 'user_id'];
}
