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
    protected $fillable = ['name', 'type', 'config', 'share_token', 'share_token_expires_at'];

    protected $casts = [
        'config' => 'encrypted:array',
        'share_token_expires_at' => 'datetime',
    ];

    public function generateShareToken(): static
    {
        $this->update([
            'share_token' => bin2hex(random_bytes(32)),
            'share_token_expires_at' => now()->addWeek(),
        ]);

        return $this->refresh();
    }

    public function isShareTokenValid(): bool
    {
        return $this->share_token !== null
            && $this->share_token_expires_at?->isFuture();
    }

    public function clearShareToken(): void
    {
        $this->update(['share_token' => null, 'share_token_expires_at' => null]);
    }
}
