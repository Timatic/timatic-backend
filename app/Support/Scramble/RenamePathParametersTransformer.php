<?php

namespace App\Support\Scramble;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Parameter;
use Illuminate\Support\Str;

class RenamePathParametersTransformer implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach ($document->paths as $path) {
            $path->path = (string) preg_replace_callback('/\{(\w+)\}/', function (array $matches) {
                return '{'.Str::finish($matches[1], 'Id').'}';
            }, $path->path);

            foreach ($path->operations as $operation) {
                foreach ($operation->parameters as $parameter) {
                    if (! $parameter instanceof Parameter) {
                        continue;
                    }

                    if ($parameter->in !== 'path') {
                        continue;
                    }

                    $parameter->setName(Str::finish($parameter->name, 'Id'));
                }
            }
        }
    }
}
