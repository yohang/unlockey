<?php
declare(strict_types=1);

namespace App\JsonSchema;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryAwareInterface;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Locker;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Documents the computed "transitions" key of the Locker representation
 * (added at runtime by {@see \App\Serializer\LockerTransitionsNormalizer}).
 */
final class LockerSchemaFactory implements SchemaFactoryInterface, SchemaFactoryAwareInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly SchemaFactoryInterface $decorated,
    )
    {
    }

    #[\Override]
    public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
    {
        $schema = $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);

        if (Locker::class !== $className || Schema::TYPE_OUTPUT !== $type) {
            return $schema;
        }

        // Collections wrap the item definition in an allOf, so patch every Locker definition
        // present in this schema ("Locker.jsonld-locker.read", "OpenLocker.Locker.jsonld-locker.read", ...).
        $definitions = $schema->getDefinitions();
        foreach ($definitions as $key => $definition) {
            if (!is_string($key) || !preg_match('/(^|\.)Locker(\.|$)/', $key)) {
                continue;
            }

            $this->addTransitionsProperty($definition);
        }

        return $schema;
    }

    /**
     * JSON-LD definitions are "allOf: [HydraItemBaseSchema, {type: object, properties: ...}]";
     * plain ones carry "properties" at the top level. Patch whichever holds the properties.
     */
    private function addTransitionsProperty(\ArrayObject $definition): void
    {
        $target = $definition;
        if (isset($definition['allOf']) && is_iterable($definition['allOf'])) {
            foreach ($definition['allOf'] as $part) {
                if (($part instanceof \ArrayObject || is_array($part)) && isset($part['properties'])) {
                    $target = $part;
                    break;
                }
            }
        }

        if (!$target instanceof \ArrayObject) {
            return;
        }

        $properties = $target['properties'] ?? new \ArrayObject();
        $properties['transitions'] = new \ArrayObject([
            'type' => 'array',
            'items' => ['type' => 'string'],
            'readOnly' => true,
            'description' => 'Reachable transition names (open, close), computed from the state machine.',
            'example' => ['open'],
        ]);
        $target['properties'] = $properties;
    }

    #[\Override]
    public function setSchemaFactory(SchemaFactoryInterface $schemaFactory): void
    {
        if ($this->decorated instanceof SchemaFactoryAwareInterface) {
            $this->decorated->setSchemaFactory($schemaFactory);
        }
    }
}
