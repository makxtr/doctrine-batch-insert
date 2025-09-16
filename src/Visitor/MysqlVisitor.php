<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Visitor;

use makxtr\DoctrineBatchInsert\Builder\BatchInsertSQLBuilder;
use makxtr\DoctrineBatchInsert\DTO\BatchQueryFieldsDTO;

class MysqlVisitor implements PlatformVisitor
{
    public function getDefaultUpdateInsertStatement(): string
    {
        return 'INSERT';
    }

    public function getIgnoreUpdateInsertStatement(): string
    {
        return 'INSERT IGNORE';
    }

    public function getOnDuplicateKeyUpdateInsertStatement(): string
    {
        return 'INSERT';
    }

    public function getReplaceUpdateInsertStatement(): string
    {
        return 'REPLACE';
    }

    public function getOnDuplicateKeyUpdatePostValuesStatement(
        string $tableName,
        BatchQueryFieldsDTO $fieldsDTO,
    ): string {
        $statement = "ON DUPLICATE KEY UPDATE\n";

        $updateArray = [];

        foreach ($fieldsDTO->getMergeFields() as $mergeField) {
            $updateArray[] = "{$mergeField} = JSON_ARRAY_APPEND({$mergeField}, '$', JSON_UNQUOTE(JSON_EXTRACT(VALUES({$mergeField}), '$')))\n";
        }

        foreach ($fieldsDTO->getUpdateFields() as $updateField) {
            $updateArray[] = "{$updateField} = VALUES({$updateField})\n";
        }

        $statement .= implode(BatchInsertSQLBuilder::VALUES_COMMA_SEPARATOR, $updateArray);

        return $statement;
    }

    public function getDefaultUpdatePostValuesStatement(): string
    {
        return '';
    }

    public function getIgnoreUpdatePostValuesStatement(array $conflictFields = []): string
    {
        return '';
    }

    public function getReplaceUpdatePostValuesStatement(): string
    {
        return '';
    }
}
