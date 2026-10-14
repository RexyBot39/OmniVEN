<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlackRead extends Model
{
    protected $fillable = ['user_id', 'slack_channel_id', 'last_read_ts'];
}
