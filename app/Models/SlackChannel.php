<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlackChannel extends Model
{
    protected $fillable = ['channel_id', 'name', 'sort'];
}
