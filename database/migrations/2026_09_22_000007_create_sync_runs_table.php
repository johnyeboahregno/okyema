<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connector_account_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type');
            $table->string('status')->default('running');
            $table->integer('items_synced')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->index(['connector_account_id', 'resource_type', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
