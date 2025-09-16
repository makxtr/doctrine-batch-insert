<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\UpdateStrategy;

use makxtr\DoctrineBatchInsert\DTO\BatchQueryFieldsDTO;
use makxtr\DoctrineBatchInsert\Visitor\PlatformVisitor;

interface UpdateStrategyInterface
{
    public function getInsertStatement(PlatformVisitor $platformVisitor): string;

    public function getPostValuesStatement(
        PlatformVisitor $platformVisitor,
        string $tableName,
        BatchQueryFieldsDTO $fieldsDTO,
    ): string;
}
