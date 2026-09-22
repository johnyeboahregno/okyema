<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            // Provider-side identifier for the account (email or tenant id).
            $table->string('external_account_id')->nullable();
            $table->json('scopes')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('disconnected');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'external_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_accounts');
    }
};
