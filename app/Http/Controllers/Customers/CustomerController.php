<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources;
use App\Models\Customer;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\Includes\IncludedRelationship;
use Spatie\QueryBuilder\QueryBuilder;

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

    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = QueryBuilder::for(Customer::class)
            ->allowedFilters([
                AllowedFilter::exact('externalId', 'external_id'),
            ])
            ->allowedIncludes([
                AllowedInclude::custom('accountManager', new IncludedRelationship),
            ])
            // Private mappings are an engineer's own setup, so only shared ones count as tracked
            ->withCount(['trackedDomains' => fn (Builder $query) => $query->whereNull('user_id')])
            ->jsonPaginate();

        return Resources\Customer::collection($customers);
    }

    public function store(CustomerRequest $request): Resources\Customer
    {
        $validated = $request->validated()['data']['attributes'];

        $customer = Customer::query()->create([
            'external_id' => $validated['externalId'] ?? null,
            'name' => $validated['name'],
            'is_own_organization' => $validated['isOwnOrganization'] ?? false,
            'hourly_rate' => $validated['hourlyRate'] ?? null,
            'color' => $validated['color'] ?? null,
            'account_manager_user_id' => $request->validated('data.relationships.accountManager.data.id'),
        ]);

        return new Resources\Customer($customer->load('accountManager'));
    }

    public function show(Customer $customer): Resources\Customer
    {
        $customer->load('accountManager');

        return new Resources\Customer($customer);
    }

    public function update(CustomerRequest $request, Customer $customer): Resources\Customer
    {
        $validated = $request->validated()['data']['attributes'];

        $attributes = array_filter([
            'external_id' => $validated['externalId'] ?? null,
            'name' => $validated['name'] ?? null,
            'is_own_organization' => $validated['isOwnOrganization'] ?? null,
        ], fn ($value) => $value !== null);

        // These can be cleared, so only a missing key leaves them untouched
        if (array_key_exists('hourlyRate', $validated)) {
            $attributes['hourly_rate'] = $validated['hourlyRate'];
        }

        if (array_key_exists('color', $validated)) {
            $attributes['color'] = $validated['color'];
        }

        if ($request->has('data.relationships.accountManager')) {
            $attributes['account_manager_user_id'] = $request->validated('data.relationships.accountManager.data.id');
        }

        $customer->update($attributes);

        return new Resources\Customer($customer->load('accountManager'));
    }

    public function destroy(Customer $customer, ResponseFactory $responseFactory): Response
    {
        $customer->delete();

        return $responseFactory->noContent();
    }
}
