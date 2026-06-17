<?php

namespace Database\Seeders\Dummy;

use App\Models\BudgetType;
use Illuminate\Database\Seeder;

class BudgetTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BudgetType::unguard();

        /** @var BudgetType $type */
        $type = BudgetType::query()->firstOrNew(['id' => 'project']);
        $type->title = 'Project';
        $type->has_change_ticket = true;
        $type->renewal_frequencies = [];
        $type->has_supervisor = true;
        $type->has_total_price = true;
        $type->save();

        /** @var BudgetType $type */
        $type = BudgetType::query()->firstOrNew(['id' => 'support']);
        $type->title = 'Support';
        $type->has_change_ticket = false;
        $type->renewal_frequencies = ['monthly'];
        $type->has_supervisor = false;
        $type->has_contract_id = true;
        $type->has_total_price = true;
        $type->save();

        /** @var BudgetType $type */
        $type = BudgetType::query()->firstOrNew(['id' => 'retainer']);
        $type->title = 'Retainer';
        $type->has_change_ticket = false;
        $type->renewal_frequencies = [];
        $type->has_supervisor = false;
        $type->has_contract_id = true;
        $type->has_total_price = true;
        $type->save();

        $type = BudgetType::query()->firstOrNew(['id' => BudgetType::LEAVE]);
        $type->title = 'Leave';
        $type->renewal_frequencies = ['monthly'];
        $type->ticket_is_required = false;
        $type->has_change_ticket = false;
        $type->has_contract_id = false;
        $type->has_total_price = false;
        $type->has_supervisor = false;
        $type->save();

        BudgetType::reguard();
    }
}
