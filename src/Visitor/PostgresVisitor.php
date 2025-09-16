<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Visitor;

use makxtr\DoctrineBatchInsert\Builder\BatchInsertSQLBuilder;
use makxtr\DoctrineBatchInsert\DTO\BatchQueryFieldsDTO;

class PostgresVisitor implements PlatformVisitor
{
    public function getDefaultUpdateInsertStatement(): string
    {
        return 'INSERT';
    }

    public function getIgnoreUpdateInsertStatement(): string
    {
        return 'INSERT';
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
        $conflictFieldsAsString = implode(', ', $fieldsDTO->getConflictFields());

        $statement = "ON CONFLICT ({$conflictFieldsAsString}) DO UPDATE SET\n";

        $updateArray = [];

        foreach ($fieldsDTO->getMergeFields() as $mergeField) {
            $updateArray[] = "{$mergeField} = {$tableName}.{$mergeField} || excluded.{$mergeField}\n";
        }

        foreach ($fieldsDTO->getUpdateFields() as $updateField) {
            $updateArray[] = "{$updateField} = excluded.{$updateField}\n";
        }

        $statement .= implode(BatchInsertSQLBuilder::VALUES_COMMA_SEPARATOR, $updateArray);

        return $statement;
    }

    public function getDefaultUpdatePostValuesStatement(): string
    {
        return '';
    }

    public function getIgnoreUpdatePostValuesStatement(array $conflictFields): string
    {
        $conflictFieldsAsString = implode(',', $conflictFields);

        return "ON CONFLICT ({$conflictFieldsAsString}) DO NOTHING";
    }

    public function getReplaceUpdatePostValuesStatement(): string
    {
        return '';
    }
}
