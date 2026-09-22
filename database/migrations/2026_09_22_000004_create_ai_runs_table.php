<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('run_type');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->json('input_summary')->nullable();
            $table->json('output')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->string('status')->default('SUCCESS');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'run_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
