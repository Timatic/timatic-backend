<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

class UnusedSuggestion
{
    public readonly ?string $customer_id;

    public readonly ?string $customer_name;

    public readonly int $user_id;

    public readonly ?string $ticket_number;

    public readonly string $date;

    public readonly int $duration_in_minutes;

    public function __construct(\stdClass $row)
    {
        $this->customer_id = $row->customer_id;
        $this->customer_name = $row->customer_name;
        $this->user_id = $row->user_id;
        $this->ticket_number = $row->ticket_number;
        $this->date = $row->date;
        $this->duration_in_minutes = (int) $row->duration_in_minutes;
    }
}
