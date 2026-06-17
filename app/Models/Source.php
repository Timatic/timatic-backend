<?php

namespace App\Models;

use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $title
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    public const ID_TOPDESK = 'topdesk';

    public const ID_OUTLOOK_CALENDAR = 'outlook_calendar';

    public const ID_WORD = 'word';

    public const ID_EXCEL = 'excel';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
