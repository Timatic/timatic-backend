<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property array<string, mixed> $config
 */
class Integration extends Model
{
    protected $fillable = ['name', 'type', 'config'];

    protected $casts = ['config' => 'encrypted:array'];
}
