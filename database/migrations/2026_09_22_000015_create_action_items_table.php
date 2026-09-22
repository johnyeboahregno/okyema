<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('owner')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_items');
    }
};
