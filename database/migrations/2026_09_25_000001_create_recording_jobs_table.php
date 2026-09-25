<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recording_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('provider')->default('assemblyai');
            $table->string('provider_job_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('transcript')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recording_jobs');
    }
};
