<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Entry;

enum HoursExportDimension: string
{
    case User = 'user';
    case Budget = 'budget';
    case Customer = 'customer';
    case TicketType = 'ticketType';

    public function label(): string
    {
        return __("exports.hours.dimensions.{$this->value}");
    }

    public function keyOf(Entry $entry): string
    {
        return (string) match ($this) {
            self::User => $entry->user_id,
            self::Budget => $entry->budget_id,
            self::Customer => $entry->customer_id,
            self::TicketType => $entry->ticket_type,
        };
    }

    public function labelOf(Entry $entry): string
    {
        return match ($this) {
            self::User => (string) $entry->user_full_name,
            self::Budget => $entry->budget?->getTitle() ?? __('exports.hours.without_budget'),
            self::Customer => (string) $entry->customer_name,
            self::TicketType => $entry->ticket_type ?: __('exports.hours.without_ticket_type'),
        };
    }
}
