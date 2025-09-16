<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\DTO\Request;

use Doctrine\Common\Collections\Collection;
use makxtr\DoctrineBatchInsert\UpdateStrategy\DefaultStrategy;
use makxtr\DoctrineBatchInsert\UpdateStrategy\UpdateStrategyInterface;

class BatchInsertRequest
{
    public function __construct(
        private readonly Collection $entityCollection,
        private readonly array $returnFields = [],
        private readonly array $updateFields = [],
        private readonly array $mergeFields = [],
        private readonly array $conflictFields = [],
        private readonly bool $withRelations = false,
        private ?UpdateStrategyInterface $updateStrategy = null,
    ) {
        $this->updateStrategy = $this->updateStrategy ?? new DefaultStrategy();
    }

    public function getEntityCollection(): Collection
    {
        return $this->entityCollection;
    }

    public function getUpdateStrategy(): UpdateStrategyInterface
    {
        return $this->updateStrategy;
    }

    public function isWithRelations(): bool
    {
        return $this->withRelations;
    }

    public function getReturnFields(): array
    {
        return $this->returnFields;
    }

    public function getUpdateFields(): array
    {
        return $this->updateFields;
    }

    public function getConflictFields(): array
    {
        return $this->conflictFields;
    }

    public function getMergeFields(): array
    {
        return $this->mergeFields;
    }
}
