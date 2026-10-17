<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What one AI assistant said about our brand for one question in one month. */
class AiCheck extends Model
{
    protected $fillable = ['ai_question_id', 'engine', 'month', 'result', 'competitors', 'user_id'];

    /** Stored as a plain Y-m-d date (the first of the month) so lookups match on every database. */
    protected function month(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v === null ? null : CarbonImmutable::parse(substr((string) $v, 0, 10)),
            set: fn ($v) => CarbonImmutable::parse($v)->format('Y-m-d'),
        );
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AiQuestion::class, 'ai_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
