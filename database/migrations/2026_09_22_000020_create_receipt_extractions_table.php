<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->string('merchant')->nullable();
            $table->date('expense_date')->nullable();
            $table->integer('total_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->integer('tax_minor')->nullable();
            $table->string('payment_method')->nullable();
            $table->json('line_items')->nullable();
            $table->float('confidence')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_extractions');
    }
};
