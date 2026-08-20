<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Agentic CRM durable queue requires PostgreSQL.');
        }
        $path = base_path('packages/hiddenleaf/agentic-crm/database/postgres.sql');
        if (!is_file($path)) throw new RuntimeException('Agentic CRM PostgreSQL schema file is missing.');
        DB::unprepared(file_get_contents($path));
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS agent_definitions CASCADE; DROP TABLE IF EXISTS contact_facts CASCADE; DROP TABLE IF EXISTS agent_evidence CASCADE; DROP TABLE IF EXISTS agent_tasks CASCADE;');
    }
};
