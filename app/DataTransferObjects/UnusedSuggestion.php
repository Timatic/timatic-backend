<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

readonly class UnusedSuggestion
{
    public ?string $customer_id;

    public ?string $customer_name;

    public int $user_id;

    public ?string $ticket_number;

    public string $date;

    public int $duration_in_minutes;

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
