<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One starred tool, account, brand, page or tool category on a person's launchpad. */
class Favorite extends Model
{
    protected $fillable = ['user_id', 'item_type', 'item_id', 'position'];
}
