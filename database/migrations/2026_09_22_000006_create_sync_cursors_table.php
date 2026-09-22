<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connector_account_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type');
            // Opaque checkpoint token (nextSyncToken, deltaLink, page cursor…).
            $table->text('cursor')->nullable();
            $table->timestamps();

            $table->unique(['connector_account_id', 'resource_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_cursors');
    }
};
