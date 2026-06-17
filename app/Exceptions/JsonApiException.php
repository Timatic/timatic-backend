<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class JsonApiException extends Exception
{
    /**
     * @param  array<int|string, mixed>  $errors
     */
    public function __construct(
        public int $status = 422,
        public array $errors = [],
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'errors' => $this->errors,
        ], $this->status);
    }
}
