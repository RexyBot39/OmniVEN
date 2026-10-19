<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Brand;
use App\Support\Activity;
use App\Support\Assistant\Assistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The OmniVEN assistant: private conversations, one per question thread, answered in the background. */
class AssistantController extends Controller
{
    public function index(Request $request): View
    {
        return $this->page($request, null);
    }

    public function show(Request $request, AssistantConversation $conversation): View
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        return $this->page($request, $conversation->load('messages'));
    }

    public function ask(Request $request): RedirectResponse
    {
        abort_unless(Assistant::configured(), 404);
        $me = $request->user();
        $data = $request->validate([
            'question' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
            'focus' => ['nullable', 'string', 'max:60', Rule::in(array_merge(array_keys(Assistant::FOCUS), Brand::pluck('slug')->map(fn ($s) => 'brand:'.$s)->all()))],
        ], ['question.required' => 'Type a question first.']);

        $today = AssistantMessage::where('role', 'user')->whereHas('conversation', fn ($q) => $q->where('user_id', $me->id))
            ->where('created_at', '>=', now(config('app.timezone'))->startOfDay())->count();
        if ($today >= config('assistant.daily_limit')) {
            return back()->withInput()->withErrors(['question' => 'You’ve asked '.$today.' questions today, the daily limit. It resets at midnight.']);
        }

        $conv = ! empty($data['conversation_id']) ? AssistantConversation::where('user_id', $me->id)->findOrFail($data['conversation_id']) : null;
        if ($conv && $conv->messages()->where('status', 'pending')->exists()) {
            return redirect()->route('assistant.show', $conv)->with('status', 'Still working on the last question. Ask the next one when it’s answered.');
        }
        $conv ??= AssistantConversation::create(['user_id' => $me->id, 'title' => mb_substr(trim(preg_replace('/\s+/', ' ', $data['question'])), 0, 120), 'focus' => $data['focus'] ?? null]);
        if (array_key_exists('focus', $data)) {
            $conv->update(['focus' => $data['focus'] ?: null]);
        }
        $conv->messages()->create(['role' => 'user', 'content' => trim($data['question'])]);
        $answer = $conv->messages()->create(['role' => 'assistant', 'status' => 'pending']);
        $conv->touch();
        // Activity shows that the assistant was used, never what was asked.
        Activity::log('assistant.asked', 'Asked the OmniVEN assistant a question');

        dispatch(fn () => Assistant::answer($answer->fresh()))->afterResponse();

        return redirect()->to(route('assistant.show', $conv).'#m-'.$answer->id);
    }

    /** The page checks back here until the answer is ready. */
    public function poll(Request $request, AssistantMessage $message): JsonResponse
    {
        abort_unless($message->conversation?->user_id === $request->user()->id, 404);

        return response()->json([
            'status' => $message->status,
            'html' => $message->status === 'pending' ? null : view('assistant.message', ['m' => $message, 'isAdmin' => $request->user()->can('admin')])->render(),
        ]);
    }

    public function destroy(Request $request, AssistantConversation $conversation): RedirectResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
        $conversation->delete();

        return redirect()->route('assistant')->with('status', 'Conversation deleted.');
    }

    private function page(Request $request, ?AssistantConversation $conv): View
    {
        $me = $request->user();
        $isAdmin = $me->can('admin');
        $usage = null;
        if ($isAdmin) {
            $month = AssistantMessage::where('role', 'assistant')->where('created_at', '>=', now(config('app.timezone'))->startOfMonth());
            $in = (int) (clone $month)->sum('input_tokens');
            $out = (int) (clone $month)->sum('output_tokens');
            $usage = ['answers' => (clone $month)->where('status', 'done')->count(), 'cost' => $in / 1e6 * config('assistant.price.input') + $out / 1e6 * config('assistant.price.output')];
        }

        return view('assistant.index', [
            'configured' => Assistant::configured(),
            'conv' => $conv,
            'conversations' => AssistantConversation::where('user_id', $me->id)->latest('updated_at')->limit(30)->get(),
            'focuses' => Assistant::FOCUS,
            'brands' => Brand::orderBy('sort')->get(),
            'focus' => $conv?->focus ?? (string) $request->query('focus', ''),
            'prefill' => (string) $request->query('q', ''),
            'isAdmin' => $isAdmin,
            'usage' => $usage,
            'model' => config('assistant.model'),
            'effort' => config('assistant.effort'),
        ]);
    }
}
