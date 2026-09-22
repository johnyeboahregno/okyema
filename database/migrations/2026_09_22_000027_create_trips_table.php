<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('planned');
            $table->timestamps();

            $table->index(['user_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
