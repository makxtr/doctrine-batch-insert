<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert;

interface BatchInsertableInterface
{
    public function getBatchInsertData(): array;
}
