<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Nicolás, the course's AI assistant (config/assistant.php): the student's
 * conversations, a trace of every answer, the credits ledger, the course
 * index it answers from, the frequent-question cache, and the evaluation runs
 * that gate each change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->string('role', 16); // user | assistant
            $table->text('content');
            // The pieces of the course the answer cites: [{title, path}].
            $table->json('sources')->nullable();
            $table->string('route', 16)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // One row per question: who asked (never what key was used), the route
        // taken, the model, tokens, cost, credits, the pieces of the course
        // retrieved, the tools run, what the guards flagged, how long it took.
        Schema::create('assistant_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('assistant_conversations')->nullOnDelete();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('route', 16); // cache | small | large | escalated | blocked | refused | error | unavailable
            $table->string('status', 16); // ok | blocked | refusal | error
            $table->string('model', 64)->nullable();
            $table->string('prompt_version', 16)->nullable();
            $table->string('index_version', 16)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedSmallInteger('credits')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->json('chunks')->nullable();
            $table->json('tools')->nullable();
            $table->json('flags')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });

        // Append-only, like the invoices: never updated, never deleted.
        Schema::create('assistant_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');
            $table->string('reason', 24); // monthly | spend | refund | grant
            $table->char('period', 7); // YYYY-MM, Europe/Madrid
            $table->unsignedBigInteger('trace_id')->nullable();
            $table->string('note', 160)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'period']);
        });

        // The course cut in pieces, per language, each with who may read it.
        Schema::create('assistant_chunks', function (Blueprint $table) {
            $table->id();
            $table->char('locale', 2);
            $table->string('source_type', 16); // lesson | module | checklist | document
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('module_id')->nullable();
            $table->unsignedBigInteger('lesson_id')->nullable();
            // free (a free lesson) | module (its module's owners) | enrolled | full | financials
            $table->string('access', 16);
            $table->string('title', 255);
            $table->string('path', 255)->nullable();
            $table->text('text');
            $table->unsignedInteger('length');
            $table->index(['locale', 'source_type']);
        });

        // The inverted index: which pieces hold a word, and how often.
        Schema::create('assistant_postings', function (Blueprint $table) {
            $table->char('locale', 2);
            $table->string('term', 64);
            $table->foreignId('chunk_id')->constrained('assistant_chunks')->cascadeOnDelete();
            $table->unsignedSmallInteger('tf');
            $table->index(['locale', 'term']);
        });

        Schema::create('assistant_answer_cache', function (Blueprint $table) {
            $table->id();
            $table->char('key', 64)->unique();
            $table->char('locale', 2);
            $table->text('question');
            $table->text('answer');
            $table->json('sources')->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Each run of the evaluation set: a change reaches students only once
        // the run for its prompt, models and index has passed.
        Schema::create('assistant_eval_runs', function (Blueprint $table) {
            $table->id();
            $table->char('version_key', 64);
            $table->string('prompt_version', 16);
            $table->string('models', 160);
            $table->string('index_version', 16);
            $table->boolean('passed');
            $table->decimal('score', 5, 4);
            $table->unsignedSmallInteger('total');
            $table->unsignedSmallInteger('passed_count');
            $table->json('report');
            $table->timestamp('ran_at')->useCurrent();
            $table->index(['version_key', 'passed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_eval_runs');
        Schema::dropIfExists('assistant_answer_cache');
        Schema::dropIfExists('assistant_postings');
        Schema::dropIfExists('assistant_chunks');
        Schema::dropIfExists('assistant_credit_ledger');
        Schema::dropIfExists('assistant_traces');
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
