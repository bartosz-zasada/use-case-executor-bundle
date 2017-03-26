<?php

namespace Bamiz\UseCaseExecutorBundle\DependencyInjection;

use Bamiz\UseCaseExecutor\Actor\ActorRecognizerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ActorCompilerPass implements CompilerPassInterface
{
    const COMPOSITE_ACTOR_RECOGNIZER_SERVICE = 'bamiz_use_case.actor_recognizer.composite';

    /**
     * @param ContainerBuilder $container
     *
     * @throws \InvalidArgumentException
     */
    public function process(ContainerBuilder $container)
    {
        $compositeRecognizerDefinition = $container->findDefinition(self::COMPOSITE_ACTOR_RECOGNIZER_SERVICE);
        if (!$compositeRecognizerDefinition) {
            return;
        }

        $serviceIds = array_keys($container->findTaggedServiceIds('use_case_actor_recognizer'));
        foreach ($serviceIds as $serviceId) {
            $definition = $container->findDefinition($serviceId);
            $this->validateActorRecognizer($serviceId, $definition);

            $compositeRecognizerDefinition->addMethodCall('addActorRecognizer', [new Reference($serviceId)]);
        }
    }

    /**
     * @param string     $serviceId
     * @param Definition $serviceDefinition
     *
     * @throws \InvalidArgumentException
     */
    private function validateActorRecognizer($serviceId, Definition $serviceDefinition)
    {
        $interfaces = class_implements($serviceDefinition->getClass());
        if (!is_array($interfaces) || !in_array(ActorRecognizerInterface::class, $interfaces)) {
            throw new \InvalidArgumentException(sprintf(
                'Service "%s" is not a valid Actor Recognizer, as class "%s" does not implement ActorRecognizerInterface.',
                $serviceId, $serviceDefinition->getClass()
            ));
        }
    }
}
