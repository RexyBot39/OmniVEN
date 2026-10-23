<?php

namespace App\Support\Assistant;

use App\Models\AssistantMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Answers one question with OpenAI's Responses API: the model looks things up with the read-only tools
 * (as many rounds as it needs, up to a limit), then writes the answer. Stateless: nothing is stored at
 * OpenAI, so reasoning is passed back encrypted between rounds. The key never leaves the server.
 */
class Assistant
{
    public const FOCUS = [
        '' => 'Everything',
        'sales' => 'Sales and revenue',
        'tasks' => 'Tasks, routines and the team',
        'search' => 'Search and content',
        'email' => 'Email and SMS',
        'affiliates' => 'Affiliates and outreach',
        'reputation' => 'Reviews, Reddit and AI answers',
        'campaigns' => 'Campaigns and the calendar',
        'site' => 'Site check and coverage',
    ];

    public static function configured(): bool
    {
        return filled(config('assistant.key'));
    }

    /** Work out the answer for a pending assistant message (runs after the page has been sent). */
    public static function answer(AssistantMessage $msg): void
    {
        // Only once per question: a message that's already answered (or failed) is left alone.
        if ($msg->fresh()?->status !== 'pending') {
            return;
        }
        @set_time_limit(0);
        $conv = $msg->conversation()->with('user')->first();
        try {
            $tools = new Tools($conv->user);
            $input = self::history($msg);
            $used = [];
            $usage = ['in' => 0, 'out' => 0];
            for ($round = 0; $round <= config('assistant.max_tool_rounds'); $round++) {
                $last = $round === (int) config('assistant.max_tool_rounds');
                $res = self::request($input, $last ? [] : $tools->definitions(), $conv->user, $conv->focus);
                $usage['in'] += (int) ($res['usage']['input_tokens'] ?? 0);
                $usage['out'] += (int) ($res['usage']['output_tokens'] ?? 0);
                $calls = array_values(array_filter($res['output'] ?? [], fn ($o) => ($o['type'] ?? '') === 'function_call'));
                // Everything the model produced goes back in, reasoning included, so it keeps its train of thought.
                foreach ($res['output'] ?? [] as $o) {
                    $input[] = $o;
                }
                if (! $calls) {
                    $text = self::text($res);
                    if ($text === '') {
                        throw new RuntimeException(($res['status'] ?? '') === 'incomplete'
                            ? 'The answer ran past the length limit. Ask a narrower question, or raise OPENAI_MAX_OUTPUT_TOKENS.'
                            : 'OpenAI returned an empty answer. Try asking again.');
                    }
                    $msg->update(['content' => $text, 'status' => 'done', 'tools' => array_values(array_unique($used)), 'input_tokens' => $usage['in'], 'output_tokens' => $usage['out']]);

                    return;
                }
                foreach ($calls as $c) {
                    $args = json_decode((string) ($c['arguments'] ?? '{}'), true);
                    $used[] = $c['name'];
                    $input[] = ['type' => 'function_call_output', 'call_id' => $c['call_id'],
                        'output' => json_encode($tools->call((string) $c['name'], is_array($args) ? $args : []), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)];
                }
            }
            throw new RuntimeException('It kept looking things up without answering. Try a more specific question.');
        } catch (Throwable $e) {
            $text = $e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong while answering ('.class_basename($e).').';
            Log::warning('Assistant answer failed', ['message_id' => $msg->id, 'error' => $text]);
            $msg->update(['status' => 'error', 'error' => mb_substr($text, 0, 500)]);
        }
    }

    /** The conversation so far: earlier turns (text only), then this question. */
    private static function history(AssistantMessage $msg): array
    {
        $turns = AssistantMessage::where('assistant_conversation_id', $msg->assistant_conversation_id)->where('id', '<', $msg->id)
            ->where(fn ($q) => $q->where('role', 'user')->orWhere('status', 'done'))->orderByDesc('id')
            ->limit(config('assistant.history_turns') * 2 + 1)->get()->reverse();

        return $turns->map(fn ($m) => ['role' => $m->role === 'user' ? 'user' : 'assistant', 'content' => (string) $m->content])->values()->all();
    }

    private static function request(array $input, array $tools, User $user, ?string $focus): array
    {
        $body = [
            'model' => config('assistant.model'),
            'instructions' => self::instructions($user, $focus),
            'input' => $input,
            'reasoning' => ['effort' => config('assistant.effort')],
            'max_output_tokens' => config('assistant.max_output_tokens'),
            'store' => false,
            'include' => ['reasoning.encrypted_content'],
        ];
        if ($tools) {
            $body['tools'] = $tools;
        }
        return self::post($body);
    }

    /** One call to OpenAI's Responses API, with errors explained in plain words. The key never leaves the server. */
    public static function post(array $body): array
    {
        $res = Http::withToken((string) config('assistant.key'))->acceptJson()->asJson()
            ->connectTimeout(15)->timeout(config('assistant.timeout'))
            ->post(rtrim((string) config('assistant.url'), '/').'/responses', $body);
        if (! $res->successful()) {
            $detail = (string) ($res->json('error.message') ?? '');
            throw new RuntimeException(match (true) {
                $res->status() === 401 => 'OpenAI refused the key (HTTP 401). Check OPENAI_API_KEY in Railway.',
                $res->status() === 403 => 'OpenAI says this key can’t use '.config('assistant.model').' (HTTP 403). Check the project’s model access in the OpenAI dashboard.',
                $res->status() === 404 => 'OpenAI doesn’t know the model '.config('assistant.model').' (HTTP 404). Check OPENAI_MODEL.',
                $res->status() === 429 => 'OpenAI is rate-limiting or the account is out of credit (HTTP 429). Try again in a minute, or check billing.',
                $res->status() === 400 => 'OpenAI rejected the request (HTTP 400).'.($detail !== '' ? ' '.mb_substr($detail, 0, 200) : ''),
                default => 'OpenAI answered HTTP '.$res->status().'. Try again in a minute.',
            });
        }

        return (array) $res->json();
    }

    public static function text(array $res): string
    {
        $parts = [];
        foreach ($res['output'] ?? [] as $o) {
            if (($o['type'] ?? '') === 'message') {
                foreach ($o['content'] ?? [] as $c) {
                    if (in_array($c['type'] ?? '', ['output_text', 'text'], true)) {
                        $parts[] = (string) ($c['text'] ?? '');
                    }
                }
            }
        }

        return trim(implode("\n\n", $parts));
    }

    private static function instructions(User $user, ?string $focus): string
    {
        $focusLine = $focus && isset(self::FOCUS[$focus]) ? 'The person chose to focus on: '.self::FOCUS[$focus].'. Lean on the matching lookups, but bring in anything else that explains it.' : '';
        if (str_starts_with((string) $focus, 'brand:')) {
            $focusLine = 'The person chose to focus on one brand: '.substr($focus, 6).'. Pass that brand to every lookup that takes one.';
        }

        return implode("\n\n", array_filter([
            'You are the analyst built into OmniVEN, the marketing control center for 39DollarGlasses and its sister brands (Ocusafe prescription safety glasses, Ocusleep, Neurolux and others). You help the marketing team understand their numbers and work, and decide what to do next to grow sales across every channel.',
            'Always get facts from the lookups before answering. Call "overview" first unless the question is narrow. Never invent numbers, dates, names or links; if a lookup says a source isn’t connected or has no data, say so plainly and suggest how to connect it. Say which OmniVEN page each number comes from (Sales, Search, Reviews, Tasks, Campaigns, Outreach, Site check, Coverage and so on).',
            'Go deep: compare periods and brands, look for causes across sections (for example a revenue drop alongside falling search clicks, an unsent campaign or overdue tasks), and flag risks. Then give concrete suggestions: who should do what, by when, and why it should help, in order of expected impact. When a suggestion is a task, phrase it so it can be pasted into OmniVEN as a task title.',
            'When asked about reviews or what customers think, read the reviews themselves with "review_analysis" (best and worst separately, and a keyword pass for any theme that stands out). Group what they say into themes with how many reviews mention each, quote a few short phrases, and turn them into key learnings and action items (marketing copy and ads to use the praise, fixes for the complaints) with who and why.',
            'Write for busy marketers: start with a two- or three-sentence answer, then the detail with short headings, bullets and small tables where they help. Use plain words, US English, dollars and US Eastern dates. Brand spellings: 39DollarGlasses, Ocusafe, Ocusleep, Neurolux.',
            'You can only read. You can’t change anything in OmniVEN or elsewhere; if asked to, explain what the person can do instead. Never ask for or repeat passwords, codes or keys.',
            'The person asking is '.$user->name.($user->is_admin ? ', an admin' : '').'.',
            $focusLine,
        ]));
    }
}
