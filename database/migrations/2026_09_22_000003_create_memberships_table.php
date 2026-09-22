<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner');
            $table->boolean('is_active_context')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'workspace_context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
