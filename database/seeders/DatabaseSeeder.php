<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Workspace contexts are created per user on registration
        // (WorkspaceContextService::seedDefaultsFor), so there is nothing
        // global to seed in Milestone 0.
    }
}
