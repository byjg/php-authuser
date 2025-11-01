<?php

namespace ByJG\Authenticate\MapperFunctions;

use ByJG\MicroOrm\Interface\MapperFunctionInterface;
use Closure;
use ReflectionException;
use ReflectionFunction;

/**
 * Wrapper class to adapt closures to the MapperFunctionInterface
 * This allows using closures as mapper functions while maintaining type safety
 */
class ClosureMapper implements MapperFunctionInterface
{
    private Closure $closure;

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    /**
     * @throws ReflectionException
     */
    public function processedValue(mixed $value, mixed $instance, mixed $helper = null): mixed
    {
        $reflection = new ReflectionFunction($this->closure);
        $paramCount = $reflection->getNumberOfParameters();

        // Call closure with appropriate number of parameters
        return match($paramCount) {
            1 => ($this->closure)($value),
            2 => ($this->closure)($value, $instance),
            default => ($this->closure)($value, $instance, $helper)
        };
    }
}
