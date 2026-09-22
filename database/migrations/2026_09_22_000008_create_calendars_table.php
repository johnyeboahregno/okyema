<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('provider');
            $table->string('provider_calendar_id')->nullable();
            $table->string('timezone')->nullable();
            $table->string('colour', 9)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['connector_account_id', 'provider_calendar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendars');
    }
};
