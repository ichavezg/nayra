<?php

namespace ProcessMaker\Nayra\Bpmn;

use ProcessMaker\Nayra\Contracts\Bpmn\ActivityInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\CancelInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\TokenInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\TransitionInterface;
use ProcessMaker\Nayra\Contracts\Engine\ExecutionInstanceInterface;

/**
 * Transition rule to cancel an activity.
 *
 * @package ProcessMaker\Nayra\Bpmn
 */
class CancelActivityTransition implements TransitionInterface
{
    use TransitionTrait;

    /**
     * Initialize transition.
     *
     */
    protected function initActivityTransition()
    {
        $this->setPreserveToken(true);
    }

    /**
     * Condition required to transit the element.
     *
     * @param TokenInterface|null $token
     * @param ExecutionInstanceInterface|null $executionInstance
     *
     * @return bool
     */
    public function assertCondition(TokenInterface $token = null, ExecutionInstanceInterface $executionInstance = null)
    {
        return $token->getStatus() === ActivityInterface::TOKEN_STATE_CLOSED;
    }

    protected function onTokenTransit(TokenInterface $token)
    {
        $token->setProperty(TokenInterface::BPMN_PROPERTY_EVENT_TYPE, CancelInterface::class);
    }
}
