<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(
            <<<'SQL'
            create or replace view user_customer_hours_records
            as
            select
                entries.id as entry_id,
                entries.customer_id,
                customers.name as customer_name,
                entries.user_id,
                entries.user_full_name,
                users.team_id as user_team_id,
                entries.started_at,
                entries.ended_at,
                timestampdiff(minute, internal_entries.started_at, internal_entries.ended_at) as internal_minutes,
                timestampdiff(minute, budget_entries.started_at, budget_entries.ended_at) as budget_minutes,
                timestampdiff(minute, paid_per_hour_entries.started_at, paid_per_hour_entries.ended_at) as paid_per_hour_minutes
            from entries
            join customers on entries.customer_id = customers.id
            join users on entries.user_id = users.id
            left join entries as internal_entries
                on internal_entries.id = entries.id
                and internal_entries.is_internal = 1
            left join entries as budget_entries
                on budget_entries.id = entries.id
                and budget_entries.is_internal = 0
                and budget_entries.budget_id is not null
            left join entries as paid_per_hour_entries
                on paid_per_hour_entries.id = entries.id
                and paid_per_hour_entries.is_internal = 0
                and paid_per_hour_entries.budget_id is null
            SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            <<<'SQL'
            drop view user_customer_hours_records
        SQL
        );
    }
};
