<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Analyzer;

use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Mapping\ClassMetadata;
use makxtr\DoctrineBatchInsert\DTO\Response\EntityCollectionRelationsResponseObject;

interface EntityRelationsAnalyzerInterface
{
    public function getEntityCollectionRelations(Collection $entityCollection, ClassMetadata $classMetadata): EntityCollectionRelationsResponseObject;
}
