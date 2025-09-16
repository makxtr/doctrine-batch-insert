<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Validator;

use Doctrine\Common\Collections\Collection;

interface EntityCollectionValidatorInterface
{
    public function validateCollectionBeforeLightInsert(Collection $collection): void;

    public function validateCollection(Collection $collection): void;
}
