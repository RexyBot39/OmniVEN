<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A question a shopper might ask an AI assistant, which we check each month for our brand. */
class AiQuestion extends Model
{
    protected $fillable = ['brand_id', 'question', 'active', 'sort'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort' => 'integer'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(AiCheck::class);
    }

    /** A link that opens the assistant with this question typed in, or its home page. */
    public function askUrl(string $engine): ?string
    {
        $e = config('ai_answers.engines.'.$engine);
        if (! $e) {
            return null;
        }

        return $e['ask'] ? $e['ask'].rawurlencode($this->question) : ($e['home'] ?? null);
    }
}
