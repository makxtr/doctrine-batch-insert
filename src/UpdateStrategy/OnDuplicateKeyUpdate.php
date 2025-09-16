<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\UpdateStrategy;

use makxtr\DoctrineBatchInsert\DTO\BatchQueryFieldsDTO;
use makxtr\DoctrineBatchInsert\Visitor\PlatformVisitor;

class OnDuplicateKeyUpdate implements UpdateStrategyInterface
{
    public function getInsertStatement(PlatformVisitor $platformVisitor): string
    {
        return $platformVisitor->getOnDuplicateKeyUpdateInsertStatement();
    }

    public function getPostValuesStatement(
        PlatformVisitor $platformVisitor,
        string $tableName,
        BatchQueryFieldsDTO $fieldsDTO,
    ): string {
        return $platformVisitor->getOnDuplicateKeyUpdatePostValuesStatement($tableName, $fieldsDTO);
    }
}
