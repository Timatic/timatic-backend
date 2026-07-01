<?php

namespace Timatic\Topdesk\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Timatic\Topdesk\Connector;
use Timatic\Topdesk\Requests\GetBranchesRequest;

final class TopdeskBranchResolver
{
    public function __construct(
        private readonly Connector $connector,
        private readonly string $baseUrl,
        private readonly string $branchMatchField,
    ) {}

    public function resolveBranchId(Customer $customer): ?string
    {
        if ($customer->external_id === null) {
            return null;
        }

        $cacheKey = 'topdesk.branch_id.'.md5($this->baseUrl.$customer->external_id.$this->branchMatchField);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($customer): ?string {
            $branch = $this->connector->send(
                new GetBranchesRequest("archived==false;{$this->branchMatchField}=={$customer->external_id}")
            )->dto();

            return $branch?->id;
        });
    }

    public function customerForBranchId(?string $branchId): ?Customer
    {
        if ($branchId === null) {
            return null;
        }

        $branch = $this->connector->send(new GetBranchesRequest("id=={$branchId}"))->dto();

        if (! $branch) {
            return null;
        }

        return Customer::where('external_id', $branch->clientReferenceNumber)->first();
    }
}
