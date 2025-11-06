<?php

namespace Tests\Fixture;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Interface\MapperFunctionInterface;
use Override;

class TestUniqueIdGenerator implements MapperFunctionInterface
{
    private string $prefix;

    public function __construct(string $prefix = 'TEST-')
    {
        $this->prefix = $prefix;
    }

    #[Override]
    public function processedValue(mixed $value, mixed $instance, ?DatabaseExecutor $executor = null): mixed
    {
        return $this->prefix . uniqid();
    }
}
