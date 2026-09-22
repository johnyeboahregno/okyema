<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('state')->default('confirmed');
            $table->string('timezone')->nullable();
            $table->string('provider');
            $table->string('provider_event_id')->nullable();
            $table->string('provider_revision')->nullable();
            $table->text('external_url')->nullable();
            $table->string('recurrence_rule')->nullable();
            $table->string('recurrence_id')->nullable();
            $table->timestamps();

            // Idempotent sync: the same provider event never inserts twice.
            $table->unique(['provider', 'provider_event_id']);
            $table->index(['user_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
