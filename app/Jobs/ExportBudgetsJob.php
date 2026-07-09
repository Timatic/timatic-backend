<?php

namespace App\Jobs;

use App\DataTransferObjects\ExportPeriod;
use App\Integrations\ExportService;
use App\Mail\ExportEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ExportBudgetsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        protected User $user,
        protected string $exportType,
        protected int $year,
        protected ?int $month,
    ) {}

    public function handle(ExportService $exportService): void
    {
        $format = $exportService->findFormat($this->exportType)
            ?? throw new InvalidArgumentException("Invalid export type: {$this->exportType}");

        $fileName = "export_{$this->exportType}_{$this->year}_".($this->month ?? 'all').".{$format->extension}";

        $exportService
            ->createExport($this->exportType, new ExportPeriod($this->year, $this->month))
            ->export(Storage::disk('temp')->path($fileName));

        $this->uploadToS3($fileName);
        $this->sendExportEmail($fileName);
    }

    private function uploadToS3(string $fileName): void
    {
        $content = Storage::disk('temp')->get($fileName);

        if ($content === null) {
            throw new InvalidArgumentException("File {$fileName} does not exist");
        }

        Storage::disk('s3')->put($fileName, $content);
    }

    private function sendExportEmail(string $fileName): void
    {
        Mail::to($this->user->email)->queue(new ExportEmail($fileName, $this->year, $this->month, $this->exportType));
    }
}
