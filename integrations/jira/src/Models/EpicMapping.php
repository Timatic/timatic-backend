<?php

namespace Timatic\Jira\Models;

use App\Models\Budget;
use App\Models\Customer;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The customer and budget of one epic, for a project that holds the work of several customers.
 *
 * @property int $integration_id
 * @property string $epic_key
 * @property string $epic_name
 * @property ?int $customer_id
 * @property ?int $budget_id
 */
class EpicMapping extends Model
{
    protected $table = 'jira_epic_mappings';

    protected $fillable = [
        'integration_id',
        'epic_key',
        'epic_name',
        'customer_id',
        'budget_id',
    ];

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
