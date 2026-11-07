<?php

namespace App\Mail;

use App\DataTransferObjects\ExportParameters;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExportEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $fileName;

    public string $downloadUrl;

    public string $exportType;

    public string $formattedDate;

    public function __construct(string $fileName, ExportParameters $parameters, string $exportType)
    {
        $this->fileName = $fileName;
        $this->exportType = $exportType;
        $this->downloadUrl = route('download.export', ['fileName' => $this->fileName]);

        if ($parameters->fromMonth !== null) {
            $this->formattedDate = $parameters->rangeStart()->format('F Y').' - '.$parameters->rangeEnd()->format('F Y');
        } elseif (is_null($parameters->year)) {
            $this->formattedDate = '';
        } elseif (is_null($parameters->month)) {
            $this->formattedDate = (string) $parameters->year;
        } else {
            $this->formattedDate = Carbon::createFromDate($parameters->year, $parameters->month, 1)
                ->format('F Y');
        }
    }

    public function build(): self
    {
        return $this->subject(__('Your ').$this->exportType)
            ->markdown('mail.budgets.export')
            ->with([
                'downloadUrl' => $this->downloadUrl,
                'exportType' => $this->exportType,
                'formattedDate' => $this->formattedDate,
            ]);
    }
}
