<?php

namespace App\DataTransferObjects;

class EntryExportRow
{
    public string $startedAt;

    public string $settlement;

    public ?string $ticketNumber = null;

    public ?string $ticketType = null;

    public string $customerExternalId;

    public ?string $customerName = null;

    public ?string $budgetId = null;

    public ?string $budgetTitle = null;

    public ?string $budgetTypeId = null;

    public ?string $employeeName = null;

    public ?string $employeeTeam = null;

    public string $description;

    public ?string $invoicedAt = null;

    public float $hourlyRate;

    public float $hoursSpent;

    public float $result;
}
