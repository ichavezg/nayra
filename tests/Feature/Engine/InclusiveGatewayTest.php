<?php

namespace Tests\Feature\Engine;

use PHPUnit\Framework\TestCase;
use ProcessMaker\Nayra\Contracts\Bpmn\ActivityInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\EventInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\GatewayInterface;

/**
 * Test transitions
 *
 */
class InclusiveGatewayTest extends EngineTestCase
{

    /**
     * Inclusive Gateway
     *           ┌─────────┐
     *        ┌─→│activityA│─┐
     *  ○─→╱╲─┘  └─────────┘ └─→╱╲
     *     ╲╱─┐  ┌─────────┐ ┌─→╲╱─→●
     *     A  └─→│activityB│─┘  B
     *           └─────────┘
     *
     * @return \ProcessMaker\Nayra\Contracts\Bpmn\ProcessInterface
     */
    private function createProcessWithInclusiveGateway()
    {
        $process = $this->processRepository->createProcessInstance();

        //elements
        $start = $this->eventRepository->createStartEventInstance();
        $gatewayA = $this->gatewayRepository->createInclusiveGatewayInstance();
        $activityA = $this->activityRepository->createActivityInstance();
        $activityB = $this->activityRepository->createActivityInstance();

        $gatewayB = $this->gatewayRepository->createInclusiveGatewayInstance();
        $end = $this->eventRepository->createEndEventInstance();
        $process
            ->addActivity($activityA)
            ->addActivity($activityB);
        $process
            ->addGateway($gatewayA)
            ->addGateway($gatewayB);
        $process
            ->addEvent($start)
            ->addEvent($end);

        //flows
        $start->createFlowTo($gatewayA, $this->flowRepository);
        $gatewayA
            ->createConditionedFlowTo($activityA, function ($data) {
                return $data['A']=='1';
            }, false, $this->flowRepository)
            ->createConditionedFlowTo($activityB, function ($data) {
                return $data['B']=='1';
            }, false, $this->flowRepository);
        $activityA->createFlowTo($gatewayB, $this->flowRepository);
        $activityB->createFlowTo($gatewayB, $this->flowRepository);
        $gatewayB->createFlowTo($end, $this->flowRepository);
        return $process;
    }

    /**
     * Creates a process that has a default transition
     *
     * @return \ProcessMaker\Models\Process
     */
    private function createProcessWithDefaultTransition()
    {
        $process = $this->processRepository->createProcessInstance();

        //elements
        $start = $this->eventRepository->createStartEventInstance();
        $gatewayA = $this->gatewayRepository->createInclusiveGatewayInstance();
        $gatewayB = $this->gatewayRepository->createInclusiveGatewayInstance();
        $gatewayA->name= "A";
        $gatewayB->name= "B";
        $activityA = $this->activityRepository->createActivityInstance();
        $activityB = $this->activityRepository->createActivityInstance();

        $end = $this->eventRepository->createEndEventInstance();
        $process
            ->addActivity($activityA)
            ->addActivity($activityB);
        $process
            ->addGateway($gatewayA)
            ->addGateway($gatewayB);
        $process
            ->addEvent($start)
            ->addEvent($end);

        //flows
        $start->createFlowTo($gatewayA, $this->flowRepository);
        $gatewayA
            ->createConditionedFlowTo($activityA, function ($data) {
                return $data['A']=='1';
            }, false, $this->flowRepository)
            ->createConditionedFlowTo($activityB, function ($data) {
                return true;
            }, true, $this->flowRepository);
        $activityA->createFlowTo($gatewayB, $this->flowRepository);
        $activityB->createFlowTo($gatewayB, $this->flowRepository);
        $gatewayB->createFlowTo($end, $this->flowRepository);
        return $process;
    }

    /**
     * Test a inclusive gateway with two outgoing flows.
     *
     * Test transitions from start event, inclusive gateways, activities and end event,
     * with two activities activated.
     */
    public function testInclusiveGatewayAllPaths()
    {
        //Create a data store with data.
        $dataStore = $this->dataStoreRepository->createDataStoreInstance();
        $dataStore->putData('A', '1');
        $dataStore->putData('B', '1');

        //Load the process
        $process = $this->createProcessWithInclusiveGateway();
        $this->engine->createExecutionInstance($process, $dataStore);

        //Get References
        $start = $process->getEvents()->item(0);
        $activityA = $process->getActivities()->item(0);
        $activityB = $process->getActivities()->item(1);

        //Start the process
        $start->start();

        $this->engine->runToNextState();

        //Assertion: Verify the triggered engine events. Two activities are activated.
        $this->assertEvents([
            EventInterface::EVENT_EVENT_TRIGGERED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_ARRIVES,
            GatewayInterface::EVENT_GATEWAY_ACTIVATED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_CONSUMED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_PASSED,
            ActivityInterface::EVENT_ACTIVITY_ACTIVATED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_PASSED,
            ActivityInterface::EVENT_ACTIVITY_ACTIVATED
        ]);

        //Completes the Activity A
        $tokenA = $activityA->getTokens($dataStore)->item(0);
        $activityA->complete($tokenA);

        //the run to next state should go false when the max steps is reached.
        $this->assertFalse($this->engine->runToNextState(1));

        $this->engine->runToNextState();

        //Assertion: Verify the triggered engine events. The activity is closed.
        $this->assertEvents([
            ActivityInterface::EVENT_ACTIVITY_COMPLETED,
            ActivityInterface::EVENT_ACTIVITY_CLOSED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_ARRIVES,
        ]);

        //Completes the Activity B
        $tokenB = $activityB->getTokens($dataStore)->item(0);
        $activityB->complete($tokenB);
        $this->engine->runToNextState();

        //Assertion: Verify the triggered engine events. The activity is closed and process is ended.
        $this->assertEvents([
            ActivityInterface::EVENT_ACTIVITY_COMPLETED,
            ActivityInterface::EVENT_ACTIVITY_CLOSED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_ARRIVES,
            GatewayInterface::EVENT_GATEWAY_ACTIVATED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_CONSUMED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_CONSUMED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_PASSED,
            EventInterface::EVENT_EVENT_TRIGGERED
        ]);
    }

    /**
     * Test a inclusive gateway with one activated outgoing flow.
     *
     * Test transitions from start event, inclusive gateways, two activities and one end event,
     * with only one activity (B) activated.
     */
    public function testInclusiveGatewayOnlyB()
    {
        //Create a data store with data.
        $dataStore = $this->dataStoreRepository->createDataStoreInstance();
        $dataStore->putData('A', '0');
        $dataStore->putData('B', '1');
        $process = $this->createProcessWithInclusiveGateway();
        $this->engine->createExecutionInstance($process, $dataStore);

        //Get References
        $start = $process->getEvents()->item(0);
        $activityA = $process->getActivities()->item(0);
        $activityB = $process->getActivities()->item(1);

        //Start the process
        $start->start();
        $this->engine->runToNextState();

        //Assertion: Verify the triggered engine events. One activity is activated.
        $this->assertEvents([
            EventInterface::EVENT_EVENT_TRIGGERED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_ARRIVES,
            GatewayInterface::EVENT_GATEWAY_ACTIVATED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_CONSUMED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_PASSED,
            ActivityInterface::EVENT_ACTIVITY_ACTIVATED,
        ]);

        //Completes the Activity B
        $tokenB = $activityB->getTokens($dataStore)->item(0);
        $activityB->complete($tokenB);
        $this->engine->runToNextState();

        //Assertion: Verify the triggered engine events. The activity is closed and process is ended.
        $this->assertEvents([
            ActivityInterface::EVENT_ACTIVITY_COMPLETED,
            ActivityInterface::EVENT_ACTIVITY_CLOSED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_ARRIVES,
            GatewayInterface::EVENT_GATEWAY_ACTIVATED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_CONSUMED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_PASSED,
            EventInterface::EVENT_EVENT_TRIGGERED,
        ]);
    }

    /**
     * Test that a process with a default transition is created and run correctly
     */
    public function testDefaultTransition()
    {
        //Create a data store with data.
        $dataStore = $this->dataStoreRepository->createDataStoreInstance();
        $dataStore->putData('A', '2');
        $process = $this->createProcessWithDefaultTransition();
        $this->engine->createExecutionInstance($process, $dataStore);

        //Get References
        $start = $process->getEvents()->item(0);

        //Start the process
        $start->start();
        $this->engine->runToNextState();

        //Assertion: The correct events of the default transition should be triggered
        $this->assertEvents([
            EventInterface::EVENT_EVENT_TRIGGERED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_ARRIVES,
            GatewayInterface::EVENT_GATEWAY_ACTIVATED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_CONSUMED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_PASSED,
            ActivityInterface::EVENT_ACTIVITY_ACTIVATED
        ]);

        $dataStore->putData('A', '1');
        $process = $this->createProcessWithDefaultTransition();
        $this->engine->createExecutionInstance($process, $dataStore);

        //Get References
        $start = $process->getEvents()->item(0);

        //Start the process
        $start->start();
        $this->engine->runToNextState();
        //Assertion: The correct events of the default transition should be triggered
        $this->assertEvents([
            EventInterface::EVENT_EVENT_TRIGGERED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_ARRIVES,
            GatewayInterface::EVENT_GATEWAY_ACTIVATED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_CONSUMED,
            GatewayInterface::EVENT_GATEWAY_TOKEN_PASSED,
            ActivityInterface::EVENT_ACTIVITY_ACTIVATED
        ]);
    }
}
