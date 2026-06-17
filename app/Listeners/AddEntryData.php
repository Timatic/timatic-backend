<?php

namespace App\Listeners;

use App\Events\EntrySaved;
use App\Models\Customer;
use App\Models\Entry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Webmozart\Assert\Assert;

class AddEntryData implements ShouldQueue
{
    public function __construct(
        protected Repository $config
    ) {}

    public function handle(EntrySaved $event): void
    {
        $entry = $event->getEntry();

        $this->addUserFullNameAndEmail($entry);

        $this->addCustomerName($entry);

        $this->addHourlyRate($entry);

        $entry->saveQuietly();
    }

    public function addUserFullNameAndEmail(Entry $entry): void
    {
        if (is_null($entry->user_id)) {
            return;
        }

        if (! $entry->user_full_name) {
            $entry->user_full_name = sprintf(
                '%s %s',
                $entry->user->given_name ?? '',
                $entry->user->family_name ?? ''
            );
        }

        if (! $entry->user_email) {
            $entry->user_email = $entry->user->email ?? null;
        }
    }

    public function addCustomerName(Entry $entry): void
    {
        if ($entry->customer_name || ! $entry->customer_id) {
            return;
        }

        /** @var ?Customer $customer */
        $customer = Customer::query()->find($entry->customer_id);
        if ($customer) {
            $entry->customer_name = $customer->name;
        }
    }

    protected function addHourlyRate(Entry $entry): void
    {
        if (! $entry->customer_id) {
            return;
        }

        Assert::notNull($entry->customer);

        // If there is a budget, we want to use that hourly rate and set the column to NULL
        if ($entry->budget_id) {
            $entry->hourly_rate = null;
        } else {
            $entry->hourly_rate = $entry->customer->hourly_rate ?? $this->config->get('timatic.default_hourly_rate');
        }
    }
}
