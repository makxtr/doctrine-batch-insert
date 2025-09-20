<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use JsonException;
use makxtr\DoctrineBatchInsert\Analyzer\EntityRelationsAnalyzer;
use makxtr\DoctrineBatchInsert\Analyzer\EntityRelationsAnalyzerInterface;
use makxtr\DoctrineBatchInsert\Builder\BatchInsertSQLBuilder;
use makxtr\DoctrineBatchInsert\DTO\Request\BatchInsertRequest;
use makxtr\DoctrineBatchInsert\PrimaryKeyGeneratorStrategy\PrimaryKeyGeneratorStrategy;
use makxtr\DoctrineBatchInsert\UpdateStrategy\UpdateStrategyInterface;
use makxtr\DoctrineBatchInsert\Validator\EntityCollectionValidatorInterface;
use makxtr\DoctrineBatchInsert\Util\BatchInsertOptions;
use Symfony\Component\Uid\Uuid;
use Throwable;

class BatchInsertService implements BatchInsertServiceInterface
{
    private Connection $connection;

    private ClassMetadataFactory $classMetadataFactory;

    private BatchInsertSQLBuilder $builder;

    private EntityRelationsAnalyzer|EntityRelationsAnalyzerInterface $entityRelationsAnalyzer;

    private PrimaryKeyGeneratorStrategy $primaryKeyGeneratorStrategy;

    private EntityCollectionValidatorInterface $collectionValidator;

    public function __construct(
        EntityManagerInterface $em,
        EntityCollectionValidatorInterface $collectionValidator,
        EntityRelationsAnalyzerInterface $entityRelationsAnalyzer,
        ?BatchInsertSQLBuilder $builder = null,
    ) {
        $this->connection = $em->getConnection();
        $this->classMetadataFactory = $em->getMetadataFactory();
        $this->collectionValidator = $collectionValidator;
        $this->entityRelationsAnalyzer = $entityRelationsAnalyzer;
        $this->builder = $builder ?? BatchInsertSQLBuilder::createWithPureDependency($this->connection, $this->classMetadataFactory);
        $this->primaryKeyGeneratorStrategy = new PrimaryKeyGeneratorStrategy();
    }

    /**
     * @throws Exception
     * @throws JsonException
     */
    public function lightBatchInsert(Collection $entityCollection): void
    {
        if ($entityCollection->isEmpty()) {
            return;
        }

        $this->collectionValidator->validateCollectionBeforeLightInsert($entityCollection);

        $tableName = $this->getEntityCollectionClassMetadata($entityCollection)->getTableName();
        $entitiesData = $this->getEntitiesData($entityCollection);

        $sql = $this->prepareLightQueryForInsert($tableName, $entitiesData);

        $this->connection->executeStatement($sql);

        gc_collect_cycles();
    }

    /**
     * @throws Exception
     * @throws JsonException
     */
    public function lightBatchInsertWithResult(Collection $entityCollection, array $returnFields = []): array
    {
        if ($entityCollection->isEmpty()) {
            return [];
        }

        $this->collectionValidator->validateCollectionBeforeLightInsert($entityCollection);

        $classMetadata = $this->getEntityCollectionClassMetadata($entityCollection);
        $tableName = $classMetadata->getTableName();
        $entitiesData = $this->getEntitiesData($entityCollection);

        $allFields = array_keys($entitiesData[0]);
        if ($classMetadata->usesIdGenerator() && !in_array('id', $allFields, true)) {
            $allFields[] = 'id';
        }

        $sql = $this->prepareLightQueryForInsert(
            $tableName,
            $entitiesData,
            $returnFields ?: $allFields,
        );

        $result = $this->connection
            ->executeQuery($sql)
            ->fetchAllAssociative();

        gc_collect_cycles();

        return $result;
    }

    /**
     * @throws MappingException|Throwable
     */
    public function batchInsert(BatchInsertRequest $request): Collection
    {
        $entityCollection = $request->getEntityCollection();

        if ($entityCollection->isEmpty()) {
            return $entityCollection;
        }

        $this->collectionValidator->validateCollection($entityCollection);

        if ($request->isWithRelations()) {
            $this->executeBatchInsertWithRelations($request);
        } else {
            $this->executeBatchInsert($request);
        }

        return $entityCollection;
    }

    /**
     * @throws Throwable
     * @throws MappingException
     */
    public function batchInsertWithResult(BatchInsertRequest $request): array
    {
        $result = [];

        $entityCollection = $request->getEntityCollection();

        if ($entityCollection->isEmpty()) {
            return $result;
        }

        $this->collectionValidator->validateCollection($entityCollection);

        if ($request->isWithRelations()) {
            $result = $this->executeBatchInsertWithRelations($request, true);
        } else {
            $result = $this->executeBatchInsertWithResult($request);
        }

        return $result;
    }

    /**
     * @throws JsonException
     */
    private function prepareLightQueryForInsert(string $tableName, array $entitiesData, array $returnFields = []): string
    {
        $columns = array_keys($entitiesData[0]);
        $columnsAsString = implode(BatchInsertSQLBuilder::VALUES_COMMA_SEPARATOR, $columns);

        $values = [];
        foreach ($entitiesData as $entityData) {
            foreach ($entityData as $key => $value) {
                $entityData[$key] = $this->convertEntityValueToDatabaseValue($value);
            }

            $values[] = sprintf(
                '(%s)',
                implode(BatchInsertSQLBuilder::VALUES_COMMA_SEPARATOR, $entityData),
            );
        }
        $valuesAsString = implode(BatchInsertSQLBuilder::VALUES_COMMA_SEPARATOR, $values);

        $sql = "
            INSERT INTO {$tableName} ({$columnsAsString}) 
            VALUES {$valuesAsString}
        ";

        if (!empty($returnFields)) {
            $returnFieldsAsString = implode(BatchInsertSQLBuilder::VALUES_COMMA_SEPARATOR, $returnFields);

            $sql .= "\nRETURNING {$returnFieldsAsString}";
        }

        return $sql;
    }

    private function convertEntityValueToDatabaseValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return sprintf("'%d'", (int) $value);
        }

        if (is_string($value)) {
            return $this->connection->quote($value);
        }

        if (is_null($value)) {
            return 'null';
        }

        if ($value instanceof \DateTimeImmutable) {
            return $this->connection->quote($value->format(BatchInsertOptions::TIME_FORMAT));
        }

        if ($value instanceof Uuid) {
            return $this->connection->quote($value->__toString());
        }

        if (is_array($value)) {
            return $this->connection->quote(json_encode($value, JSON_THROW_ON_ERROR));
        }

        return $value;
    }

    private function getEntitiesData(Collection $entityCollection): array
    {
        return array_map(function (BatchInsertableInterface $entity) {
            return $entity->getBatchInsertData();
        }, array_values($entityCollection->toArray()));
    }

    /**
     * @throws MappingException
     * @throws Throwable
     */
    private function executeBatchInsertWithRelations(BatchInsertRequest $request, bool $withResult = false): array
    {
        $result = [];

        $entityCollection = $request->getEntityCollection();

        $allRelatedEntities = $this->entityRelationsAnalyzer->getEntityCollectionRelations(
            $entityCollection,
            $this->getEntityCollectionClassMetadata($entityCollection)
        );

        if (!empty($allRelatedEntities->hasRelatedEntities())) {
            $this->batchInsertRelatedEntities(
                $allRelatedEntities->getRelatedEntities(),
                $request->getUpdateStrategy()
            );
        }

        if ($withResult) {
            $result = $this->executeBatchInsertWithResult($request);
        } else {
            $this->executeBatchInsert($request);
        }

        if (!empty($allRelatedEntities->hasInverselyRelatedEntities())) {
            $this->batchInsertRelatedEntities(
                $allRelatedEntities->getInverselyRelatedEntities(),
                $request->getUpdateStrategy()
            );
        }

        return $result;
    }

    /** @throws Exception */
    private function executeBatchInsert(BatchInsertRequest $request): void
    {
        $classMetadata = $this->getEntityCollectionClassMetadata(
            $request->getEntityCollection()
        );

        $sql = $this->prepareQueryForInsert($request, $classMetadata);

        $this->connection->executeStatement($sql);

        if ($request->isWithRelations()) {
            $this->setIdsForInsertedCollection(
                $request->getEntityCollection(),
                $classMetadata,
            );
        }
    }

    /** @throws Exception */
    private function executeBatchInsertWithResult(BatchInsertRequest $request): array
    {
        $classMetadata = $this->getEntityCollectionClassMetadata(
            $request->getEntityCollection()
        );

        $sql = $this->prepareQueryForInsert($request, $classMetadata);

        $result = $this->connection
            ->executeQuery($sql)
            ->fetchAllAssociative();

        if ($request->isWithRelations()) {
            $this->setIdsForInsertedCollection(
                $request->getEntityCollection(),
                $classMetadata,
            );
        }

        return $result;
    }

    private function prepareQueryForInsert(
        BatchInsertRequest $request,
        ClassMetadata $classMetadata,
    ): string {
        $entityCollection = $request->getEntityCollection();

        $this->primaryKeyGeneratorStrategy
            ->getStrategy($classMetadata)
            ->prepareEntitiesForInsert($entityCollection, $classMetadata);

        return $this->getBatchInsertQuery($request, $classMetadata);
    }

    private function getBatchInsertQuery(
        BatchInsertRequest $request,
        ClassMetadata $classMetadata,
    ): string {
        return $this->builder
            ->withType($classMetadata)
            ->withUpdateStrategy($request->getUpdateStrategy())
            ->withEntities($request->getEntityCollection())
            ->withFields(
                $request->getReturnFields(),
                $request->getUpdateFields(),
                $request->getMergeFields(),
                $request->getConflictFields(),
            )
            ->build();
    }

    /**
     * @throws MappingException
     * @throws Exception
     */
    private function setIdsForInsertedCollection(
        Collection $entityCollection,
        ClassMetadata $classMetadata,
    ): void {
        if (!$classMetadata->usesIdGenerator()) {
            return;
        }

        $lastInsertedId = $this->connection->lastInsertId();

        $firstInsertedId = $lastInsertedId - $entityCollection->count() + 1;

        foreach ($entityCollection as $entity) {
            $identifierFieldName = $classMetadata->getSingleIdentifierFieldName();

            if (!$classMetadata->getFieldValue($entity, $identifierFieldName)) {
                $classMetadata->setFieldValue(
                    $entity,
                    $identifierFieldName,
                    $firstInsertedId,
                );

                ++$firstInsertedId;
            }
        }
    }

    /**
     * @throws MappingException
     * @throws Throwable
     */
    private function batchInsertRelatedEntities(array $relatedEntities, ?UpdateStrategyInterface $updateStrategy = null): void
    {
        foreach ($relatedEntities as $relatedClassEntities) {
            $this->batchInsert(new BatchInsertRequest(
                entityCollection: $relatedClassEntities,
                updateStrategy: $updateStrategy,
            ));
        }
    }

    private function getEntityCollectionClassMetadata(Collection $entityCollection): ClassMetadata
    {
        return $this->classMetadataFactory->getMetadataFor($this->detectTypeFromCollection($entityCollection));
    }

    private function detectTypeFromCollection(Collection $entityCollection): string
    {
        return get_class($entityCollection->first());
    }
}
