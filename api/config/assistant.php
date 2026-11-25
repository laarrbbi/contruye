<?php

/*
|--------------------------------------------------------------------------
| Nicolás, the course's AI assistant («Nicolás IA»)
|--------------------------------------------------------------------------
|
| An assistant that answers from the course (RAG over the lessons, module
| texts, checklists and documents the student has bought) and runs the
| course's templates for the student. Prepared, and off: it opens to
| students only when ASSISTANT_ENABLED=true, the API key is set, and the
| evaluation set has passed for this exact prompt, models and index
| (`php artisan assistant:eval`). Until then the menu shows «Pronto», and
| admins may try it once the key is set. Everything: docs/asistente-ia.md.
|
| The Anthropic key never leaves the server: the browser talks to this API
| only, which checks the student, the credits and the budget, then calls
| Claude itself. It is never logged, never returned, never in a trace.
*/

return [

    // Students get it only when this is true AND an evaluation has passed.
    'enabled' => (bool) env('ASSISTANT_ENABLED', false),

    // The assistant's name, as the menu and the chat say it. It is an AI and
    // says so: it never presents itself as the course's instructor.
    'name' => env('ASSISTANT_NAME', 'Nicolás'),

    'anthropic' => [
        // Server side only (constitution V: secrets fail closed).
        'api_key' => env('ANTHROPIC_API_KEY'),
        // A request that takes longer is cut, and the student's credits come back.
        'timeout' => (float) env('ASSISTANT_TIMEOUT', 45),
        'max_retries' => (int) env('ASSISTANT_MAX_RETRIES', 1),
    ],

    /*
    | A cascade of three models, the cheapest that can answer first. Per
    | million tokens the small one costs a twentieth of the medium one and a
    | fortieth of the large one, so what a question costs is, above all, which
    | of them answers it:
    |
    |   guard (no model) → frequent-question cache (no model) → small →
    |   medium → large
    |
    | Every question starts at the small model unless it plainly asks for an
    | analysis (the router, below); a model that sees a question is beyond it
    | hands it to the next one (the «consultar_al_experto» tool) instead of
    | answering, and the next one starts afresh. The student pays the credits
    | of the model that answered, never those of the attempts before it.
    |
    | Each tier has its own budget, so a dear model never carries more than it
    | needs: how much of the conversation goes with the question
    | (`history_chars`), how many times it may use its tools (`tool_rounds`),
    | whether the question and its documents are cached between those rounds
    | (`cache_turn`: a round re-sends everything; cached, it is re-read at a
    | twentieth of the price, at the cost of a quarter more on the first
    | round, so it pays where a second round is the rule). `max_tokens` is a
    | ceiling, not a target (the model does not see it, and thinking counts
    | towards it): the instructions set the length of an answer, `effort` how
    | much it thinks. Prices are per million tokens, for the daily budget and
    | the traces (Claude API list prices, checked 2026-11-25).
    */
    'models' => [
        'small' => [
            'id' => env('ASSISTANT_MODEL_SMALL', 'claude-haiku-5-5'),
            'effort' => env('ASSISTANT_EFFORT_SMALL', 'low'),
            'max_tokens' => 3000,
            'credits' => 1,
            'price' => ['input' => 0.10, 'output' => 0.50, 'cache_read' => 0.01, 'cache_write' => 0.125],
            // Claude Haiku 5.5 has no server-side fallback: a declined request stays declined.
            'fallbacks' => false,
            'history_chars' => 8000,
            'tool_rounds' => 4,
            'cache_turn' => false,
        ],
        'medium' => [
            'id' => env('ASSISTANT_MODEL_MEDIUM', 'claude-sonnet-5-5'),
            // Sonnet 5.5's default is «high»; on questions answered from documents «medium» does the work.
            'effort' => env('ASSISTANT_EFFORT_MEDIUM', 'medium'),
            'max_tokens' => 6000,
            'credits' => 3,
            'price' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.10, 'cache_write' => 2.50],
            // Server-side fallback when a safety classifier declines (beta server-side-fallback-2026-07-01).
            'fallbacks' => true,
            'history_chars' => 6000,
            'tool_rounds' => 5,
            'cache_turn' => true,
        ],
        'large' => [
            'id' => env('ASSISTANT_MODEL_LARGE', 'claude-opus-5-5'),
            // Opus 5.5's default; said explicitly so a change of default does not move the bill.
            'effort' => env('ASSISTANT_EFFORT_LARGE', 'medium'),
            'max_tokens' => 8000,
            'credits' => 5,
            'price' => ['input' => 4.00, 'output' => 20.00, 'cache_read' => 0.20, 'cache_write' => 5.00],
            'fallbacks' => true,
            'history_chars' => 6000,
            'tool_rounds' => 5,
            'cache_turn' => true,
        ],
    ],

    // The order of the cascade: who a model hands a question to is the next one here.
    'cascade' => ['small', 'medium', 'large'],

    /*
    | Where a question enters the cascade. The router scores what the question
    | asks for (Router): an analysis asked for outright, several figures, a
    | long text, several questions in one. Under `medium_at` it starts at the
    | small model, which costs next to nothing to try and can hand it over;
    | from `large_at` it goes to the large one at once, without paying the
    | medium one for a hand-over.
    */
    'router' => [
        'medium_at' => 2,
        'large_at' => 5,
    ],

    // At most this many tool rounds in one answer, whatever a tier says. A model that has spent
    // its rounds is asked once more with its tools switched off: it answers with what it has
    // gathered, so no call ends in a request for a tool that nobody runs.
    'max_tool_rounds' => 5,

    /*
    | Credits, as Claude, ChatGPT or Lovable count them: a monthly allowance
    | by plan, spent per answer (the small model 1, the medium one 3, the
    | large one 5, a frequent question answered from the cache 0), shown to
    | the student.
    | The ledger is append-only, like the invoices: a grant, a spend, a
    | refund when the call fails. The month is Europe/Madrid's.
    */
    'credits' => [
        'monthly' => [
            'full' => (int) env('ASSISTANT_CREDITS_FULL', 300),
            'modules' => (int) env('ASSISTANT_CREDITS_MODULES', 100),
            'admin' => (int) env('ASSISTANT_CREDITS_ADMIN', 2000),
        ],
    ],

    // Over-use: per student, per minute and per day; the message size; how
    // much of the conversation goes with each question.
    'limits' => [
        'per_minute' => 6,
        'per_day' => 120,
        'max_question_chars' => 2000,
        'history_messages' => 8,
        'conversations' => 200,
    ],

    /*
    | A ceiling on what the whole platform spends in a day (US dollars, from
    | each answer's tokens and the prices above). Reached, the assistant
    | pauses for everyone but the admins until the next day.
    */
    'daily_budget_usd' => (float) env('ASSISTANT_DAILY_BUDGET_USD', 20),

    /*
    | Frequent questions: a first question asked again, word for word once
    | normalised, by a student who can read exactly the same part of the
    | course, in the same language, is answered from here (0 credits). Never
    | for a conversation's follow-ups or an answer that ran a template. A new
    | prompt, model or index empties it, since the key carries their version.
    */
    'answer_cache' => [
        'enabled' => true,
        'ttl_days' => 14,
    ],

    // Retrieval: how many pieces of the course go with a question, and their size.
    // Its own check (`php artisan assistant:retrieval`, no model, no cost) runs the
    // questions of the file below after every build of the index.
    'retrieval' => [
        'chunk_chars' => 1200,
        'top_k' => 6,
        // No more than this many pieces of one lesson, text or template's card among them.
        'max_per_source' => 3,
        // BM25, tuned on the retrieval set: how fast a repeated word stops adding (k1);
        // how much more a rare word says than a common one (its weight, to this power);
        // and how many times a word of the title, and of a piece's own headings and
        // bold openings, counts beside a word of its lines.
        'k1' => 1.2,
        'idf_power' => 1.5,
        'weights' => ['title' => 1.0, 'lead' => 2.0],
        'file' => resource_path('assistant/retrieval.json'),
        'min_recall' => 0.9,
    ],

    // The evaluation set every change passes before students get it.
    'eval' => [
        'file' => resource_path('assistant/evals.json'),
        'min_score' => 0.9,
    ],

    // How long a trace is kept (days); `assistant:prune` removes older ones.
    'trace_days' => 180,
];
