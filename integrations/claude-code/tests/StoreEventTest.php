<?php

use App\Models\Customer;
use App\Models\Event as TimeEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

it('stores an event from the Claude Code plugin', function () {
    $this->loginUser(permissions: ['events.create']);

    Event::fake();

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var User $user */
    $user = User::factory()->create();

    $this->travelTo(Carbon::parse('2026-10-15 10:07:00', 'UTC'));

    $this->postJson(route('events.store'), [
        'data' => [
            'type' => 'events',
            'attributes' => [
                'sourceId' => 'claude_code',
                'eventTypeId' => 'claude_question_answered',
                'userId' => $user->id,
                'customerId' => $customer->id,
                'ticketNumber' => 'TIM-12',
                'title' => 'Claude Code: timatic-backend',
                'description' => 'Login form',
                'startedAt' => '2026-10-15T10:00:00.000Z',
                'endedAt' => '2026-10-15T10:07:00.000Z',
            ],
        ],
    ])->assertCreated();

    /** @var TimeEvent $event */
    $event = TimeEvent::query()->sole();

    expect($event->eventType?->weight)->toBe(20)
        ->and($event->started_at?->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and($event->ended_at->toDateTimeString())->toBe('2026-10-15 10:07:00');
});
