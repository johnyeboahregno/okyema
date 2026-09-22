<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_contexts', function (Blueprint $table) {
            $table->id();
            // REGNO / LAUNCHPAD / PERSONAL — the canonical boundary key.
            $table->string('type')->unique();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_contexts');
    }
};
