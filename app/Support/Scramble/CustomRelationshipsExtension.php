<?php

declare(strict_types=1);

namespace App\Support\Scramble;

use App\Http\Resources\JsonApiResource;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource as BaseJsonApiResource;

/**
 * Documents toCustomRelationships() as relationships, since Scramble only reads $relationships and toRelationships().
 */
class CustomRelationshipsExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return is_subclass_of($name, JsonApiResource::class)
            && (new \ReflectionMethod($name, 'toCustomRelationships'))->getDeclaringClass()->getName() === $name;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $customRelationships = ReferenceTypeResolver::getInstance()->resolve(
            new GlobalScope,
            new MethodCallReferenceType(new ObjectType($event->name), 'toCustomRelationships', []),
        );

        if (! $customRelationships instanceof KeyedArrayType) {
            return;
        }

        $relationships = $event->classDefinition->getPropertyDefinition('relationships');
        $defaultType = $relationships?->defaultType;

        $items = [
            ...($defaultType instanceof KeyedArrayType ? $defaultType->items : []),
            ...collect($customRelationships->items)
                ->map(fn (ArrayItemType_ $item) => transform($this->resourceType($item->value), fn (Type $resource) => new ArrayItemType_($item->key, $resource)))
                ->filter()
                ->all(),
        ];

        $event->classDefinition->properties['relationships'] = new ClassPropertyDefinition(
            type: new KeyedArrayType($items),
            defaultType: new KeyedArrayType($items),
        );
    }

    private function resourceType(Type $type): ?Type
    {
        $type = $type instanceof FunctionType ? $type->getReturnType() : $type;
        $candidates = $type instanceof Union ? $type->types : [$type];

        return collect($candidates)->first(
            fn (Type $candidate) => $candidate->isInstanceOf(BaseJsonApiResource::class) || $candidate->isInstanceOf(ResourceCollection::class),
        );
    }
}
