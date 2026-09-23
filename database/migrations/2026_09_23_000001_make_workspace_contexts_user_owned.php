<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace contexts become user-owned and user-creatable.
 *
 * They used to be three shared global rows keyed by a unique `type`. Now every
 * user owns their own contexts, so existing data is split: each membership gets
 * its own context row, that user's rows in every workspace-scoped table are
 * re-pointed onto it, and the shared rows are removed at the end.
 *
 * `type` keeps its column name but is now a free-form key that is only unique
 * within a user — and no longer an enum — so it cannot collide with the
 * defaults when a user creates their own.
 *
 * `profiles.all_contexts_active` records the merged "All contexts" view. The
 * active context stays on `memberships.is_active_context` for a single context.
 */
return new class extends Migration
{
    /** Every table that carries a workspace_context_id of its own. */
    private const SCOPED_TABLES = [
        'calendars',
        'events',
        'meetings',
        'notes',
        'summaries',
        'decisions',
        'action_items',
        'expenses',
        'receipts',
        'people',
        'conversations',
        'message_references',
        'approval_requests',
        'drafts',
        'trips',
        'document_references',
        'automation_rules',
    ];

    public function up(): void
    {
        Schema::table('workspace_contexts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });

        // `type` is now unique per user rather than globally.
        Schema::table('workspace_contexts', function (Blueprint $table) {
            $table->dropUnique(['type']);
        });

        $this->splitSharedContexts();

        Schema::table('workspace_contexts', function (Blueprint $table) {
            $table->unique(['user_id', 'type']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('all_contexts_active')->default(false)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('all_contexts_active');
        });

        Schema::table('workspace_contexts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'type']);
        });

        $this->mergeIntoSharedContexts();

        Schema::table('workspace_contexts', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->unique('type');
        });
    }

    /**
     * Give every user their own copy of each context they belong to, move that
     * user's data onto the copy, then drop the shared rows.
     */
    private function splitSharedContexts(): void
    {
        // Snapshot first: the loop re-points memberships as it goes.
        $memberships = DB::table('memberships')->get();

        foreach ($memberships as $membership) {
            $shared = DB::table('workspace_contexts')
                ->where('id', $membership->workspace_context_id)
                ->first();

            if ($shared === null) {
                continue;
            }

            $ownedId = DB::table('workspace_contexts')->insertGetId([
                'user_id' => $membership->user_id,
                'type' => $shared->type,
                'name' => $shared->name,
                'is_default' => $shared->is_default,
                'created_at' => $shared->created_at,
                'updated_at' => $shared->updated_at,
            ]);

            DB::table('memberships')
                ->where('id', $membership->id)
                ->update(['workspace_context_id' => $ownedId]);

            foreach (self::SCOPED_TABLES as $table) {
                DB::table($table)
                    ->where('workspace_context_id', $membership->workspace_context_id)
                    ->where('user_id', $membership->user_id)
                    ->update(['workspace_context_id' => $ownedId]);
            }
        }

        DB::table('workspace_contexts')->whereNull('user_id')->delete();
    }

    /**
     * Collapse user-owned contexts back into one shared row per key. Used only
     * when rolling this migration back.
     */
    private function mergeIntoSharedContexts(): void
    {
        $shared = [];

        foreach (DB::table('workspace_contexts')->orderBy('id')->get() as $context) {
            if ($context->user_id === null) {
                $shared[$context->type] = $context->id;

                continue;
            }

            $shared[$context->type] ??= DB::table('workspace_contexts')->insertGetId([
                'type' => $context->type,
                'name' => $context->name,
                'is_default' => $context->is_default,
                'created_at' => $context->created_at,
                'updated_at' => $context->updated_at,
            ]);

            DB::table('memberships')
                ->where('workspace_context_id', $context->id)
                ->update(['workspace_context_id' => $shared[$context->type]]);

            foreach (self::SCOPED_TABLES as $table) {
                DB::table($table)
                    ->where('workspace_context_id', $context->id)
                    ->update(['workspace_context_id' => $shared[$context->type]]);
            }

            DB::table('workspace_contexts')->where('id', $context->id)->delete();
        }
    }
};
