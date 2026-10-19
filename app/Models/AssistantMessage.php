<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A question (role "user") or an answer (role "assistant"; status pending, done or error). */
class AssistantMessage extends Model
{
    protected $fillable = ['assistant_conversation_id', 'role', 'content', 'status', 'error', 'tools', 'input_tokens', 'output_tokens'];

    protected function casts(): array
    {
        return ['tools' => 'array', 'input_tokens' => 'integer', 'output_tokens' => 'integer'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    /** The answer as safe HTML: Markdown, with any raw HTML stripped and unsafe links dropped. */
    public function html(): string
    {
        return Str::markdown((string) $this->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
