<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Util;

class StringHelper
{
    public static function prepareStringValueForInsert(string $string): string
    {
        return str_replace("'", "''", $string);
    }
}
