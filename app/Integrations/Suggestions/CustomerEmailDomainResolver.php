<?php

declare(strict_types=1);

namespace App\Integrations\Suggestions;

use App\Models\Customer;
use App\Models\CustomerEmailDomain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CustomerEmailDomainResolver
{
    /**
     * The customer of the first address whose domain a customer lists. Domains listed on the own
     * organization are the company's own and never resolve: every mail has one of those in it.
     *
     * @param  iterable<int, string>  $emailAddresses  ordered by priority, e.g. the recipients of a mail; "Jane <jane@acme.com>" works too
     */
    public function resolve(iterable $emailAddresses): ?Customer
    {
        $domains = collect($emailAddresses)->map(fn (string $address) => CustomerEmailDomain::normalise(Str::afterLast(Str::between($address, '<', '>'), '@')));

        $emailDomains = CustomerEmailDomain::query()
            ->whereIn('domain', $domains->unique()->values())
            ->whereHas('customer', fn (Builder $query) => $query->where('is_own_organization', false))
            ->with('customer')
            ->get()
            ->keyBy('domain');

        $matchingDomain = $domains->first(fn (string $domain) => $emailDomains->has($domain));

        return $matchingDomain === null ? null : $emailDomains->get($matchingDomain)?->customer;
    }
}
