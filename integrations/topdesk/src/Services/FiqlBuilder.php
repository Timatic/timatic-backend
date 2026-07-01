<?php

namespace Timatic\Topdesk\Services;

use App\Models\Customer;

final class FiqlBuilder
{
    private const RECENT_WEEKS = 3;

    public static function build(
        TopdeskBranchResolver $branchResolver,
        ?Customer $customer,
        ?string $search,
        string $branchField,
        string $keyPattern,
    ): ?string {
        $parts = [];

        if ($customer !== null) {
            $branchId = $branchResolver->resolveBranchId($customer);

            if ($branchId === null) {
                return null;
            }

            $parts[] = $branchField.'=='.$branchId;
        } elseif ($search === null || $search === '') {
            return null;
        }

        if ($search !== null && $search !== '') {
            $parts[] = preg_match('/^'.$keyPattern.'$/i', $search)
                ? 'number=='.$search
                : 'briefDescription=contains='.$search;
        } else {
            $since = now()->subWeeks(self::RECENT_WEEKS)->toIso8601String();
            $parts[] = "modificationDate=gt='{$since}'";
        }

        return implode(';', $parts);
    }
}
