<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            // The original file is preserved unchanged at this path.
            $table->string('original_path');
            $table->string('content_hash', 64);
            $table->string('mime_type')->nullable();
            $table->integer('size_bytes')->default(0);
            $table->string('file_status')->default('stored');
            // Confirmed fields (set when the user verifies the extraction).
            $table->string('merchant')->nullable();
            $table->integer('total_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->date('expense_date')->nullable();
            $table->float('confidence')->nullable();
            // Drive filing (Milestone 3): the canonical destination.
            $table->string('drive_folder')->nullable();
            $table->string('drive_filename')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->text('drive_link')->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
