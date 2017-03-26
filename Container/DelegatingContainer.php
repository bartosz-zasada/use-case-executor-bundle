<?php

namespace Bamiz\UseCaseExecutorBundle\Container;

use Bamiz\UseCaseExecutor\Container\ItemNotFoundException;
use Bamiz\UseCaseExecutor\Container\ReferenceAcceptingContainerInterface;
use Symfony\Component\DependencyInjection as DI;

/**
 * This container stores names of services in Symfony Container, so that the services don't have to be
 * instantiated when the use cases are collected and added to the container.
 */
class DelegatingContainer implements ReferenceAcceptingContainerInterface
{
    /**
     * @var array
     */
    private $references = [];

    /**
     * @var DI\ContainerInterface
     */
    private $symfonyContainer;

    /**
     * @param DI\ContainerInterface $symfonyContainer
     */
    public function __construct(DI\ContainerInterface $symfonyContainer)
    {
        $this->symfonyContainer = $symfonyContainer;
    }

    /**
     * @inheritdoc
     */
    public function set($name, $item)
    {
        $this->references[$name] = $item;
    }

    /**
     * @inheritdoc
     */
    public function get($name)
    {
        if (!array_key_exists($name, $this->references)) {
            throw new ItemNotFoundException(sprintf('Service "%s" not found.', $name));
        }

        try {
            return $this->symfonyContainer->get($this->references[$name]);
        } catch (DI\Exception\ServiceNotFoundException $e) {
            throw new ItemNotFoundException(
                sprintf('Reference "%s" points to a non-existent service "%s".', $name, $this->references[$name])
            );
        }
    }
}
