<?php

use App\Models\EventType;
use App\Models\Source;
use Illuminate\Database\Migrations\Migration;
use Timatic\Gmail\ServiceProvider;

return new class extends Migration
{
    /**
     * A sent mail is written work at a known moment, as strong as a pull request comment. A saved
     * draft ranks just below it: it may still be abandoned.
     *
     * @var array<string, array{name: string, weight: int}>
     */
    private array $eventTypes = [
        ServiceProvider::EVENT_TYPE_EMAIL_SENT => ['name' => 'Email sent', 'weight' => 40],
        ServiceProvider::EVENT_TYPE_EMAIL_DRAFT_SAVED => ['name' => 'Email draft saved', 'weight' => 45],
    ];

    public function up(): void
    {
        Source::firstOrCreate(['id' => ServiceProvider::SOURCE_ID], ['title' => 'Gmail']);

        foreach ($this->eventTypes as $id => $attributes) {
            EventType::query()->updateOrCreate(['id' => $id], $attributes);
        }
    }

    public function down(): void
    {
        Source::where('id', ServiceProvider::SOURCE_ID)->delete();
        EventType::whereIn('id', array_keys($this->eventTypes))->delete();
    }
};
