<?php

namespace App\Models;

use Carbon\Carbon;
use Exception;

class DailyProgress
{
    protected Carbon $date;

    protected ?int $progress;

    protected string $userId;

    /**
     * @param  array<string, mixed>  $fields
     *
     * @throws Exception
     */
    public function __construct(array $fields = [])
    {
        if ($fields['date'] instanceof Carbon) {
            $this->date = $fields['date'];
        } else {
            $date = Carbon::createFromFormat('Y-m-d', $fields['date'], config('timatic.preferred_timezone'));
            if (! ($date instanceof Carbon)) {
                throw new Exception('Invalid date.');
            }

            $this->date = $date;
        }

        $this->progress = $fields['progress'] ?? null;
        $this->userId = $fields['userId'];
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getDate(): Carbon
    {
        return $this->date;
    }

    public function getProgress(): ?int
    {
        return $this->progress;
    }

    /**
     * @return array<string>
     */
    public function getFillable(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getDates(): array
    {
        return ['date'];
    }

    public function getId(): string
    {
        return sha1($this->getUserId().$this->getDate()->toDateString());
    }

    public function setProgress(int $progress): void
    {
        $this->progress = $progress;
    }
}
