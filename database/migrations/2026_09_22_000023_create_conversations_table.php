<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->string('provider');
            $table->string('provider_conversation_id')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_conversation_id']);
            $table->index(['user_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
