<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OvertimeType extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public const PERSONAL = 'personal';

    public const CUSTOMER = 'customer';

    protected $fillable = [
        'title',
    ];
}
