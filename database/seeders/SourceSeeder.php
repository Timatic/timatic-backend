<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * The sources this application's own clients send events from; integration packages seed their own.
     *
     * @var array<string, string>
     */
    private array $sources = [
        'browser' => 'Browser',
    ];

    public function run(): void
    {
        foreach ($this->sources as $id => $title) {
            Source::query()->updateOrCreate(['id' => $id], ['title' => $title]);
        }
    }
}
