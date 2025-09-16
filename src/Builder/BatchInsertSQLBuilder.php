<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Builder;

use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use makxtr\DoctrineBatchInsert\DTO\BatchQueryFieldsDTO;
use makxtr\DoctrineBatchInsert\UpdateStrategy\UpdateStrategyInterface;
use makxtr\DoctrineBatchInsert\Visitor\PlatformContext;
use makxtr\DoctrineBatchInsert\Visitor\PlatformVisitor;
use makxtr\DoctrineBatchInsert\Util\BatchInsertOptions;
use makxtr\DoctrineBatchInsert\Util\StringHelper;
use Symfony\Component\Uid\Uuid;

class BatchInsertSQLBuilder
{
    public const string VALUES_COMMA_SEPARATOR = ', ';

    private ClassMetadata $typeMetaData;

    private ?string $tableName;

    private PlatformVisitor $platformVisitor;

    private UpdateStrategyInterface $updateStrategy;

    private array $directFields = [];

    private array $relationFields = [];

    private BatchQueryFieldsDTO $batchQueryFieldsDTO;

    private array|ClassMetadata $relationFieldsMetaData = [];

    private array $values = [];

    private Connection $connection;

    private ClassMetadataFactory $classMetadataFactory;

    public function __construct(Connection $connection, ClassMetadataFactory $classMetadataFactory)
    {
        $this->connection = $connection;
        $this->classMetadataFactory = $classMetadataFactory;
        $this->platformVisitor = PlatformContext::getPlatformVisitor($connection->getDatabasePlatform());
    }

    public static function createWithPureDependency(Connection $connection, ClassMetadataFactory $classMetadataFactory): self
    {
        return new self($connection, $classMetadataFactory);
    }

    public function withType(ClassMetadata $classMetadata): self
    {
        $this->typeMetaData = $classMetadata;
        $this->tableName = $this->typeMetaData->table['name'];
        $this->obtainFields();

        return $this;
    }

    public function withFields(
        array $returnFields,
        array $updateFields,
        array $mergeFields,
        array $conflictFields,
    ): self {
        $updateFields = $updateFields ?: $this->columnNames();

        foreach ($mergeFields as $mergeField) {
            unset($updateFields[array_search($mergeField, $updateFields, true)]);
        }

        $this->batchQueryFieldsDTO = new BatchQueryFieldsDTO(
            $returnFields ?: $this->columnNamesWithId(),
            $updateFields,
            $mergeFields,
            $conflictFields ?: ['id'],
        );

        return $this;
    }

    public function withUpdateStrategy(UpdateStrategyInterface $updateStrategy): self
    {
        $this->updateStrategy = $updateStrategy;

        return $this;
    }

    /**
     * @throws MappingException
     * @throws Exception
     */
    public function withEntities(Collection $entities): self
    {
        foreach ($entities as $entity) {
            $directFieldValues = $this->getDirectFieldValues($entity);
            $relatedFieldValues = $this->getRelatedFieldValues($entity);
            $this->values[] = array_merge($directFieldValues, $relatedFieldValues);
        }

        return $this;
    }

    public function build(): string
    {
        $sql = "
            {$this->updateStrategy->getInsertStatement($this->platformVisitor)}
            INTO {$this->tableName} ({$this->columnNamesAsString()})
            VALUES {$this->values()}
            {$this->updateStrategy->getPostValuesStatement($this->platformVisitor, $this->tableName, $this->batchQueryFieldsDTO)}
            RETURNING {$this->returnColumnNamesAsString()}
        ";

        $this->values = [];
        $this->directFields = [];
        $this->relationFields = [];
        $this->relationFieldsMetaData = [];

        return $sql;
    }

    private function values(): string
    {
        $values = [];

        foreach ($this->values as $value) {
            if ($this->typeMetaData->usesIdGenerator()) {
                unset($value[0]);
            }

            foreach ($value as $key => $item) {
                if (is_string($item)) {
                    $value[$key] = sprintf("'%s'", $item);
                }
                if (is_bool($item)) {
                    $value[$key] = sprintf("'%d'", (int) $item);
                }
                if (is_null($item)) {
                    $value[$key] = 'null';
                }
            }

            $values[] = sprintf('(%s)', implode(self::VALUES_COMMA_SEPARATOR, $value));
        }

        return implode(self::VALUES_COMMA_SEPARATOR, $values);
    }

    private function columnNamesAsString(): string
    {
        return implode(self::VALUES_COMMA_SEPARATOR, $this->columnNames());
    }

    private function returnColumnNamesAsString(): string
    {
        return implode(
            self::VALUES_COMMA_SEPARATOR,
            $this->batchQueryFieldsDTO->getReturnFields(),
        );
    }

    private function columnNamesWithId(): array
    {
        return array_merge(array_keys($this->directFields), array_keys($this->relationFields));
    }

    private function columnNames(): array
    {
        $directField = array_keys($this->directFields);

        if ($this->typeMetaData->usesIdGenerator()) {
            unset($directField[0]);
        }

        return array_merge($directField, array_keys($this->relationFields));
    }

    /**
     * @throws MappingException
     * @throws Exception
     */
    private function getRelatedFieldValues(object $entity): array
    {
        $relatedFieldValues = [];

        foreach ($this->relationFields as $relationColumnName => $relationField) {
            $relatedEntity = $this->typeMetaData->getFieldValue($entity, $relationField);

            if (empty($relatedEntity)) {
                $relatedFieldValues[] = null;

                continue;
            }

            $relationMapping = $this->typeMetaData->getAssociationMapping($relationField);
            $relationClassMetaData = $this->getRelatedClassMetaData($relationMapping['targetEntity']);
            $relatedFieldName = $relationMapping['sourceToTargetKeyColumns'][$relationColumnName];
            $relatedFieldValues[] = $this->fieldValue($relatedEntity, $relationClassMetaData, $relatedFieldName);
        }

        return $relatedFieldValues;
    }

    private function getRelatedClassMetaData(string $className): ClassMetadata
    {
        if (empty($this->relationFieldsMetaData[$className])) {
            $this->relationFieldsMetaData[$className] = $this->classMetadataFactory->getMetadataFor($className);
        }

        return $this->relationFieldsMetaData[$className];
    }

    /**
     * @throws MappingException
     * @throws Exception
     */
    private function getDirectFieldValues(object $entity): array
    {
        $directFieldValues = [];

        foreach ($this->directFields as $directField) {
            $directFieldValues[] = $this->fieldValue($entity, $this->typeMetaData, $directField);
        }

        return $directFieldValues;
    }

    /**
     * @throws MappingException
     * @throws Exception
     */
    private function fieldValue(object $entity, ClassMetadata $classMetadata, string $fieldName): mixed
    {
        $fieldValue = $classMetadata->getFieldValue($entity, $fieldName);

        if (is_string($fieldValue)) {
            return StringHelper::prepareStringValueForInsert($fieldValue);
        }

        if (is_scalar($fieldValue) || is_null($fieldValue)) {
            return $fieldValue;
        }

        if ($fieldValue instanceof DateTimeImmutable) {
            return $fieldValue->format(BatchInsertOptions::TIME_FORMAT);
        }

        if ($fieldValue instanceof Uuid) {
            return $fieldValue->__toString();
        }

        $fieldMapping = $classMetadata->getFieldMapping($fieldName);

        $databaseValue = $this->connection->convertToDatabaseValue($fieldValue, $fieldMapping['type']);

        if (is_string($databaseValue)) {
            $databaseValue = StringHelper::prepareStringValueForInsert($databaseValue);
        }

        return $databaseValue;
    }

    private function obtainFields(): void
    {
        $this->directFields = $this->typeMetaData->fieldNames;
        $relationFields = [];

        foreach ($this->typeMetaData->associationMappings as $mappingName => $associationMapping) {
            if ($this->shouldSkipAssociation($associationMapping)) {
                continue;
            }

            $sourceToTargetKeyColumn = array_key_first($associationMapping['sourceToTargetKeyColumns']);
            $relationFields[$sourceToTargetKeyColumn] = $associationMapping['fieldName'];
        }

        $this->relationFields = $relationFields;
    }

    private function shouldSkipAssociation(array $associationMapping): bool
    {
        return
            !in_array(
                $associationMapping['type'],
                [
                    ClassMetadataInfo::MANY_TO_ONE,
                    ClassMetadataInfo::ONE_TO_ONE,
                ],
                true
            ) ||
            !$associationMapping['isOwningSide'];
    }
}
