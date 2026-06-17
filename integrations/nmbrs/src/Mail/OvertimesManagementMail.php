<?php

namespace Timatic\Nmbrs\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Timatic\Nmbrs\DataTransferObjects\NmbrsEmployee;

class OvertimesManagementMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<string, array<int, int>>  $overtimes  email => [percentage => minutes]
     * @param  Collection<string, NmbrsEmployee>  $employeesByEmail
     */
    public function __construct(
        public readonly Carbon $previousMonth,
        public readonly Collection $overtimes,
        public readonly Collection $employeesByEmail,
    ) {}

    public function build(): self
    {
        return $this->view('nmbrs::emails.overtimes_management')
            ->subject('Overzicht van alle overuren van '.$this->previousMonth->month.'-'.$this->previousMonth->year)
            ->from('noreply@intermax.nl');
    }
}
