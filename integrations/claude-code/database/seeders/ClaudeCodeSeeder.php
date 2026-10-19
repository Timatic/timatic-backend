<?php

declare(strict_types=1);

namespace Timatic\ClaudeCode\Database\Seeders;

use App\Models\ApiClient;
use App\Models\EventType;
use App\Models\Source;
use Illuminate\Database\Seeder;
use Timatic\ClaudeCode\ServiceProvider;

class ClaudeCodeSeeder extends Seeder
{
    /**
     * A prompt or an answer means someone is at the keyboard steering the work, which is stronger
     * evidence than any Jira transition, so these win from everything but a rebase.
     *
     * @var array<string, array{name: string, weight: int}>
     */
    private array $eventTypes = [
        ServiceProvider::EVENT_TYPE_PROMPT_SUBMITTED => ['name' => 'Claude Code prompt', 'weight' => 20],
        ServiceProvider::EVENT_TYPE_QUESTION_ANSWERED => ['name' => 'Claude Code question answered', 'weight' => 20],
    ];

    public function run(): void
    {
        Source::query()->updateOrCreate(['id' => ServiceProvider::SOURCE_ID], ['title' => 'Claude Code']);

        foreach ($this->eventTypes as $id => $attributes) {
            EventType::query()->updateOrCreate(['id' => $id], $attributes);
        }

        ApiClient::query()->updateOrCreate(['id' => ServiceProvider::CLIENT_ID], [
            'label' => 'Claude Code plugin',
            // The plugin listens on whichever loopback port is free; see ApiClient::allowsRedirectUri.
            'redirect_uris' => ['http://127.0.0.1/callback'],
            'token_lifetime_days' => 90,
            // Its hooks run unattended, so a token that expires mid use would silently stop tracking.
            'sliding_expiration' => true,
            'auto_approve' => false,
        ]);
    }
}
