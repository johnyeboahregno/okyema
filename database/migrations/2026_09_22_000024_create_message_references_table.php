<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->text('snippet')->nullable();
            $table->foreignId('sender_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('provider');
            $table->string('provider_message_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('needs_reply')->default(false);
            $table->timestamps();

            $table->unique(['provider', 'provider_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_references');
    }
};
