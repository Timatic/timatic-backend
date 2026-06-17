<?php

namespace App\Models;

use Database\Factories\BudgetTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property bool $is_archived
 * @property array<int, string> $renewal_frequencies
 * @property bool $has_start_and_end_date
 * @property bool $has_change_ticket
 * @property bool $has_supervisor
 * @property bool $has_contract_id
 * @property bool $has_total_price
 * @property bool $ticket_is_required
 * @property string $default_title
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BudgetType extends Model
{
    /** @use HasFactory<BudgetTypeFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public const LEAVE = 'leave';

    protected $fillable = ['title', 'isArchived'];

    protected function casts(): array
    {
        return [
            'renewal_frequencies' => 'array',
            'is_archived' => 'bool',
            'has_start_and_end_date' => 'bool',
            'has_change_ticket' => 'bool',
            'has_supervisor' => 'bool',
            'has_contract_id' => 'bool',
            'has_total_price' => 'bool',
            'ticket_is_required' => 'bool',
        ];
    }
}
