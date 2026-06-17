<?php

namespace App\Http\Resources;

use App\Models\Customer as CustomerModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\DataTransferObjects\Ticket
 */
class Ticket extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'key' => $this->number,
            'title' => $this->title,
            'createdAt' => $this->created_at->toISOString(),
            'closedAt' => $this->closed_at?->toISOString(),
            'url' => $this->url,
        ];
    }

    public function toType(Request $request): string
    {
        return 'tickets';
    }

    public function toId(Request $request): string
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveResourceObject(JsonApiRequest $request): array
    {
        $object = parent::resolveResourceObject($request);
        $included = $request->sparseIncluded() ?? [];

        if (in_array('actions', $included) && $this->resource->actions->isNotEmpty()) {
            $object['relationships']['actions'] = [
                'data' => $this->resource->actions->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => 'actions',
                ])->values()->all(),
            ];
        }

        if (in_array('customers', $included) && $this->resource->customer_id !== null) {
            $customer = CustomerModel::query()->find($this->resource->customer_id);
            if ($customer instanceof CustomerModel) {
                $object['relationships']['customers'] = [
                    'data' => ['id' => (string) $customer->id, 'type' => 'customers'],
                ];
            }
        }

        return $object;
    }

    /**
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        $with = parent::with($request);

        if (! ($request instanceof JsonApiRequest)) {
            return $with;
        }

        $included = $request->sparseIncluded() ?? [];
        $items = [];

        if (in_array('actions', $included)) {
            foreach ($this->resource->actions as $action) {
                $items[] = [
                    'id' => $action->id,
                    'type' => 'actions',
                    'attributes' => TicketAction::make($action)->toAttributes($request),
                ];
            }
        }

        if (in_array('customers', $included) && $this->resource->customer_id !== null) {
            $customer = CustomerModel::query()->find($this->resource->customer_id);
            if ($customer instanceof CustomerModel) {
                $customerResource = Customer::make($customer);
                $items[] = [
                    'id' => $customerResource->resolveResourceIdentifier($request),
                    'type' => $customerResource->resolveResourceType($request),
                    'attributes' => $customerResource->toAttributes($request),
                ];
            }
        }

        if (! empty($items)) {
            $with['included'] = array_merge($with['included'] ?? [], $items);
        }

        return $with;
    }
}
