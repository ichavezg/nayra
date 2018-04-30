<?php

namespace ProcessMaker\Nayra\Contracts\Bpmn;

use ProcessMaker\Nayra\Contracts\Engine\ExecutionInstanceInterface;

/**
 * Behavior that must implement objects that listen to message notifications
 *
 * @package ProcessMaker\Nayra\Contracts\Bpmn
 */
interface MessageListenerInterface
{
    /**
     * Method to be called when a message event arrives
     *
     * @param mixed $message
     * @return mixed
     */
    public function execute(EventDefinitionInterface $message, ExecutionInstanceInterface $instance);
}
