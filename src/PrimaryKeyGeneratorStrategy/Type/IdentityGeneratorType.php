<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\PrimaryKeyGeneratorStrategy\Type;

use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Mapping\ClassMetadata;
use makxtr\DoctrineBatchInsert\PrimaryKeyGeneratorStrategy\PrimaryKeyGeneratorTypeInterface;

class IdentityGeneratorType implements PrimaryKeyGeneratorTypeInterface
{
    public function prepareEntitiesForInsert(Collection $entityCollection, ClassMetadata $entityMetadata): void
    {
        foreach ($entityCollection as $entity) {
            foreach ($entityMetadata->lifecycleCallbacks as $lifecycleCallback => $callbackData) {
                foreach ($callbackData as $callback) {
                    $entity->{$callback}();
                }
            }
        }
    }
}
