<?php

namespace App\Jobs;

use App\DataTransferObjects\ExportParameters;
use App\Integrations\Exports\ExportService;
use App\Mail\ExportEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExportBudgetsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        protected User $user,
        protected string $exportType,
        protected ExportParameters $period,
    ) {}

    public function handle(ExportService $exportService): void
    {
        $format = $exportService->findFormat($this->exportType)
            ?? throw new InvalidArgumentException("Invalid export type: {$this->exportType}");

        $fileName = $this->generateFileName($format->extension);

        $exportService
            ->createExport($this->exportType, $this->period)
            ->export(Storage::disk('temp')->path($fileName));

        $this->storeExport($fileName);
        $this->sendExportEmail($fileName);
    }

    private function generateFileName(string $extension): string
    {
        if ($this->period->fromMonth !== null) {
            // The download link is public and filtered exports of the same range differ, so the name must be unguessable.
            return "export_{$this->exportType}_{$this->period->fromMonth}_{$this->period->toMonth}_".Str::random(16).".{$extension}";
        }

        if ($this->period->year === null) {
            return "export_{$this->exportType}.{$extension}";
        }

        return "export_{$this->exportType}_{$this->period->year}_".($this->period->month ?? 'all').".{$extension}";
    }

    private function storeExport(string $fileName): void
    {
        $content = Storage::disk('temp')->get($fileName);

        if ($content === null) {
            throw new InvalidArgumentException("File {$fileName} does not exist");
        }

        Storage::put($fileName, $content);
    }

    private function sendExportEmail(string $fileName): void
    {
        Mail::to($this->user->email)->queue(new ExportEmail($fileName, $this->period->year, $this->period->month, $this->exportType));
    }
}
