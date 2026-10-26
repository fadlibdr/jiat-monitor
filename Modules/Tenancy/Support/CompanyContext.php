<?php

namespace Modules\Tenancy\Support;

/**
 * Active company for the current request/job. Held in a container-scoped instance,
 * not a static, so a long-lived worker cannot carry company A into a job for B.
 */
class CompanyContext
{
    private ?int $id = null;

    public static function instance(): self
    {
        return app(self::class);
    }

    public static function id(): ?int
    {
        return self::instance()->id;
    }

    public static function set(?int $id): void
    {
        self::instance()->id = $id;
    }

    public static function clear(): void
    {
        self::instance()->id = null;
    }

    /** Run $fn inside a company context and always restore the previous one. */
    public static function run(int $companyId, callable $fn): mixed
    {
        $previous = self::id();
        self::set($companyId);
        try {
            return $fn();
        } finally {
            self::set($previous);
        }
    }
}
