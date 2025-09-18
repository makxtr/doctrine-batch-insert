<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Tests\Unit;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use makxtr\DoctrineBatchInsert\Analyzer\EntityRelationsAnalyzerInterface;
use makxtr\DoctrineBatchInsert\BatchInsertService;
use makxtr\DoctrineBatchInsert\BatchInsertableInterface;
use makxtr\DoctrineBatchInsert\Validator\EntityCollectionValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

class BatchInsertServiceTest extends TestCase
{
    private BatchInsertService $batchInsertService;
    private EntityManagerInterface|MockObject $entityManager;
    private Connection|MockObject $connection;
    private AbstractPlatform $databasePlatform;
    private ClassMetadataFactory|MockObject $classMetadataFactory;
    private EntityCollectionValidatorInterface|MockObject $collectionValidator;
    private EntityRelationsAnalyzerInterface|MockObject $entityRelationsAnalyzer;
    private ClassMetadata $classMetadata;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->databasePlatform = new MySQLPlatform();
        $this->classMetadataFactory = $this->createMock(ClassMetadataFactory::class);
        $this->collectionValidator = $this->createMock(EntityCollectionValidatorInterface::class);
        $this->entityRelationsAnalyzer = $this->createMock(EntityRelationsAnalyzerInterface::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->connection
            ->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn($this->databasePlatform);

        $this->connection
            ->expects($this->any())
            ->method('quote')
            ->willReturnCallback(function ($value) {
                return "'{$value}'";
            });

        $this->entityManager
            ->expects($this->any())
            ->method('getMetadataFactory')
            ->willReturn($this->classMetadataFactory);

        $this->batchInsertService = new BatchInsertService(
            $this->entityManager,
            $this->collectionValidator,
            $this->entityRelationsAnalyzer
        );
    }

    #[DataProvider('provideEntityCollections')]
    public function testLightBatchInsertSuccessfully(
        ArrayCollection $entityCollection,
        string $tableName,
        string $expectedSql
    ): void
    {
        $firstEntity = $entityCollection->first();

        $this->classMetadata = new ORMClassMetadata('DummyEntity');
        $this->classMetadata->table = ['name' => $tableName];

        $this->classMetadataFactory
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with(get_class($firstEntity))
            ->willReturn($this->classMetadata);

        $this->collectionValidator
            ->expects($this->once())
            ->method('validateCollectionBeforeLightInsert')
            ->with($entityCollection);

        $normalize = fn(string $sql) => preg_replace('/\s+/', ' ', trim($sql));

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->callback(function (string $actualSql) use ($expectedSql, $normalize) {
                return $normalize($actualSql) === $normalize($expectedSql);
            }));

        $this->batchInsertService->lightBatchInsert($entityCollection);
    }

    public static function provideEntityCollections(): array
    {
        return [
            '2 entities' => [
                new ArrayCollection([
                    self::createBatchInsertableEntity(['id' => 1, 'name' => 'Test Entity 1', 'active' => true]),
                    self::createBatchInsertableEntity(['id' => 2, 'name' => 'Test Entity 2', 'active' => false]),
                ]),
                'test_entities',
                "INSERT INTO test_entities (id, name, active) VALUES (1, 'Test Entity 1', '1'), (2, 'Test Entity 2', '0')",
            ],

            '1 entity with escaped quote' => [
                new ArrayCollection([
                    self::createBatchInsertableEntity(['id' => 10, 'name' => 'Single Entity', 'more_field' => "O'Reilly", 'active' => true]),
                ]),
                'single_table',
                "INSERT INTO single_table (id, name, more_field, active) VALUES (10, 'Single Entity', 'O'Reilly', '1')",
            ],

            'with null' => [
                new ArrayCollection([
                    self::createBatchInsertableEntity(['id' => 100, 'name' => null, 'active' => false]),
                ]),
                'nullable_table',
                "INSERT INTO nullable_table (id, name, active) VALUES (100, null, '0')",
            ],

            'with DateTimeImmutable' => [
                new ArrayCollection([
                    self::createBatchInsertableEntity(['id' => 200, 'created_at' => new \DateTimeImmutable($createdAt = '2023-05-10 12:34:56')]),
                ]),
                'datetime_table',
                "INSERT INTO datetime_table (id, created_at) VALUES (200, '$createdAt')",
            ],

            'with array' => [
                new ArrayCollection([
                    self::createBatchInsertableEntity(['id' => 300, 'data' => ['foo' => 'bar', 'baz' => 123]]),
                ]),
                'array_table',
                "INSERT INTO array_table (id, data) VALUES (300, '{\"foo\":\"bar\",\"baz\":123}')",
            ],

            'with Uuid' => [
                new ArrayCollection([
                    self::createBatchInsertableEntity(['id' => 400, 'uuid' => $uuid = Uuid::v4()]),
                ]),
                'uuid_table',
                "INSERT INTO uuid_table (id, uuid) VALUES (400, '$uuid')",
            ],
        ];
    }

    public function testLightBatchInsertWithEmptyCollection(): void
    {
        $emptyCollection = new ArrayCollection();

        $this->collectionValidator
            ->expects($this->never())
            ->method('validateCollectionBeforeLightInsert');

        $this->classMetadataFactory
            ->expects($this->never())
            ->method('getMetadataFor');

        $this->connection
            ->expects($this->never())
            ->method('executeStatement');

        $this->batchInsertService->lightBatchInsert($emptyCollection);
    }

    private static function createBatchInsertableEntity(array $data): BatchInsertableInterface
    {
        return new class($data) implements BatchInsertableInterface {
            private array $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function getBatchInsertData(): array
            {
                return $this->data;
            }
        };
    }
}
