<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class PlatformContext
{
    public const array MAP = [
        'mysql' => MysqlVisitor::class,
        'postgresql' => PostgresVisitor::class,
    ];

    public static function getPlatformVisitor(AbstractPlatform $platform): PlatformVisitor
    {
        $driverClassName = self::MAP[$platform->getName()];

        return new $driverClassName();
    }
}
