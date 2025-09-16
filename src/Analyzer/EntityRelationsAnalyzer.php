<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Analyzer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadata;
use makxtr\DoctrineBatchInsert\DTO\Response\EntityCollectionRelationsResponseObject;

readonly class EntityRelationsAnalyzer implements EntityRelationsAnalyzerInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function getEntityCollectionRelations(Collection $entityCollection, ClassMetadata $classMetadata): EntityCollectionRelationsResponseObject
    {
        $response = new EntityCollectionRelationsResponseObject();

        foreach ($this->getEntityRelationFields($classMetadata) as $relationFieldMetadata) {
            $relatedEntityClassName = $relationFieldMetadata['targetEntity'];

            foreach ($entityCollection as $entity) {
                $related = $classMetadata->getFieldValue($entity, $relationFieldMetadata['name']);

                if (empty($related)) {
                    continue;
                }

                $newRelatedEntities = $this->getNewRelatedEntities($related, $relationFieldMetadata['type']);

                if (empty($newRelatedEntities)) {
                    continue;
                }

                if ($relationFieldMetadata['isOwningSide']) {
                    $response->addRelatedEntities($relatedEntityClassName, $newRelatedEntities);
                } else {
                    $response->addInverselyRelatedEntities($relatedEntityClassName, $newRelatedEntities);
                }
            }
        }

        return $response;
    }

    private function getEntityRelationFields(ClassMetadata $classMetadata): ArrayCollection
    {
        $relationFields = new ArrayCollection();

        foreach ($classMetadata->associationMappings as $mappingName => $associationMapping) {
            if ($this->shouldInsertAssociationEntities($associationMapping)) {
                $relationFields->add([
                    'type' => $associationMapping['type'],
                    'targetEntity' => $associationMapping['targetEntity'],
                    'name' => $associationMapping['fieldName'],
                    'isOwningSide' => $associationMapping['isOwningSide'],
                ]);
            }
        }

        return $relationFields;
    }

    private function shouldInsertAssociationEntities(array $associationMapping): bool
    {
        $hasSupportedAssociationType = in_array($associationMapping['type'], [
            ClassMetadataInfo::MANY_TO_ONE,
            ClassMetadataInfo::ONE_TO_ONE,
            ClassMetadataInfo::ONE_TO_MANY,
        ], true);

        return
            $hasSupportedAssociationType &&
            ($associationMapping['isOwningSide'] || $associationMapping['isCascadePersist']);
    }

    private function getNewRelatedEntities(
        mixed $related,
        int $associationType,
    ): array {
        $newRelatedEntities = [];

        if (ClassMetadataInfo::ONE_TO_MANY === $associationType) {
            $allRelatedEntities = is_object($related) ? $related->toArray() : $related;
        } else {
            $allRelatedEntities = [$related];
        }

        foreach ($allRelatedEntities as $relatedEntity) {
            if ($this->entityManager->contains($relatedEntity)) {
                continue;
            }

            $newRelatedEntities[] = $relatedEntity;
        }

        return $newRelatedEntities;
    }
}
