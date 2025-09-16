<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Validator;

use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;
use makxtr\DoctrineBatchInsert\BatchInsertableInterface;

class EntityCollectionValidator implements EntityCollectionValidatorInterface
{
    public function validateCollectionBeforeLightInsert(Collection $collection): void
    {
        $this->validateCollection($collection);

        $entity = $collection->first();
        if (!$entity instanceof BatchInsertableInterface) {
            throw new InvalidArgumentException(sprintf(
                'Provided entities are not supporting batch insert. Entity class: "%s"',
                get_class($entity),
            ));
        }
    }

    public function validateCollection(Collection $collection): void
    {
        $types = [];

        foreach ($collection as $entity) {
            $types[] = get_class($entity);
        }

        $uniqueTypes = array_unique($types);

        if (count($uniqueTypes) > 1) {
            throw new InvalidArgumentException('Entity collection has more than one type');
        }
    }
}
