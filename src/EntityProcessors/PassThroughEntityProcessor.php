<?php

namespace ByJG\Authenticate\EntityProcessors;

use ByJG\MicroOrm\Interface\EntityProcessorInterface;

/**
 * Default entity processor that passes through the instance without modification
 */
class PassThroughEntityProcessor implements EntityProcessorInterface
{
    public function process(array $instance): array
    {
        return $instance;
    }
}
