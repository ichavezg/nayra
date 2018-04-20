<?php

namespace ProcessMaker\Nayra\Bpmn;

use ProcessMaker\Nayra\Contracts\Bpmn\TokenInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\TransitionInterface;
use ProcessMaker\Nayra\Contracts\Engine\ExecutionInstanceInterface;

/**
 * Transition rule for a parallel gateway.
 *
 * @package ProcessMaker\Nayra\Bpmn
 */
class ParallelGatewayTransition implements TransitionInterface
{
    use TransitionTrait;

    /**
     * Initialize the tokens consumed property, the Parallel Gateway consumes
     * exactly one token from each incoming Sequence Flow.
     *
     */
    protected function initParallelGatewayTransition()
    {
        $this->setTokensConsumedPerTransition(-1);
        $this->setTokensConsumedPerIncoming(1);
    }

    /**
     * Always true because the conditions are not defined in the gateway, but for each
     * outgoing flow transition.
     *
     * @return bool
     */
    public function assertCondition(TokenInterface $token, ExecutionInstanceInterface $executionInstance)
    {
        return true;
    }

    /**
     * The Parallel Gateway is activated if there is at least one token on
     * each incoming Sequence Flow.
     *
     * @return bool
     */
    protected function hasAllRequiredTokens()
    {
        $incomingWithToken = $this->incoming()->find(function(Connection $flow){
            return $flow->originState()->getTokens()->count()>0;
        });
        return $incomingWithToken->count() === $this->incoming()->count();
    }
}