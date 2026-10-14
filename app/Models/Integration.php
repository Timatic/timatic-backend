<?php

namespace App\Models;

use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $title
 * @property string $type
 * @property array<string, mixed> $config
 * @property-read string $display_title
 */
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use HasFactory;

    protected $fillable = ['title', 'type', 'config', 'share_token'];

    protected $casts = [
        'config' => 'encrypted:array',
    ];

    /**
     * @return Attribute<string, never>
     */
    protected function displayTitle(): Attribute
    {
        return Attribute::get(fn (): string => $this->title ?? Str::headline($this->type));
    }

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
