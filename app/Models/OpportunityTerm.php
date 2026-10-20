<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A phrase searched for in government bids every day (e.g. "safety glasses"). */
class OpportunityTerm extends Model
{
    protected $fillable = ['term', 'created_by'];
}
