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
    protected $fillable = ['name', 'type', 'config', 'share_token'];

    protected $casts = [
        'config' => 'encrypted:array',
    ];

    public function generateShareToken(): static
    {
        $this->update(['share_token' => bin2hex(random_bytes(32))]);

        return $this->refresh();
    }

    public function hasShareToken(): bool
    {
        return $this->share_token !== null;
    }

    public function clearShareToken(): void
    {
        $this->update(['share_token' => null]);
    }
}
