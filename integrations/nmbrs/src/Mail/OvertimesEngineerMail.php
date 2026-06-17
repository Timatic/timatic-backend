<?php

namespace Timatic\Nmbrs\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OvertimesEngineerMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $percentages
     */
    public function __construct(
        public readonly Carbon $previousMonth,
        public readonly string $name,
        public readonly array $percentages,
    ) {}

    public function build(): self
    {
        return $this->view('nmbrs::emails.overtimes_engineer')
            ->subject('Jouw persoonlijke overuren van '.$this->previousMonth->month.'-'.$this->previousMonth->year)
            ->from('noreply@intermax.nl');
    }
}
