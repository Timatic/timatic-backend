<?php

declare(strict_types=1);

namespace Timatic\ClaudeCode;

use App\Integrations\SuggestionIntegrationRegistry;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public const SOURCE_ID = 'claude_code';

    /** The api client the Claude Code plugin logs in as. */
    public const CLIENT_ID = 'claude-code';

    public const EVENT_TYPE_PROMPT_SUBMITTED = 'claude_prompt_submitted';

    public const EVENT_TYPE_QUESTION_ANSWERED = 'claude_question_answered';

    public function boot(SuggestionIntegrationRegistry $suggestionIntegrations): void
    {
        $suggestionIntegrations->register('claude_code', SuggestionIntegration::class);
        $this->publishes([__DIR__.'/../resources/icons/claude_code.svg' => public_path('icons/claude_code.svg')], 'source-icons');

        $this->mergeConfigFrom(__DIR__.'/../config/claude_code.php', 'claude_code');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'claude_code');
    }
}
