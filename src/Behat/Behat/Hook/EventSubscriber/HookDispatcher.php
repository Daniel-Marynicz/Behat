<?php

namespace Behat\Behat\Hook\EventSubscriber;

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Behat\Behat\Callee\Event\ExecuteCalleeEvent;
use Behat\Behat\Event\EventInterface;
use Behat\Behat\Event\HookEvent;
use Behat\Behat\Event\LifecycleEventInterface;
use Behat\Behat\EventDispatcher\DispatchingService;
use Behat\Behat\Hook\Event\HooksCarrierEvent;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hook dispatcher.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class HookDispatcher extends DispatchingService implements EventSubscriberInterface
{
    /**
     * @var Boolean
     */
    private $skip = false;

    /**
     * Initializes dispatcher.
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @param Boolean                  $skip
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, $skip = false)
    {
        parent::__construct($eventDispatcher);

        $this->skip = (bool)$skip;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return array(
            EventInterface::BEFORE_SUITE           => array('dispatchHooks', 10),
            EventInterface::AFTER_SUITE            => array('dispatchHooks', 10),
            EventInterface::BEFORE_FEATURE         => array('dispatchHooks', 10),
            EventInterface::AFTER_FEATURE          => array('dispatchHooks', 10),
            EventInterface::BEFORE_SCENARIO        => array('dispatchHooks', 10),
            EventInterface::AFTER_SCENARIO         => array('dispatchHooks', 10),
            EventInterface::BEFORE_OUTLINE_EXAMPLE => array('dispatchHooks', 10),
            EventInterface::AFTER_OUTLINE_EXAMPLE  => array('dispatchHooks', 10),
            EventInterface::BEFORE_STEP            => array('dispatchHooks', 10),
            EventInterface::AFTER_STEP             => array('dispatchHooks', 10),
        );
    }

    /**
     * Tells dispatcher to skip all hooks.
     *
     * @param Boolean $skip
     */
    public function skipHooks($skip = true)
    {
        $this->skip = (bool)$skip;
    }

    /**
     * Runs hooks with specified name.
     *
     * @param LifecycleEventInterface $event An event to which hooks glued
     *
     * @throws Exception If hook throws one
     */
    public function dispatchHooks(LifecycleEventInterface $event)
    {
        // TODO: skip hooks of failed steps and scenarios
        // TODO: remove skip states from hook and tester dispatchers
        if ($this->skip) {
            return;
        }

        $suite = $event->getSuite();
        $contexts = $event->getContextPool();

        $hooksProvider = new HooksCarrierEvent($suite, $contexts);
        $this->dispatch(EventInterface::LOAD_HOOKS, $hooksProvider);

        foreach ($hooksProvider->getHooksForEvent($event) as $hook) {
            $hookEvent = new HookEvent($event, $hook);
            $this->dispatch(EventInterface::BEFORE_HOOK, $hookEvent);

            $execution = new ExecuteCalleeEvent($suite, $contexts, $hook, array($event));

            try {
                $this->dispatch(EventInterface::EXECUTE_HOOK, $execution);
            } catch (Exception $e) {
                $hookEvent = new HookEvent($event, $hook, $execution->getStdOut(), $e);
                $this->dispatch(EventInterface::AFTER_HOOK, $hookEvent);

                throw $e;
            }

            $hookEvent = new HookEvent($event, $hook, $execution->getStdOut());
            $this->dispatch(EventInterface::AFTER_HOOK, $hookEvent);
        }
    }
}
