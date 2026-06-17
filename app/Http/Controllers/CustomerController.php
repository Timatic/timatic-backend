<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Http\Resources;
use App\Models\Customer;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class CustomerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\Customer', only: ['index']),
            new Middleware('can:view,customer', only: ['show']),
            new Middleware('can:create,App\Models\Customer', only: ['create', 'store']),
            new Middleware('can:update,customer', only: ['edit', 'update']),
            new Middleware('can:delete,customer', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonApiResourceCollection
    {
        $customers = QueryBuilder::for(Customer::class)
            ->allowedFilters([
                AllowedFilter::exact('externalId', 'external_id'),
            ])
            ->allowedIncludes([
                'accountManager',
            ])
            ->jsonPaginate();

        return Resources\Customer::collection($customers);
    }

    public function store(CustomerRequest $request): Resources\Customer
    {
        $validated = $request->validated()['data']['attributes'];

        $customer = Customer::query()->create([
            'external_id' => $validated['externalId'] ?? null,
            'name' => $validated['name'],
        ]);

        return new Resources\Customer($customer);
    }

    public function show(Customer $customer): Resources\Customer
    {
        $customer->load('accountManager');

        return new Resources\Customer($customer);
    }

    public function update(CustomerRequest $request, Customer $customer): Resources\Customer
    {
        $validated = $request->validated()['data']['attributes'];

        $customer->update(array_filter([
            'external_id' => $validated['externalId'] ?? null,
            'name' => $validated['name'] ?? null,
        ]));

        return new Resources\Customer($customer);
    }

    public function destroy(Customer $customer, ResponseFactory $responseFactory): Response
    {
        $customer->delete();

        return $responseFactory->noContent();
    }
}
