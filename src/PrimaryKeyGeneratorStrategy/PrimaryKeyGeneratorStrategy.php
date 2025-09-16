<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\PrimaryKeyGeneratorStrategy;

use Doctrine\Persistence\Mapping\ClassMetadata;
use makxtr\DoctrineBatchInsert\PrimaryKeyGeneratorStrategy\Type\IdentityGeneratorType;
use makxtr\DoctrineBatchInsert\PrimaryKeyGeneratorStrategy\Type\UuidGeneratorType;

class PrimaryKeyGeneratorStrategy
{
    public function getStrategy(ClassMetadata $classMetadata): PrimaryKeyGeneratorTypeInterface
    {
        if ($classMetadata->usesIdGenerator()) {
            return new IdentityGeneratorType();
        }

        return new UuidGeneratorType();
    }
}
