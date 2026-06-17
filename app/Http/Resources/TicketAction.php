<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use TiMacDonald\JsonApi\JsonApiResource;

/**
 * @mixin \App\DataTransferObjects\TicketAction
 */
class TicketAction extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'id' => $this->id,
            'entryDate' => $this->entryDate->toISOString(),
            'text' => $this->text,
            'personDisplayName' => $this->personDisplayName,
            'personEmail' => $this->personEmail,
        ];
    }

    public function toType(Request $request): string
    {
        return 'actions';
    }

    public function toId(Request $request): string
    {
        return $this->id;
    }
}
