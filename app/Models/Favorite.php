<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One starred tool, account or brand on a person's launchpad. */
class Favorite extends Model
{
    protected $fillable = ['user_id', 'item_type', 'item_id'];
}
