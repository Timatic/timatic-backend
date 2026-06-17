<?php

use App\Jobs\RemindUsersOfUnusedSuggestions;
use App\Mail\UnusedSuggestionsMail;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\EntrySuggestion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('reminds a user of 2 unused suggestions last week', function () {
    Mail::fake();

    /** @var User $user */
    $user = User::factory()->create();

    createSuggestions(userId: $user->id, date: Carbon::now('Europe/Amsterdam')->subWeek()->startOfWeek()->addDays(1), ticketNumber: 'IMX001', customerName: 'Company 1');

    createSuggestions(userId: $user->id, date: Carbon::now('Europe/Amsterdam')->subWeek()->startOfWeek()->addDays(5), ticketNumber: 'IMX002', customerName: 'Company 2');

    RemindUsersOfUnusedSuggestions::dispatch();

    Mail::assertQueued(
        UnusedSuggestionsMail::class,
        function (UnusedSuggestionsMail $mail) use ($user) {
            $mail->assertSeeInHtml('1 uur  30 min  voor Company 1')
                ->assertSeeInHtml('1 uur  30 min  voor Company 2')
                ->assertSeeInHtml('IMX001')
                ->assertSeeInHtml('IMX002');

            return $mail->hasTo($user->email);
        });
});

it('reminds a user of 2 unused suggestions without tickets', function () {
    Mail::fake();

    /** @var User $user */
    $user = User::factory()->create();

    createSuggestions(userId: $user->id, date: Carbon::now('Europe/Amsterdam')->subWeek()->startOfWeek()->addDays(1));

    createSuggestions(userId: $user->id, date: Carbon::now('Europe/Amsterdam')->subWeek()->startOfWeek()->addDays(5));

    RemindUsersOfUnusedSuggestions::dispatch();

    Mail::assertQueued(
        UnusedSuggestionsMail::class,
        function (UnusedSuggestionsMail $mail) use ($user) {
            $mail->assertSeeInHtml('nog 2 ongebruikte suggesties');

            return $mail->hasTo($user->email);
        });
});

it('does not remind a user that has no suggestions last week', function () {
    Mail::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $userId = $user->id;
    createSuggestions(userId: (int) $user->id, date: Carbon::now()->subWeeks(2));

    RemindUsersOfUnusedSuggestions::dispatch();

    Mail::assertNothingQueued();
});

it('does not remind about suggestions when there are manual entries for the same ticket', function () {
    Mail::fake();

    /** @var User $user */
    $user = User::factory()->create();
    $date = Carbon::now('Europe/Amsterdam')->subWeek()->startOfWeek()->addDays(1);

    createSuggestions(userId: $user->id, date: $date, ticketId: 'IMX12345');

    Event::fake();
    Entry::factory()
        ->create([
            'user_id' => $user->id,
            'started_at' => $date,
            'ended_at' => $date->clone()->addHour(),
            'ticket_id' => 'IMX12345',
        ]);

    RemindUsersOfUnusedSuggestions::dispatch();

    Mail::assertNothingQueued();
});

function createSuggestions(int $userId, Carbon $date, ?string $ticketNumber = null, ?string $customerName = null, ?string $ticketId = null): void
{
    Event::fake();

    EntrySuggestion::factory()
        ->has(Activity::factory()->count(1)->state([
            'user_id' => $userId,
            'started_at' => $date,
            'ended_at' => $date->clone()->addMinutes(90),
        ]))
        ->for(Customer::factory()->state([
            'name' => $customerName ?? fake()->company(),
        ]))
        ->create([
            'user_id' => $userId,
            'date' => $date,
            'ticket_number' => $ticketNumber,
            'ticket_id' => $ticketId,
        ]);
}
