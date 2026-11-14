<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantCreditEntry;
use App\Models\AssistantEvalRun;
use App\Models\AssistantTrace;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Assistant\Availability;
use App\Services\Assistant\Credits;
use App\Services\Assistant\Indexer;
use App\Services\Assistant\Prompt;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Nicolás for the owner: whether it can open (key, switch, index,
 * evaluation of this version, the day's budget), what it costs, and the
 * trace of every question. The traces show who asked, the route, the model,
 * tokens, cost, credits and flags; never what was asked (that stays in the
 * student's conversation) and never the key.
 */
class AssistantController extends Controller
{
    public function __construct(private readonly Availability $availability) {}

    public function show(): JsonResponse
    {
        $since = CarbonImmutable::now(config('app.business_timezone'))->subDays(29)->startOfDay()->utc();
        $byRoute = AssistantTrace::query()->where('created_at', '>=', $since)
            ->select('route', DB::raw('count(*) as n'), DB::raw('sum(cost_usd) as cost'), DB::raw('sum(credits) as credits'))
            ->groupBy('route')->get()
            ->map(fn ($row) => ['route' => $row->route, 'count' => (int) $row->n, 'cost_usd' => round((float) $row->cost, 4), 'credits' => (int) $row->credits]);
        $eval = $this->availability->lastEval();
        $index = Indexer::state();

        return response()->json(['data' => [
            'name' => config('assistant.name'),
            'configured' => $this->availability->configured(),
            'enabled' => $this->availability->enabled(),
            'launched' => $this->availability->launched(),
            'models' => [
                'small' => ['id' => config('assistant.models.small.id'), 'effort' => config('assistant.models.small.effort'), 'credits' => Credits::cost('small')],
                'large' => ['id' => config('assistant.models.large.id'), 'effort' => config('assistant.models.large.effort'), 'credits' => Credits::cost('large')],
            ],
            'prompt_version' => Prompt::make()->version(),
            'index' => ['version' => $index['version'] ?? null, 'chunks' => $index['chunks'] ?? 0, 'built_at' => $index['built_at'] ?? null],
            'eval' => [
                'passed' => $this->availability->evalPassed(),
                'last' => $eval === null ? null : [
                    'passed' => $eval->passed,
                    'score' => $eval->score,
                    'total' => $eval->total,
                    'passed_count' => $eval->passed_count,
                    'ran_at' => $eval->ran_at?->toIso8601String(),
                    'failures' => collect($eval->report)->where('passed', false)->pluck('id')->values(),
                ],
            ],
            'budget' => ['daily_usd' => (float) config('assistant.daily_budget_usd'), 'spent_today_usd' => round($this->availability->spentToday(), 4)],
            'credits' => ['monthly' => config('assistant.credits.monthly')],
            'last_30_days' => [
                'questions' => (int) $byRoute->sum('count'),
                'cost_usd' => round((float) $byRoute->sum('cost_usd'), 4),
                'by_route' => $byRoute->values(),
            ],
            'evals' => AssistantEvalRun::query()->latest('ran_at')->latest('id')->limit(10)->get()->map(fn (AssistantEvalRun $run) => [
                'id' => $run->id,
                'passed' => $run->passed,
                'score' => $run->score,
                'total' => $run->total,
                'passed_count' => $run->passed_count,
                'prompt_version' => $run->prompt_version,
                'models' => $run->models,
                'index_version' => $run->index_version,
                'ran_at' => $run->ran_at?->toIso8601String(),
            ]),
        ]]);
    }

    public function traces(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'route' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', 'string', 'max:16'],
            'flagged' => ['nullable', 'boolean'],
        ]);

        $traces = AssistantTrace::query()
            ->with('user:id,name,email')
            ->when($filters['route'] ?? null, fn ($q, $route) => $q->where('route', $route))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['flagged'] ?? false, fn ($q) => $q->whereNotNull('flags')->where('flags', '!=', '[]'))
            ->latest('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($traces->items())->map(fn (AssistantTrace $t) => [
                'id' => $t->id,
                'created_at' => $t->created_at?->toIso8601String(),
                'user' => $t->user === null ? null : ['id' => $t->user->id, 'name' => $t->user->name, 'email' => $t->user->email],
                'route' => $t->route,
                'status' => $t->status,
                'model' => $t->model,
                'prompt_version' => $t->prompt_version,
                'index_version' => $t->index_version,
                'tokens' => ['input' => $t->input_tokens, 'output' => $t->output_tokens, 'cache_read' => $t->cache_read_tokens, 'cache_write' => $t->cache_write_tokens],
                'cost_usd' => $t->cost_usd,
                'credits' => $t->credits,
                'latency_ms' => $t->latency_ms,
                'chunks' => count($t->chunks ?? []),
                'tools' => $t->tools ?? [],
                'flags' => $t->flags ?? [],
                'error' => $t->error,
            ]),
            'meta' => ['current_page' => $traces->currentPage(), 'last_page' => $traces->lastPage(), 'total' => $traces->total()],
        ]);
    }

    /** Credits for one student this month (a goodwill top-up, or negative to correct one), written to the ledger. */
    public function grant(Request $request, Credits $credits): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'credits' => ['required', 'integer', 'between:-10000,10000', 'not_in:0'],
            'note' => ['required', 'string', 'max:160'],
        ]);
        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $entry = $credits->grant($user, (int) $data['credits'], $data['note']);
        AuditLog::record($request->user(), 'assistant.credits_granted', $user, ['credits' => (int) $data['credits'], 'entry' => $entry->id]);

        return response()->json(['data' => [
            'user' => ['id' => $user->id, 'email' => $user->email],
            'credits' => (int) $data['credits'],
            'balance' => (int) AssistantCreditEntry::query()->where('user_id', $user->id)->where('period', Credits::period())->sum('delta'),
        ]], 201);
    }
}
