<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('avatar_url')->nullable();
            $table->string('title')->nullable();
            $table->string('organisation')->nullable();
            $table->string('timezone')->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('onboarding_dismissed_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
