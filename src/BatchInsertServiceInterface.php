<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert;

use Doctrine\Common\Collections\Collection;
use makxtr\DoctrineBatchInsert\DTO\Request\BatchInsertRequest;

interface BatchInsertServiceInterface
{
    /**
     * @param Collection<int, covariant BatchInsertableInterface> $entityCollection
     */
    public function lightBatchInsert(Collection $entityCollection): void;

    /**
     * @param Collection<int, covariant BatchInsertableInterface> $entityCollection
     * @param array<string> $returnFields
     * @return array<int, array<string, mixed>>
     */
    public function lightBatchInsertWithResult(Collection $entityCollection, array $returnFields = []): array;

    public function batchInsert(BatchInsertRequest $request): Collection;

    public function batchInsertWithResult(BatchInsertRequest $request): array;
}
