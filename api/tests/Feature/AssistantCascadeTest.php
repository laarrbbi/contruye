<?php

use Anthropic\Core\Exceptions\BadRequestException;
use App\Models\AssistantConversation;
use App\Models\AssistantEvalRun;
use App\Models\AssistantTrace;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\Assistant\AnthropicLlm;
use App\Services\Assistant\Availability;
use App\Services\Assistant\Indexer;
use App\Services\Assistant\Llm;
use App\Services\Assistant\LlmError;
use App\Services\Assistant\Prompt;
use App\Services\Assistant\Router;
use App\Services\Assistant\Tools;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeLlm;

/*
 * What a question costs is, above all, which model answers it. So: the
 * cheapest model first, a hand-over at a time up the cascade, each tier with
 * its own budget of history, tool rounds and output, and nothing in the
 * prompt that the question does not need (a template's cells come when a
 * calculation asks for them). All with a fake model: no test spends a token.
 */

/** @return array{0: Course, 1: Module, 2: Module} */
function cascadeCourse(): array
{
    $course = Course::factory()->create(['status' => 'published']);
    $one = Module::factory()->for($course)->create(['order' => 1, 'kind' => 'phase']);
    $two = Module::factory()->for($course)->create(['order' => 2, 'kind' => 'phase']);
    Lesson::factory()->for($one)->create(['order' => 1, 'title_es' => 'Lección 1.3 — Due diligence',
        'content_es' => 'La nota simple se pide en el Registro de la Propiedad antes de firmar nada.']);
    app(Indexer::class)->rebuild();

    return [$course->fresh(), $one, $two];
}

function cascadeStudent(Course $course, ?Module $module = null): User
{
    $user = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'module_id' => $module?->id]);

    return $user;
}

function cascadeOpen(): FakeLlm
{
    config(['assistant.enabled' => true, 'assistant.anthropic.api_key' => 'sk-ant-api03-TESTKEYTESTKEYTESTKEY0000']);
    $fake = new FakeLlm;
    app()->instance(Llm::class, $fake);
    AssistantEvalRun::query()->create([
        'version_key' => app(Availability::class)->versionKey(), 'prompt_version' => Prompt::make()->version(),
        'models' => Availability::models(), 'index_version' => Indexer::version(), 'passed' => true,
        'score' => 1, 'total' => 1, 'passed_count' => 1, 'report' => [], 'ran_at' => now(),
    ]);

    return $fake;
}

function cascadeAsk(User $user, string $question, array $extra = [])
{
    return test()->actingAs($user)->withHeader('Accept-Language', 'es')->postJson('/api/assistant/messages', ['question' => $question] + $extra);
}

it('climbs the cascade one hand-over at a time, and charges only the model that answered', function () {
    [$course] = cascadeCourse();
    $fake = cascadeOpen()->push(
        FakeLlm::tool(Tools::ESCALATE, ['motivo' => 'Pide un análisis']),
        FakeLlm::tool(Tools::ESCALATE, ['motivo' => 'Varios escenarios encadenados'], 'toolu_02'),
        FakeLlm::text('Con los tres escenarios, el margen queda en el 11 % [1].', 'claude-opus-5-5'),
    );

    $response = cascadeAsk(cascadeStudent($course), '¿Me ayudas con mi promoción?')->assertCreated();

    expect(array_column($fake->requests, 'model'))->toBe(['claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-5-5'])
        // Each model starts afresh from the question and its documents: no half-answer travels up.
        ->and(array_map(fn ($request) => count($request['messages']), $fake->requests))->toBe([1, 1, 1])
        ->and(array_column($fake->requests[0]['tools'], 'name'))->toContain(Tools::ESCALATE)
        ->and(array_column($fake->requests[1]['tools'], 'name'))->toContain(Tools::ESCALATE)
        // The last of the cascade has nobody to hand it to.
        ->and(array_column($fake->requests[2]['tools'], 'name'))->not->toContain(Tools::ESCALATE)
        ->and($response->json('data.answer.route'))->toBe('large')
        ->and($response->json('data.credits.spent'))->toBe(5);

    $trace = AssistantTrace::query()->sole();
    expect($trace->flags)->toContain('handover:small>medium')->toContain('handover:medium>large')->toContain('rounds:3')
        ->and($trace->model)->toBe('claude-opus-5-5')
        // The bill is all three tries, each at its own price: 2 small tool calls' worth is not what it costs.
        ->and((float) $trace->cost_usd)->toBeGreaterThan(0.004)
        ->and($trace->credits)->toBe(5);
});

it('answers almost everything with the small model, for a tenth of a cent', function () {
    [$course, $one] = cascadeCourse();
    $fake = cascadeOpen();

    cascadeAsk(cascadeStudent($course, $one), '¿Dónde pido la nota simple?')->assertCreated();

    $request = $fake->requests[0];
    $trace = AssistantTrace::query()->sole();
    expect($request['model'])->toBe('claude-haiku-5-5')
        ->and($request['effort'])->toBe('low')
        ->and($trace->route)->toBe('small')
        // 1.000 tokens in, 3.000 read from the cache, 200 out, at Haiku's prices.
        ->and((float) $trace->cost_usd)->toBe(round((1000 * 0.10 + 3000 * 0.01 + 200 * 0.50) / 1_000_000, 6))
        ->and(Router::cascade())->toBe(['small', 'medium', 'large'])
        ->and(Router::next('small'))->toBe('medium')
        ->and(Router::next('large'))->toBeNull()
        ->and(Router::previous('small'))->toBeNull();
});

it('gives each tier its own budget of history, tool rounds, output and caching', function () {
    [$course] = cascadeCourse();
    config(['assistant.models.small.history_chars' => 5000, 'assistant.models.medium.history_chars' => 1500, 'assistant.models.small.tool_rounds' => 1]);
    $long = str_repeat('Una respuesta larga sobre la promoción. ', 60);
    $fake = cascadeOpen()->push(FakeLlm::text($long), FakeLlm::text($long), FakeLlm::text('Corta.'));
    $student = cascadeStudent($course);

    $first = cascadeAsk($student, 'Primera pregunta')->assertCreated();
    $conversation = ['conversation_id' => $first->json('data.conversation.id')];
    cascadeAsk($student, 'Segunda pregunta', $conversation)->assertCreated();
    cascadeAsk($student, 'Tercera pregunta', $conversation)->assertCreated();

    // The small model: the latest exchange and the one before it, while they fit in its budget.
    expect($fake->requests[2]['messages'])->toHaveCount(5)
        ->and($fake->requests[2]['cache_turn'])->toBeFalse()
        ->and($fake->requests[2]['max_tokens'])->toBe(3000);

    // The medium one, for an analysis: only the latest exchange, whole; its turn cached for its tool rounds.
    $fake->push(FakeLlm::text('Análisis.', 'claude-sonnet-5-5'));
    cascadeAsk($student, 'Analiza la viabilidad y compara los dos escenarios', $conversation)->assertCreated();
    expect($fake->requests[3]['model'])->toBe('claude-sonnet-5-5')
        ->and($fake->requests[3]['messages'])->toHaveCount(3)
        ->and($fake->requests[3]['messages'][0]['content'])->toBe('<pregunta>Tercera pregunta</pregunta>')
        ->and($fake->requests[3]['cache_turn'])->toBeTrue()
        ->and($fake->requests[3]['effort'])->toBe('medium')
        ->and($fake->requests[3]['max_tokens'])->toBe(6000);

    // One tool round for the small model here: after it, the model is asked once more with its
    // tools switched off, so it answers with what it has (no call ends in a tool nobody runs).
    $fake->push(FakeLlm::tool(Tools::SEARCH, ['consulta' => 'nota simple']), FakeLlm::text('Se pide en el Registro de la Propiedad [1].'));
    $answer = cascadeAsk($student, 'Otra pregunta')->assertCreated();
    $trace = AssistantTrace::query()->latest('id')->first();
    expect($fake->requests[4])->not->toHaveKey('tools_off')
        ->and($fake->requests[5]['tools_off'])->toBeTrue()
        // The tools stay defined (the conversation holds their calls): only the choice changes.
        ->and(array_column($fake->requests[5]['tools'], 'name'))->toContain(Tools::SEARCH)
        ->and($fake->requests)->toHaveCount(6)
        ->and($answer->json('data.answer.content'))->toContain('Registro de la Propiedad')
        ->and($trace->flags)->toContain('tool_rounds')->toContain('rounds:2')
        ->and($trace->status)->toBe('ok')
        ->and($trace->credits)->toBe(1)
        ->and(AssistantConversation::query()->count())->toBe(2);
});

it('keeps the templates\' cells out of every question, and gives them when a calculation needs them', function () {
    [$course, $one, $two] = cascadeCourse();
    $system = Prompt::make()->system();

    // The instructions name the calculators, one line each; not their cells.
    expect($system)->toContain('- «1»: Plantilla 1 · Compra del solar (Módulo 1)')->toContain('«numeros_gordos_solar»')
        ->not->toContain('- B5:')->not->toContain('(numero')
        ->and(mb_strlen($system))->toBeLessThan(7000);

    $fake = cascadeOpen()->push(
        FakeLlm::tool(Tools::CELLS, ['plantilla' => '1']),
        FakeLlm::text('La Plantilla 1 pide el precio del solar en B5.'),
        FakeLlm::tool(Tools::CELLS, ['plantilla' => '1']),
        FakeLlm::text('No puedo.'),
        FakeLlm::tool(Tools::TEMPLATE, ['plantilla' => '1', 'datos' => [['celda' => 'Z99', 'valor' => '1']]]),
        FakeLlm::text('Me faltaba la casilla.'),
    );

    cascadeAsk(cascadeStudent($course, $one), '¿Qué datos necesita la plantilla 1?')->assertCreated();
    expect(FakeLlm::lastUserText($fake->requests[1]))->toContain('Casillas de entrada')->toContain('- B5: Precio del solar')
        ->and(array_column($fake->requests[0]['tools'], 'name'))->toContain(Tools::CELLS);

    // Not the student's module: no cells either.
    cascadeAsk(cascadeStudent($course, $two), '¿Qué datos necesita la plantilla 1?')->assertCreated();
    expect($fake->requests[3]['messages'][2]['content'][0]['isError'])->toBeTrue()
        ->and(FakeLlm::lastUserText($fake->requests[3]))->toContain('no tiene el Módulo 1')->not->toContain('B5');

    // A wrong cell comes back with the right ones, so the next call is the last.
    cascadeAsk(cascadeStudent($course, $one), 'Calcula con la plantilla 1')->assertCreated();
    expect(FakeLlm::lastUserText($fake->requests[5]))->toContain('La casilla Z99 no es una entrada')->toContain('- B5: Precio del solar');
});

it('shows the owner what each route costs and where its tokens went', function () {
    [$course, $one] = cascadeCourse();
    $fake = cascadeOpen()->push(FakeLlm::text('Respuesta.'), FakeLlm::tool(Tools::ESCALATE, ['motivo' => 'x']), FakeLlm::text('Análisis.', 'claude-sonnet-5-5'));
    $student = cascadeStudent($course);
    cascadeAsk($student, '¿Dónde pido la nota simple?')->assertCreated();
    cascadeAsk($student, '¿Y esto otro?')->assertCreated();

    $overview = $this->actingAs(User::factory()->admin()->create())->getJson('/api/admin/assistant')->assertOk();

    expect($overview->json('data.cascade'))->toBe(['small', 'medium', 'large'])
        ->and($overview->json('data.models.medium'))->toMatchArray(['id' => 'claude-sonnet-5-5', 'effort' => 'medium', 'credits' => 3])
        ->and($overview->json('data.last_30_days.escalated'))->toBe(1);
    $small = collect($overview->json('data.last_30_days.by_route'))->firstWhere('route', 'small');
    expect($small['count'])->toBe(1)
        ->and($small['tokens'])->toBe(['input' => 4000, 'cached' => 3000, 'output' => 200])
        ->and($small['avg_cost_usd'])->toBeGreaterThan(0);

    // What a student sees: what an answer costs by the model that gives it.
    expect($this->actingAs($student)->getJson('/api/assistant')->assertOk()->json('data.credits'))
        ->toMatchArray(['small' => 1, 'medium' => 3, 'large' => 5]);
});

it('checks the search on a set of questions, with no model', function () {
    cascadeCourse();
    $file = tempnam(sys_get_temp_dir(), 'retrieval').'.json';
    file_put_contents($file, json_encode(['cases' => [
        ['id' => 'nota-simple', 'q' => '¿Dónde pido la nota simple?', 'as' => 'modules:1', 'expect' => ['lesson' => '1.3', 'text' => 'Registro de la Propiedad']],
        ['id' => 'glosario', 'q' => '¿Qué es el ITP?', 'expect' => ['kind' => 'glossary', 'text' => 'ITP']],
        ['id' => 'plantilla', 'q' => '¿Qué casillas tiene la plantilla 1?', 'as' => 'modules:1', 'expect' => ['kind' => 'template', 'title' => 'Plantilla 1']],
        ['id' => 'solo-sus-modulos', 'q' => '¿Dónde pido la nota simple?', 'as' => 'modules:2', 'expect' => ['none_of_modules' => [1]]],
    ]]));
    config(['assistant.retrieval.file' => $file]);
    app()->instance(Llm::class, $fake = new FakeLlm);

    $this->artisan('assistant:retrieval')->expectsOutputToContain('3 of 3 pieces found')->expectsOutputToContain('Passed.')->assertSuccessful();
    expect($fake->requests)->toBeEmpty();

    // A piece the search loses fails the check (and the deploy that runs it).
    file_put_contents($file, json_encode(['cases' => [['id' => 'falta', 'q' => '¿Dónde pido la nota simple?', 'as' => 'modules:1', 'expect' => ['lesson' => '9.9']]]]));
    $this->artisan('assistant:retrieval')->expectsOutputToContain('Not passed')->assertFailed();
    unlink($file);
});

it('runs the free search check before the paid evaluation, and counts a model that was not reached as not evaluated', function () {
    cascadeCourse();
    config(['assistant.enabled' => true, 'assistant.anthropic.api_key' => 'sk-ant-api03-TESTKEYTESTKEYTESTKEY0000']);
    app()->instance(Llm::class, $fake = new FakeLlm);
    $evals = tempnam(sys_get_temp_dir(), 'eval').'.json';
    $search = tempnam(sys_get_temp_dir(), 'retrieval').'.json';
    file_put_contents($evals, json_encode(['cases' => [
        ['id' => 'registro', 'as' => 'full', 'locale' => 'es', 'question' => '¿Qué es la nota simple?', 'expect' => ['status' => 'ok', 'route' => ['small'], 'any' => ['registro']]],
        ['id' => 'analisis', 'as' => 'full', 'locale' => 'es', 'question' => 'Analiza la viabilidad de esta promoción', 'expect' => ['status' => 'ok', 'route' => ['medium']]],
    ]]));
    config(['assistant.eval.file' => $evals, 'assistant.retrieval.file' => $search]);

    // The search loses a piece: the paid set is not run, nothing is spent, nothing opens.
    file_put_contents($search, json_encode(['cases' => [['id' => 'falta', 'q' => '¿Dónde pido la nota simple?', 'expect' => ['lesson' => '9.9']]]]));
    $this->artisan('assistant:eval')->expectsOutputToContain('nothing was spent')->assertFailed();
    expect($fake->requests)->toBeEmpty()
        ->and(AssistantEvalRun::query()->count())->toBe(0);

    // The search finds it. The medium model is busy and the small one answers for it: a good
    // answer, but the medium model was not evaluated, so this version does not open.
    file_put_contents($search, json_encode(['cases' => [['id' => 'nota-simple', 'q' => '¿Dónde pido la nota simple?', 'expect' => ['lesson' => '1.3']]]]));
    $fake->push(FakeLlm::text('Se pide en el Registro de la Propiedad [1].'), new LlmError('The model is rate limited.', true, 'rate_limit'), FakeLlm::text('El margen sale del 18 % [1].'));
    $this->artisan('assistant:eval')->expectsOutputToContain('1 without their model')->assertFailed();
    $run = AssistantEvalRun::query()->latest('id')->first();
    expect($run->passed)->toBeFalse()
        ->and(collect($run->report)->firstWhere('id', 'analisis'))->toMatchArray(['unreached' => true, 'passed' => false, 'route' => 'small'])
        ->and(app(Availability::class)->launched())->toBeFalse();

    // Every model answers its own questions: it opens.
    $fake->push(FakeLlm::text('Se pide en el Registro de la Propiedad [1].'), FakeLlm::text('El margen sale del 18 % [1].', 'claude-sonnet-5-5'));
    $this->artisan('assistant:eval')->expectsOutputToContain('Passed: this version may reach students.')->assertSuccessful();
    expect(app(Availability::class)->launched())->toBeTrue();
    unlink($evals);
    unlink($search);
});

it('keeps what the API said about a refused request for the log, and never the key', function () {
    // The SDK's own exception, as a 400 from the API builds it.
    $request = new Request('POST', 'https://api.anthropic.com/v1/messages', ['x-api-key' => 'sk-ant-api03-SECRETSECRETSECRET0000']);
    $response = new Response(400, ['request-id' => 'req_011CascadeTest'], json_encode(['type' => 'error', 'error' => [
        'type' => 'invalid_request_error', 'message' => 'tool_choice: unexpected value (sk-ant-api03-SECRETSECRETSECRET0000)',
    ]]));
    $detail = AnthropicLlm::describe(new BadRequestException($request, $response));
    expect($detail)->toContain('invalid_request_error (400)')->toContain('tool_choice: unexpected value')->toContain('[request req_011CascadeTest]')
        ->not->toContain('SECRETSECRET');

    // A call that fails is written to the log with that, and the student is told to try again, at no credits.
    [$course] = cascadeCourse();
    Log::spy();
    cascadeOpen()->push(new LlmError('The request to the model was refused: '.$detail, false, 'bad_request'));
    $answer = cascadeAsk(cascadeStudent($course), '¿Qué es la nota simple?')->assertCreated();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context['tier'] === 'small'
        && $context['kind'] === 'bad_request' && str_contains($context['detail'], 'tool_choice: unexpected value') && ! str_contains(json_encode($context), 'SECRETSECRET'));
    $trace = AssistantTrace::query()->sole();
    expect($answer->json('data.credits.spent'))->toBe(0)
        ->and($trace->status)->toBe('error')
        ->and($trace->error)->toBe('bad_request');
});
