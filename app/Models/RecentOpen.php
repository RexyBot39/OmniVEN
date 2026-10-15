<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The last time a person opened a tool, account or brand. */
class RecentOpen extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'item_type', 'item_id', 'opened_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }
}
