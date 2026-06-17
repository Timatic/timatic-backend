<?php

use App\Events\EntrySaved;
use App\Events\MinutesSpentSetOnEntry;
use App\Mail\BudgetUsageWarning;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\User;
use App\Services\BudgetVersionService;
use App\Services\MinutesSpentCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

uses(WithFaker::class);

beforeEach(function () {
    Event::fake([EntrySaved::class]);
});

test('mail for budget usage is not sent below 70 procent used', function () {
    $budget = createBudget(initialMinutes: 100, properties: ['renewal_frequency' => null]);
    triggerWarning(budget: $budget, minutesUsed: 60);

    Mail::assertNothingSent();
});

test('mail for budget usage of one time budget is sent on 70 procent used', function () {
    $budget = createBudget(initialMinutes: 100, properties: ['renewal_frequency' => null]);
    triggerWarning(budget: $budget, minutesUsed: 70);

    Mail::assertSent(BudgetUsageWarning::class);
});

test('mail for budget usage of recurring budget is not sent on 70 procent used', function () {
    $budget = createBudget(initialMinutes: 100, properties: ['renewal_frequency' => 'monthly']);
    triggerWarning(budget: $budget, minutesUsed: 70);

    Mail::assertNothingSent();
});

test('mail for budget usage of one time budget is sent on 90 procent used', function () {
    $budget = createBudget(initialMinutes: 100, properties: ['renewal_frequency' => null]);
    triggerWarning(budget: $budget, minutesUsed: 90);

    Mail::assertSent(BudgetUsageWarning::class);
});

test('mail for budget usage of recurring budget is not sent on 90 procent used', function () {
    $budget = createBudget(initialMinutes: 100, properties: ['renewal_frequency' => 'monthly']);
    triggerWarning(budget: $budget, minutesUsed: 90);

    Mail::assertNothingSent();
});

test('mail for budget usage of one time budget is sent on 100 procent used', function () {
    $budget = createBudget(initialMinutes: 100, properties: ['renewal_frequency' => null]);
    triggerWarning(budget: $budget, minutesUsed: 100);

    Mail::assertSent(BudgetUsageWarning::class);
});

test('mail for budget usage of recurring budget is sent on 100 procent used', function () {
    $budget = createBudget(initialMinutes: 100, properties: ['renewal_frequency' => 'monthly']);
    triggerWarning(budget: $budget, minutesUsed: 100);

    Mail::assertSent(BudgetUsageWarning::class);
});

test('mail for budget usage of one time budget is sent on 110 procent used', function () {
    $budget = createBudget(initialMinutes: 100, properties: ['renewal_frequency' => null]);
    triggerWarning(budget: $budget, minutesUsed: 110);

    Mail::assertSent(BudgetUsageWarning::class);
});

test('mail is sent to account manager and supervisor mail addresses', function () {
    $budget = createBudget(initialMinutes: 100);
    triggerWarning(budget: $budget, minutesUsed: 110);

    Mail::assertSent(BudgetUsageWarning::class, function (BudgetUsageWarning $mail) use ($budget) {
        return $mail->hasTo([$budget->customer?->accountManager?->email, $budget->supervisor?->email]);
    });
});

test('mail is sent to default mail address for unknown account manager', function () {
    config()->set('timatic.account_management_mail_address', 'dummy@test.com');
    /** @var Customer $customer */
    $customer = Customer::factory()->create(['account_manager_user_id' => null]);
    $budget = createBudget(initialMinutes: 100, properties: [
        'customer_id' => $customer->id,
        'supervisor_user_id' => null,
    ]);
    triggerWarning(budget: $budget, minutesUsed: 110);

    Mail::assertSent(BudgetUsageWarning::class, function ($mail) {
        return $mail->hasTo('dummy@test.com');
    });
});

test('do not send mail when threshold stay the same', function () {
    $budget = createBudget(initialMinutes: 100);
    Event::fakeFor(function () use ($budget) {
        triggerWarning(budget: $budget, minutesUsed: 91);
    });

    triggerWarning(budget: $budget, minutesUsed: 1);

    Mail::assertNotSent(BudgetUsageWarning::class);
});

test('send email when threshold met after editing budget', function () {
    $budget = createBudget(initialMinutes: 999999, properties: [
        'renewal_frequency' => null,
    ]);

    $budgetVersionService = app(BudgetVersionService::class);

    /** @var BudgetVersionService $budgetVersionService */
    $budgetVersionService->createAndReplaceVersion($budget, ['initial_minutes' => 100]);

    triggerWarning(budget: $budget, minutesUsed: 70);

    Mail::assertSent(BudgetUsageWarning::class);
});

test('mail for budget usage has action url', function () {
    $budget = createBudget(initialMinutes: 100, properties: ['renewal_frequency' => null]);

    $mailable = new BudgetUsageWarning($budget, $budget->customer, 100);

    $mailable->assertSeeInHtml(config('app.frontend_url').'/budgets/'.$budget->id.'/');
});

function createBudget(int $initialMinutes, array $properties = []): Budget
{
    $accountManager = User::factory()->create();

    return Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state(['initial_minutes' => $initialMinutes]))
        ->has(
            Customer::factory()->state([
                'account_manager_user_id' => $accountManager->id,
            ])
        )
        ->create($properties);
}

function triggerWarning(Budget $budget, int $minutesUsed)
{
    Mail::fake();

    /** @var Entry $entry */
    $entry = Entry::factory()->create([
        'started_at' => Carbon::now(),
        'ended_at' => Carbon::now()->addMinutes($minutesUsed),
        'budget_id' => $budget->id,
    ]);

    $entry->overtimes()->delete();

    /** @var MinutesSpentCalculator $calculator */
    $calculator = app()->make(MinutesSpentCalculator::class);
    $entry->minutes_spent = $calculator->calculate($entry);
    $entry->saveQuietly();

    MinutesSpentSetOnEntry::dispatch($entry);
}
