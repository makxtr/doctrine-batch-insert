<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\PrimaryKeyGeneratorStrategy\Type;

use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Mapping\ClassMetadata;
use makxtr\DoctrineBatchInsert\PrimaryKeyGeneratorStrategy\PrimaryKeyGeneratorTypeInterface;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Uid\Uuid;

class UuidGeneratorType implements PrimaryKeyGeneratorTypeInterface
{
    /** @throws ReflectionException */
    public function prepareEntitiesForInsert(Collection $entityCollection, ClassMetadata $entityMetadata): void
    {
        foreach ($entityCollection as $entity) {
            $this->generateUuid($entity, $entityMetadata);

            foreach ($entityMetadata->lifecycleCallbacks as $lifecycleCallback => $callbackData) {
                foreach ($callbackData as $callback) {
                    $entity->{$callback}();
                }
            }
        }
    }

    /** @throws ReflectionException */
    private function generateUuid(object $entity, ClassMetadata $entityMetadata): void
    {
        $idFields = $entityMetadata->getIdentifierFieldNames();

        foreach ($idFields as $idField) {
            if ($entityMetadata->getFieldValue($entity, $idField)) {
                continue;
            }

            $uuid = Uuid::v4();

            $setter = 'set' . ucfirst($idField);

            if (method_exists($entity, $setter)) {
                $entity->{$setter}($uuid);
            } else {
                $reflectionProperty = new ReflectionProperty($entity, $idField);
                $reflectionProperty->setValue($entity, $uuid);
            }
        }
    }
}
