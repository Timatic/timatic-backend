<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Http\Resources\Event as EventResource;
use App\Models\Customer;
use App\Models\Event;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;

class EventController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,App\Models\Event', only: ['index']),
            new Middleware('can:view,event', only: ['show']),
            new Middleware('can:create,App\Models\Event', only: ['create', 'store']),
            new Middleware('can:update,event', only: ['edit', 'update']),
            new Middleware('can:delete,event', only: ['destroy']),
        ];
    }

    public function store(EventRequest $request): EventResource
    {
        $attributes = $request->validatedAttributes();

        $externalCustomerId = Arr::get($request->validatedAttributes(), 'customer_external_id');
        $externalUserId = Arr::get($request->validatedAttributes(), 'user_external_id');

        if ($externalCustomerId) {
            try {
                /** @var Customer $customer */
                $customer = Customer::query()->where('external_id', $externalCustomerId)->sole();
                $attributes['customer_id'] = $customer->id;
            } catch (ModelNotFoundException $exception) {
            }
        }

        if ($externalUserId) {
            /** @var User $user */
            $user = User::query()->where('external_id', $externalUserId)->sole();
            $attributes['user_id'] = $user->id;
        }

        $event = Event::query()->create($attributes);

        return new EventResource($event);
    }

    public function show(Event $event): EventResource
    {
        return new EventResource($event);
    }

    public function update(EventRequest $request, Event $event): EventResource
    {
        $event->update($request->validatedAttributes());

        return new EventResource($event);
    }

    /**
     * @throws Exception
     */
    public function destroy(Event $event): Response
    {
        $event->delete();

        return response()->noContent();
    }
}
