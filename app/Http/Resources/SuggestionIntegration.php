<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\SuggestionIntegrationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin SuggestionIntegrationStatus
 */
class SuggestionIntegration extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'label' => $this->label,
            'instructions' => Str::markdown($this->instructions),
            'isCompanyManaged' => $this->isCompanyManaged,
            'isActive' => $this->isActive,
            'connectUrl' => $this->connectUrl,
        ];
    }

    public function toType(Request $request): string
    {
        return 'suggestionIntegrations';
    }

    public function toId(Request $request): string
    {
        return $this->key;
    }
}
