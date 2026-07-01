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
        private readonly int|string $integrationId,
        private readonly string $branchMatchField,
    ) {}

    public function resolveBranchId(Customer $customer): ?string
    {
        if ($customer->external_id === null) {
            return null;
        }

        return Cache::remember($this->branchIdKey($customer->external_id), now()->endOfDay(), function () use ($customer): ?string {
            $branch = $this->connector->send(
                new GetBranchesRequest("archived==false;{$this->branchMatchField}=={$customer->external_id}", $this->branchMatchField)
            )->dto();

            if ($branch === null) {
                return null;
            }

            Cache::put($this->externalIdKey($branch->id), $customer->external_id, now()->endOfDay());

            return $branch->id;
        });
    }

    public function customerForBranchId(?string $branchId): ?Customer
    {
        if ($branchId === null) {
            return null;
        }

        $externalId = Cache::remember($this->externalIdKey($branchId), now()->endOfDay(), function () use ($branchId): ?string {
            $branch = $this->connector->send(new GetBranchesRequest("id=={$branchId}", $this->branchMatchField))->dto();

            if ($branch?->matchValue === null) {
                return null;
            }

            Cache::put($this->branchIdKey($branch->matchValue), $branchId, now()->endOfDay());

            return $branch->matchValue;
        });

        if ($externalId === null) {
            return null;
        }

        return Customer::where('external_id', $externalId)->first();
    }

    private function branchIdKey(string $externalId): string
    {
        return 'topdesk.branch_id.'.md5($this->integrationId.$externalId);
    }

    private function externalIdKey(string $branchId): string
    {
        return 'topdesk.external_id.'.md5($this->integrationId.$branchId);
    }
}
