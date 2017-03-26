<?php

namespace Bamiz\UseCaseExecutorBundle\DependencyInjection;

use Bamiz\UseCaseExecutor\UseCase\RequestClassNotFoundException;
use Bamiz\UseCaseExecutorBundle\Annotation\ProcessorAnnotation;
use Bamiz\UseCaseExecutorBundle\Annotation\UseCase as UseCaseAnnotation;
use Bamiz\UseCaseExecutor\Container\ReferenceAcceptingContainerInterface;
use Bamiz\UseCaseExecutor\UseCase\RequestResolver;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class UseCaseCompilerPass implements CompilerPassInterface
{
    /**
     * @var AnnotationReader
     */
    private $annotationReader;

    /**
     * @var RequestResolver
     */
    private $requestResolver;

    /**
     * @param AnnotationReader $annotationReader
     * @param RequestResolver  $requestResolver
     */
    public function __construct(AnnotationReader $annotationReader = null, RequestResolver $requestResolver = null)
    {
        $this->annotationReader = $annotationReader ?: new AnnotationReader();
        $this->requestResolver = $requestResolver ?: new RequestResolver();
    }

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('bamiz_use_case.executor')) {
            return;
        }

        $this->addInputProcessorsToContainer($container);
        $this->addResponseProcessorsToContainer($container);
        $this->addUseCasesToContainer($container);
        $this->addContextsToResolver($container);
    }

    /**
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     */
    private function addUseCasesToContainer(ContainerBuilder $container)
    {
        $services = $container->getDefinitions();

        foreach ($services as $id => $serviceDefinition) {
            $serviceClass = $serviceDefinition->getClass();
            if (!class_exists($serviceClass)) {
                continue;
            }

            $useCaseReflection = new \ReflectionClass($serviceClass);
            $useCaseTags = $serviceDefinition->getTag('use_case');

            if ($this->validateTags($useCaseTags, $serviceClass)) {
                $this->registerTaggedUseCase($container, $useCaseReflection, $id, $serviceClass, $useCaseTags[0]);
            } else {
                $this->registerAnnotatedUseCase($container, $useCaseReflection, $id, $serviceClass);
            }
        }
    }

    /**
     * @param ContainerBuilder $containerBuilder
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    private function addInputProcessorsToContainer(ContainerBuilder $containerBuilder)
    {
        $processorContainerDefinition = $containerBuilder->findDefinition('bamiz_use_case.container.input_processor');
        $inputProcessors = $containerBuilder->findTaggedServiceIds('use_case_input_processor');
        /**
         * @var string $id
         * @var array  $tags
         */
        foreach ($inputProcessors as $id => $tags) {
            foreach ($tags as $attributes) {
                if ($this->containerAcceptsReferences($processorContainerDefinition)) {
                    $processorContainerDefinition->addMethodCall('set', [$attributes['alias'], $id]);
                } else {
                    $processorContainerDefinition->addMethodCall('set', [$attributes['alias'], new Reference($id)]);
                }
            }
        }
    }

    /**
     * @param ContainerBuilder $containerBuilder
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    private function addResponseProcessorsToContainer(ContainerBuilder $containerBuilder)
    {
        $processorContainerDefinition = $containerBuilder->findDefinition(
            'bamiz_use_case.container.response_processor'
        );
        $responseProcessors = $containerBuilder->findTaggedServiceIds('use_case_response_processor');

        foreach ($responseProcessors as $id => $tags) {
            foreach ($tags as $attributes) {
                if ($this->containerAcceptsReferences($processorContainerDefinition)) {
                    $processorContainerDefinition->addMethodCall('set', [$attributes['alias'], $id]);
                } else {
                    $processorContainerDefinition->addMethodCall('set', [$attributes['alias'], new Reference($id)]);
                }
            }
        }
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    private function addContextsToResolver(ContainerBuilder $containerBuilder)
    {
        $resolverDefinition = $containerBuilder->findDefinition('bamiz_use_case.context_resolver');
        $defaultContextName = $containerBuilder->getParameter('bamiz_use_case.default_context');
        $contexts = (array)$containerBuilder->getParameter('bamiz_use_case.contexts');

        $resolverDefinition->addMethodCall('setDefaultContextName', [$defaultContextName, []]);
        foreach ($contexts as $name => $contextConfiguration) {
            $resolverDefinition->addMethodCall('addContextDefinition', [$name, $contextConfiguration]);
        }
    }

    /**
     * @param \ReflectionClass $useCaseReflection
     *
     * @throws InvalidUseCase
     */
    private function validateUseCase($useCaseReflection)
    {
        if (!$useCaseReflection->hasMethod('execute')) {
            throw new InvalidUseCase(sprintf(
                'Class "%s" has been annotated as a Use Case, but does not contain execute() method.',
                $useCaseReflection->getName()
            ));
        }
    }

    /**
     * @param array  $annotations
     * @param string $serviceClass
     *
     * @throws \InvalidArgumentException
     */
    private function validateAnnotations($annotations, $serviceClass)
    {
        $useCaseAnnotationCount = 0;
        foreach ($annotations as $annotation) {
            if ($annotation instanceof UseCaseAnnotation) {
                $useCaseAnnotationCount++;
            }
        }
        
        if ($useCaseAnnotationCount > 1) {
            throw new \InvalidArgumentException(sprintf(
                'It is not possible for a class to be more than one Use Case. ' .
                'Please remove the excessive @UseCase annotations from class %s',
                $serviceClass
            ));
        }
    }

    /**
     * @param string            $serviceId
     * @param string            $serviceClass
     * @param string            $useCaseName
     * @param array             $annotations
     * @param Definition        $resolverDefinition
     * @param Definition        $containerDefinition
     *
     * @throws RequestClassNotFoundException
     */
    private function registerUseCase(
        $serviceId,
        $serviceClass,
        $useCaseName,
        $annotations,
        $resolverDefinition,
        $containerDefinition
    ) {
        $configuration = [
            'use_case'      => $useCaseName ?: $this->fqnToUseCaseName($serviceClass),
            'request_class' => $this->requestResolver->resolve($serviceClass)
        ];

        foreach ($annotations as $annotation) {
            if ($annotation instanceof ProcessorAnnotation) {
                $configuration[$annotation->getType()][$annotation->getName()] = $annotation->getOptions();
            }
        }

        $this->addUseCaseToUseCaseContainer($containerDefinition, $configuration['use_case'], $serviceId);
        $resolverDefinition->addMethodCall('addUseCaseConfiguration', [$configuration]);
    }

    /**
     * @param Definition $containerDefinition
     *
     * @return bool
     */
    private function containerAcceptsReferences($containerDefinition)
    {
        $interfaces = class_implements($containerDefinition->getClass());
        if (is_array($interfaces)) {
            return in_array(ReferenceAcceptingContainerInterface::class, $interfaces);
        } else {
            return false;
        }
    }

    /**
     * @param string $fqn
     *
     * @return string
     */
    private function fqnToUseCaseName($fqn)
    {
        $unqualifiedName = substr($fqn, strrpos($fqn, '\\') + 1);
        return ltrim(strtolower(preg_replace('/[A-Z0-9]/', '_$0', $unqualifiedName)), '_');
    }

    /**
     * @param Definition $containerDefinition
     * @param string     $useCaseName
     * @param string     $serviceId
     */
    private function addUseCaseToUseCaseContainer($containerDefinition, $useCaseName, $serviceId)
    {
        if ($this->containerAcceptsReferences($containerDefinition)) {
            $containerDefinition->addMethodCall('set', [$useCaseName, $serviceId]);
        } else {
            $containerDefinition->addMethodCall('set', [$useCaseName, new Reference($serviceId)]);
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param \ReflectionClass $useCaseReflection
     * @param string           $id
     * @param string           $serviceClass
     * @param array            $useCaseTag
     */
    private function registerTaggedUseCase(
        ContainerBuilder $container,
        $useCaseReflection,
        $id,
        $serviceClass,
        array $useCaseTag
    ) {
        $resolverDefinition = $container->findDefinition('bamiz_use_case.context_resolver');
        $useCaseContainerDefinition = $container->findDefinition('bamiz_use_case.container.use_case');

        $this->validateUseCase($useCaseReflection);
        $this->registerUseCase(
            $id,
            $serviceClass,
            isset($useCaseTag['alias']) ? $useCaseTag['alias'] : '',
            [],
            $resolverDefinition,
            $useCaseContainerDefinition
        );
    }

    /**
     * @param ContainerBuilder $container
     * @param \ReflectionClass $useCaseReflection
     * @param string           $id
     * @param string           $serviceClass
     */
    private function registerAnnotatedUseCase(ContainerBuilder $container, $useCaseReflection, $id, $serviceClass)
    {
        $resolverDefinition = $container->findDefinition('bamiz_use_case.context_resolver');
        $useCaseContainerDefinition = $container->findDefinition('bamiz_use_case.container.use_case');

        try {
            $annotations = $this->annotationReader->getClassAnnotations($useCaseReflection);
        } catch (\InvalidArgumentException $e) {
            throw new \LogicException(
                sprintf('Could not load annotations for class %s: %s', $serviceClass, $e->getMessage())
            );
        }

        foreach ($annotations as $annotation) {
            if ($annotation instanceof UseCaseAnnotation) {
                $this->validateUseCase($useCaseReflection);
                $this->validateAnnotations($annotations, $serviceClass);
                $this->registerUseCase(
                    $id,
                    $serviceClass,
                    $annotation->getName(),
                    $annotations,
                    $resolverDefinition,
                    $useCaseContainerDefinition
                );
            }
        }
    }

    /**
     * @param array  $useCaseTags
     * @param string $serviceClass
     *
     * @return bool
     */
    private function validateTags($useCaseTags, $serviceClass)
    {
        switch (count($useCaseTags)) {
            case 1:
                return true;
            case 0:
                return false;
            default:
                throw new \InvalidArgumentException(sprintf(
                    'It is not possible for a class to be more than one Use Case. ' .
                    'Please remove the excessive use_case tags from class %s',
                    $serviceClass
                ));
        }
    }
}
