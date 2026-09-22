<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source'); // meeting, trip, …
            $table->string('title');
            $table->string('provider');
            $table->string('provider_document_id')->nullable();
            $table->text('deep_link')->nullable();
            $table->string('mime_type')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_references');
    }
};
