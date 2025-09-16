<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\PrimaryKeyGeneratorStrategy;

use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Mapping\ClassMetadata;

interface PrimaryKeyGeneratorTypeInterface
{
    public function prepareEntitiesForInsert(Collection $entityCollection, ClassMetadata $entityMetadata): void;
}
