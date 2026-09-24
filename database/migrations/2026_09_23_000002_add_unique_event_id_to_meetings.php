<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // One meeting per calendar event: the sync mirror keys on event_id,
            // so duplicates must be impossible. NULL stays non-unique for
            // manually created meetings.
            $table->unique('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropUnique(['event_id']);
        });
    }
};
