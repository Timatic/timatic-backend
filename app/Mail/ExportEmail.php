<?php

namespace App\Mail;

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

    public function __construct(string $fileName, int $year, ?int $month, string $exportType)
    {
        $this->fileName = $fileName;
        $this->exportType = $exportType;
        $this->downloadUrl = route('download.export', ['fileName' => $this->fileName]);

        if (is_null($month)) {
            $this->formattedDate = (string) $year;
        } else {
            $this->formattedDate = Carbon::createFromDate($year, $month, 1)
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
