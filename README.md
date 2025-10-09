# Doctrine Batch Insert Bundle

Batch insert utility for [Doctrine ORM](https://www.doctrine-project.org/)

## Installation

Since this package is hosted in a private GitLab repository, you'll need to add it to your project manually.

**** 1. Add the repository to your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": ""
    }
  ],
  "require": {
    "makxtr/doctrine-batch-insert": "dev-master"
  }
}
```

### 2. Install via Composer:

```bash
composer require makxtr/doctrine-batch-insert
```

### 3. Symfony Integration

The bundle automatically registers itself. All services are available through dependency injection:

```php
// In your controller or service
public function __construct(
    private BatchInsertServiceInterface $batchInsertService
) {}
```

## Basic Setup

### 1. Implement BatchInsertableInterface

Your entities must implement the `BatchInsertableInterface`(only for light versions):

```php
<?php

use makxtr\DoctrineBatchInsert\BatchInsertableInterface;

class User implements BatchInsertableInterface
{
    private ?int $id = null;
    private string $name;
    private string $email;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getBatchInsertData(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->createdAt,
        ];
    }

    // getters and setters...
}
```
[See about key generation](#primary-key-generation)

[About relations](#related-entities)

### 2. Use in Symfony Controller/Service

```php
<?php

namespace App\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use makxtr\DoctrineBatchInsert\BatchInsertServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class DataImportController
{
    public function __construct(
        private BatchInsertServiceInterface $batchInsertService
    ) {}

    public function importUsers(): JsonResponse
    {
        $users = new ArrayCollection([
            new User('John Doe', 'john@example.com'),
            new User('Jane Smith', 'jane@example.com'),
        ]);

        $this->batchInsertService->lightBatchInsert($users);

        return new JsonResponse(['status' => 'success']);
    }
}
```

## Methods Overview

### `lightBatchInsert()`

**Use when**: Simple entities without relations, maximum performance needed, no need for inserted IDs.

```php
$users = new ArrayCollection([
    new User('John Doe', 'john@example.com'),
    new User('Jane Smith', 'jane@example.com'),
]);

$batchInsertService->lightBatchInsert($users);
```

### `lightBatchInsertWithResult()`

**Use when**: Need data from inserted records (e.g., IDs), simple entities without complex relations.

```php
// Return all fields
$results = $batchInsertService->lightBatchInsertWithResult($users);

// Return specific fields only
$results = $batchInsertService->lightBatchInsertWithResult($users, ['id', 'email']);

foreach ($results as $result) {
    echo "Inserted user with ID: " . $result['id'] . "\n";
}
```

### `batchInsert()`

**Use when**: Entities with relations, need conflict resolution strategies, require precise control.

```php
use makxtr\DoctrineBatchInsert\DTO\Request\BatchInsertRequest;

$request = new BatchInsertRequest(
    entityCollection: $users,
    withRelations: true,
    updateStrategy: $updateStrategy, // optional
    conflictFields: ['email'],
    updateFields: ['name'],
);

$insertedCollection = $batchInsertService->batchInsert($request);
```

### `batchInsertWithResult()`

**Use when**: All features of `batchInsert()` + need insertion results.

```php
$request = new BatchInsertRequest(
    entityCollection: $users,
    withRelations: true,
    returnFields: ['id', 'name', 'email'],
);

$results = $batchInsertService->batchInsertWithResult($request);
```

## Conflict Resolution Strategies

### DefaultStrategy
Standard INSERT without special duplicate handling.

```php
use makxtr\DoctrineBatchInsert\UpdateStrategy\DefaultStrategy;

$request = new BatchInsertRequest(
    entityCollection: $users,
    updateStrategy: new DefaultStrategy()
);
```

### IgnoreUpdate
Ignores records that conflict with existing data.

```php
use makxtr\DoctrineBatchInsert\UpdateStrategy\IgnoreUpdate;

$request = new BatchInsertRequest(
    entityCollection: $users,
    updateStrategy: new IgnoreUpdate(),
    conflictFields: ['email'] // conflict detection fields
);
```

### OnDuplicateKeyUpdate
Updates existing records when conflicts are detected.

```php
use makxtr\DoctrineBatchInsert\UpdateStrategy\OnDuplicateKeyUpdate;

$request = new BatchInsertRequest(
    entityCollection: $users,
    updateStrategy: new OnDuplicateKeyUpdate(),
    conflictFields: ['email'],
    updateFields: ['name', 'updated_at'], // fields to update
    mergeFields: ['login_count'] // fields to merge (sum/append)
);
```

### Replace
Completely replaces existing records with new data.

```php
use makxtr\DoctrineBatchInsert\UpdateStrategy\Replace;

$request = new BatchInsertRequest(
    entityCollection: $users,
    updateStrategy: new Replace()
);
```

## Database Support

### MySQL
### PostgreSQL


## Primary Key Generation

### Auto Increment (Identity)
```php
class User implements BatchInsertableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getBatchInsertData(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
        // ID excluded - will be generated automatically
    }
}
```

### UUID
```php
use Symfony\Component\Uid\Uuid;

class Product implements BatchInsertableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    public function getBatchInsertData(): array
    {
        return [
            'id' => $this->id, // UUID generated automatically if null
            'name' => $this->name,
            'price' => $this->price,
        ];
    }
}
```


## Related Entities

The library automatically handles `ManyToOne` and `OneToOne` relationships:

```php
class Order implements BatchInsertableInterface
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private User $user;
    
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    private Product $product;

    public function getBatchInsertData(): array
    {
        return [
            'quantity' => $this->quantity,
            'price' => $this->price,
            'created_at' => $this->createdAt,
            // user_id and product_id extracted automatically from related objects
        ];
    }
}

$orders = new ArrayCollection([
    new Order($user1, $product1, 2, 100.00),
    new Order($user2, $product2, 1, 50.00),
]);

$request = new BatchInsertRequest(
    entityCollection: $orders,
    withRelations: true
);

$batchInsertService->batchInsert($request);
```

## Advanced Configuration

### BatchInsertRequest Parameters

```php
$request = new BatchInsertRequest(
    entityCollection: $entities,        // Collection of entities to insert
    returnFields: ['id', 'name'],       // Fields to return in result
    updateFields: ['name', 'email'],    // Fields to update on conflict
    mergeFields: ['view_count'],        // Fields to merge (sum/append values)
    conflictFields: ['email'],          // Fields for conflict detection
    withRelations: true,                // Process related entities
    updateStrategy: new OnDuplicateKeyUpdate() // Conflict resolution strategy
);
```

## Migration from Regular Doctrine

### Before:
```php
public function createUsers(array $userData): void
{
    foreach ($userData as $data) {
        $user = new User($data['name'], $data['email']);
        $this->entityManager->persist($user);
    }
    $this->entityManager->flush();
}
```

### After:
```php
public function createUsers(array $userData): void
{
    $users = new ArrayCollection();
    foreach ($userData as $data) {
        $users->add(new User($data['name'], $data['email']));
    }
    
    $this->batchInsertService->lightBatchInsert($users);
}
```

## Test example:
Change entities for you project

When adding/removing a field from an entity you don't forget to update the getBatchInsertData method

```php
class BatchInsertableTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');
    }

    #[DataProvider('getEntitiesDataProvider')]
    public function testBatchInsertableEntities(BatchInsertableInterface $entity): void
    {
        $classMetadataFactory = $this->entityManager->getMetadataFactory();
        $classMetadata = $classMetadataFactory->getMetadataFor(get_class($entity));

        $expectedFields = $this->obtainFields($classMetadata);
        sort($expectedFields);

        $providedFields = array_keys($entity->getBatchInsertData());
        sort($providedFields);

        $this->assertEquals($expectedFields, $providedFields);
    }

    public static function getEntitiesDataProvider(): array
    {
        $message = (new Message(
            'test',
            1,
            1,
            1,
            null,
            null
        ))
            ->setId(1);

        return [
            'notification channel' => [
                (new RecipientNotificationChannel(1, 1))
                    ->setMessageId(1)
                    ->setVersionTemplateId(1)
                    ->setCategory(1),
            ],
            'personal account channel' => [
                (new RecipientPersonalAccountChannel(1, 1))
                    ->setMessageId(1)
                    ->setVersionTemplateId(1),
            ],
            'email channel' => [
                (new RecipientEmailChannel(1, 1))
                    ->setMessageId(1)
                    ->setVersionTemplateId(1),
            ],
            'push channel' => [
                (new RecipientPushChannel(1, 1))
                    ->setMessageId(1)
                    ->setVersionTemplateId(1),
            ],
            'recipient email' => [
                (new RecipientEmail(1, 'test@gmail.com'))
                    ->setMessage($message)
                    ->setPartnerSupportEmail('test@gmail.com')
                    ->setLanguage('ua')
                    ->setVariable(['test' => 1]),
            ],
            'recipient push' => [
                (new RecipientPush('UA', 1))->setMessage($message),
            ],
        ];
    }

    private function obtainFields(ClassMetadata $classMetadata): array
    {
        $directFields = array_keys($classMetadata->fieldNames);

        if ($classMetadata->usesIdGenerator()) {
            unset($directFields[0]);
        }

        $relationFields = [];

        foreach ($classMetadata->associationMappings as $associationMapping) {
            if ($this->shouldSkipAssociation($associationMapping)) {
                continue;
            }

            $sourceToTargetKeyColumn = array_key_first($associationMapping['sourceToTargetKeyColumns']);
            $relationFields[] = $sourceToTargetKeyColumn;
        }

        return array_merge($directFields, $relationFields);
    }

    private function shouldSkipAssociation(array $associationMapping): bool
    {
        $allowedTypes = [
            ClassMetadataInfo::MANY_TO_ONE,
            ClassMetadataInfo::ONE_TO_ONE,
        ];

        return
            !in_array($associationMapping['type'], $allowedTypes, true) ||
            !$associationMapping['isOwningSide'];
    }
}
```


## Conclusion

The Doctrine Batch Insert Bundle provides a powerful and flexible tool for bulk database operations. Choose the appropriate method based on your needs:

- For simple cases, use `lightBatchInsert()`
- For complex scenarios with relations, use `batchInsert()`
- When you need results, use methods with `WithResult` suffix

This achieves optimal balance between performance and functionality in your application.
