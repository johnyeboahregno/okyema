<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_context_id')->constrained()->cascadeOnDelete();
            $table->string('merchant');
            $table->string('category')->nullable();
            $table->string('project')->nullable();
            $table->date('expense_date');
            // Money is an integer number of minor units plus an ISO code.
            $table->integer('total_minor');
            $table->string('currency', 3)->default('GBP');
            $table->integer('tax_minor')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['user_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
