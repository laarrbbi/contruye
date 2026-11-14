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
    | Two models, a router between them. Most questions are easy (what a
    | nota simple is, which tax applies) and go to the small, fast and cheap
    | one; a calculation, a viability analysis or a long multi-part question
    | goes to the large one, and the small one can hand a question over to it
    | (the «consultar_al_experto» tool). Prices are per million tokens, for
    | the daily budget and the traces (Claude API list prices, 2026-11).
    */
    'models' => [
        'small' => [
            'id' => env('ASSISTANT_MODEL_SMALL', 'claude-haiku-5-5'),
            'effort' => env('ASSISTANT_EFFORT_SMALL', 'low'),
            'max_tokens' => 1500,
            'credits' => 1,
            'price' => ['input' => 0.10, 'output' => 0.50, 'cache_read' => 0.01, 'cache_write' => 0.125],
            // Claude Haiku 5.5 has no server-side fallback: a declined request stays declined.
            'fallbacks' => false,
        ],
        'large' => [
            'id' => env('ASSISTANT_MODEL_LARGE', 'claude-opus-5-5'),
            // Opus 5.5's default; said explicitly so a change of default does not move the bill.
            'effort' => env('ASSISTANT_EFFORT_LARGE', 'medium'),
            'max_tokens' => 4000,
            'credits' => 5,
            'price' => ['input' => 4.00, 'output' => 20.00, 'cache_read' => 0.20, 'cache_write' => 5.00],
            // Server-side fallback when a safety classifier declines (beta server-side-fallback-2026-07-01).
            'fallbacks' => true,
        ],
    ],

    // At most this many tool rounds in one answer (search, template, legal parameters).
    'max_tool_rounds' => 5,

    /*
    | Credits, as Claude, ChatGPT or Lovable count them: a monthly allowance
    | by plan, spent per answer (the small model 1, the large one 5, a
    | frequent question answered from the cache 0), shown to the student.
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
    'retrieval' => [
        'chunk_chars' => 1200,
        'top_k' => 6,
    ],

    // The evaluation set every change passes before students get it.
    'eval' => [
        'file' => resource_path('assistant/evals.json'),
        'min_score' => 0.9,
    ],

    // How long a trace is kept (days); `assistant:prune` removes older ones.
    'trace_days' => 180,
];
