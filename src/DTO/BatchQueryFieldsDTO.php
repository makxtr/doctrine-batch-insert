<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\DTO;

readonly class BatchQueryFieldsDTO
{
    public function __construct(
        private array $returnFields,
        private array $updateFields,
        private array $mergeFields,
        private array $conflictFields,
    ) {
    }

    public function getConflictFields(): array
    {
        return $this->conflictFields;
    }

    public function getReturnFields(): array
    {
        return $this->returnFields;
    }

    public function getMergeFields(): array
    {
        return $this->mergeFields;
    }

    public function getUpdateFields(): array
    {
        return $this->updateFields;
    }
}
