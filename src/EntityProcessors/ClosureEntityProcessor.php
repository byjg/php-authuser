<?php

namespace ByJG\Authenticate\EntityProcessors;

use ByJG\MicroOrm\Interface\EntityProcessorInterface;
use Closure;

/**
 * Wrapper class to adapt closures to the EntityProcessorInterface
 */
class ClosureEntityProcessor implements EntityProcessorInterface
{
    private Closure $closure;

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    #[\Override]
    public function process(array $instance): array
    {
        $result = ($this->closure)($instance);

        // If closure returns an object, convert it to array
        if (is_object($result)) {
            return (array) $result;
        }

        return $result;
    }
}
