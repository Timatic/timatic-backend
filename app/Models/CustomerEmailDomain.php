<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A domain the email addresses of a customer end in, stored lowercase without a leading "@".
 *
 * @property int $customer_id
 * @property string $domain
 * @property ?Customer $customer
 */
class CustomerEmailDomain extends Model
{
    protected $fillable = [
        'customer_id',
        'domain',
    ];

    public static function normalise(string $domain): string
    {
        return ltrim(Str::lower(trim($domain)), '@');
    }

    public static function isValid(string $domain): bool
    {
        return str_contains($domain, '.') && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
