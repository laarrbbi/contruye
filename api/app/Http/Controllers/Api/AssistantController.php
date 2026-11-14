<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Assistant\Assistant;
use App\Services\Assistant\AssistantUnavailable;
use App\Services\Assistant\Availability;
use App\Services\Assistant\Credits;
use App\Services\Assistant\Indexer;
use App\Services\Assistant\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Nicolás for the student: whether it answers them and with how many
 * credits, their conversations, a question. A student only ever reaches
 * their own conversations (another's is a 404, as if it did not exist).
 * The browser never talks to the model: this API does, with the key on the
 * server, after the guard, the credits and the budget.
 */
class AssistantController extends Controller
{
    public function __construct(
        private readonly Assistant $assistant,
        private readonly Availability $availability,
        private readonly Credits $credits,
    ) {}

    /** Public: whether the assistant has opened (the menu drops its «Pronto»). */
    public function public(): JsonResponse
    {
        return response()->json(['data' => ['name' => config('assistant.name'), 'launched' => $this->availability->launched()]]);
    }

    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $course = Indexer::course();
        if ($course === null) {
            return response()->json(['data' => ['name' => config('assistant.name'), 'available' => false, 'reason' => 'not_configured', 'launched' => false, 'credits' => null]]);
        }
        $scope = Scope::for($user, $course);
        $state = $this->availability->for($user, $scope);

        return response()->json(['data' => [
            'name' => config('assistant.name'),
            'available' => $state['available'],
            'reason' => $state['reason'],
            'launched' => $this->availability->launched(),
            // An admin trying it before it opens is told so.
            'preview' => $state['available'] && ! $this->availability->launched(),
            'course' => $course->slug,
            'credits' => $scope->eligible() ? [
                'balance' => $this->credits->peek($user, $scope),
                'monthly' => Credits::allowance($scope->tier()),
                'resets_at' => Credits::resetsAt()->toIso8601String(),
                'small' => Credits::cost('small'),
                'large' => Credits::cost('large'),
            ] : null,
            'max_question_chars' => (int) config('assistant.limits.max_question_chars'),
        ]]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $conversations = AssistantConversation::query()
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')->latest('id')
            ->limit(50)
            ->get(['id', 'title', 'updated_at']);

        return response()->json(['data' => $conversations->map(fn (AssistantConversation $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'updated_at' => $c->updated_at?->toIso8601String(),
        ])]);
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $found = $this->own($request, $conversation);
        $messages = $found->messages()->orderBy('id')->get();

        return response()->json(['data' => [
            'id' => $found->id,
            'title' => $found->title,
            'messages' => $messages->map(fn ($m) => Assistant::present($m, $request->user()))->values(),
        ]]);
    }

    public function destroy(Request $request, int $conversation): Response
    {
        $this->own($request, $conversation)->delete();

        return response()->noContent();
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:'.(int) config('assistant.limits.max_question_chars')],
            'conversation_id' => ['nullable', 'integer'],
        ]);
        /** @var User $user */
        $user = $request->user();

        $conversation = isset($data['conversation_id']) ? $this->own($request, (int) $data['conversation_id']) : null;
        if ($conversation === null && AssistantConversation::query()->where('user_id', $user->id)->count() >= (int) config('assistant.limits.conversations')) {
            // The oldest goes, as a chat app does, rather than a refusal.
            AssistantConversation::query()->where('user_id', $user->id)->oldest('updated_at')->oldest('id')->first()?->delete();
        }

        try {
            $result = $this->assistant->ask($user, $conversation, $data['question'], app()->getLocale());
        } catch (AssistantUnavailable $e) {
            return response()->json(['message' => $this->reason($e->reason), 'reason' => $e->reason], $e->status());
        }

        return response()->json(['data' => [
            'conversation' => ['id' => $result['conversation']->id, 'title' => $result['conversation']->title],
            'question' => Assistant::present($result['question'], $user),
            'answer' => Assistant::present($result['answer'], $user),
            'credits' => ['balance' => $result['balance'], 'spent' => $result['spent']],
        ]], 201);
    }

    private function own(Request $request, int $id): AssistantConversation
    {
        return AssistantConversation::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function reason(string $reason): string
    {
        return match ($reason) {
            'no_credits' => __('You have used this month\'s credits. They come back on the first of next month.'),
            'busy' => __('Wait for the answer to your previous question.'),
            'not_enrolled' => __('The assistant answers the course\'s students.'),
            'paused' => __('The assistant is resting until tomorrow. Try again then.'),
            'coming_soon' => __('The assistant is not open yet.'),
            default => __('The assistant is not available right now.'),
        };
    }
}
