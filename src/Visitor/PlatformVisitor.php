<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Visitor;

use makxtr\DoctrineBatchInsert\DTO\BatchQueryFieldsDTO;

interface PlatformVisitor
{
    public function getDefaultUpdateInsertStatement(): string;

    public function getIgnoreUpdateInsertStatement(): string;

    public function getOnDuplicateKeyUpdateInsertStatement(): string;

    public function getReplaceUpdateInsertStatement(): string;

    public function getOnDuplicateKeyUpdatePostValuesStatement(
        string $tableName,
        BatchQueryFieldsDTO $fieldsDTO,
    ): string;

    public function getDefaultUpdatePostValuesStatement(): string;

    public function getIgnoreUpdatePostValuesStatement(array $conflictFields): string;

    public function getReplaceUpdatePostValuesStatement(): string;
}
