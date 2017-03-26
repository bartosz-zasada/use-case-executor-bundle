<?php

namespace spec\Bamiz\UseCaseExecutorBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use Bamiz\UseCaseExecutorBundle\Annotation\UseCase as UseCaseAnnotation;
use Bamiz\UseCaseExecutorBundle\Annotation\InputProcessor as InputAnnotation;
use Bamiz\UseCaseExecutorBundle\Annotation\ResponseProcessor as ResponseAnnotation;
use Bamiz\UseCaseExecutor\Container\Container;
use Bamiz\UseCaseExecutor\Container\ReferenceAcceptingContainerInterface;
use Bamiz\UseCaseExecutorBundle\DependencyInjection\InvalidUseCase;
use Bamiz\UseCaseExecutor\UseCase\RequestResolver;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Bamiz\UseCaseExecutorBundle\DependencyInjection\UseCaseCompilerPass;

class UseCaseCompilerPassSpec extends ObjectBehavior
{
    public function let(
        AnnotationReader $annotationReader,
        RequestResolver $requestResolver,
        ContainerBuilder $containerBuilder,
        Definition $useCaseExecutorDefinition,
        Definition $useCaseContainerDefinition,
        Definition $inputProcessorContainerDefinition,
        Definition $responseProcessorContainerDefinition,
        Definition $contextResolverDefinition
    )
    {
        $this->beConstructedWith($annotationReader, $requestResolver);

        $containerBuilder->findDefinition('bamiz_use_case.executor')->willReturn($useCaseExecutorDefinition);
        $containerBuilder->findDefinition('bamiz_use_case.context_resolver')->willReturn($contextResolverDefinition);
        $containerBuilder->findDefinition('bamiz_use_case.container.use_case')->willReturn($useCaseContainerDefinition);
        $containerBuilder->findDefinition('bamiz_use_case.container.input_processor')->willReturn($inputProcessorContainerDefinition);
        $containerBuilder->findDefinition('bamiz_use_case.container.response_processor')->willReturn($responseProcessorContainerDefinition);
        $containerBuilder->getParameter('bamiz_use_case.default_context')->willReturn('default');
        $containerBuilder->getParameter('bamiz_use_case.contexts')->willReturn([]);
        $containerBuilder->has('bamiz_use_case.executor')->willReturn(true);
        $useCaseContainerDefinition->getClass()->willReturn(Container::class);
        $inputProcessorContainerDefinition->getClass()->willReturn(Container::class);
        $responseProcessorContainerDefinition->getClass()->willReturn(Container::class);

        $containerBuilder->findTaggedServiceIds(Argument::any())->willReturn([]);
        $containerBuilder->getDefinitions()->willReturn([]);
        $useCaseExecutorDefinition->addMethodCall(Argument::cetera())->willReturn();
        $requestResolver->resolve(Argument::any())->willReturn(\stdClass::class);
        $contextResolverDefinition
            ->addMethodCall('addUseCaseConfiguration', [['request_class' => \stdClass::class, 'use_case' => 'use_case_1']])
            ->willReturn();
        $contextResolverDefinition
            ->addMethodCall('setDefaultContextName', ['default', []])
            ->willReturn();
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(UseCaseCompilerPass::class);
    }

    public function it_does_nothing_if_use_case_executor_is_not_registered(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->has('bamiz_use_case.executor')->willReturn(false);
        $containerBuilder->findDefinition('bamiz_use_case.executor')->shouldNotBeCalled();
        $containerBuilder->findTaggedServiceIds('use_case')->shouldNotBeCalled();
        $this->process($containerBuilder);
    }

    public function it_adds_annotated_services_to_the_use_case_container(
        ContainerBuilder $containerBuilder,
        AnnotationReader $annotationReader,
        UseCaseAnnotation $useCaseAnnotation,
        Definition $useCaseContainerDefinition,
        Definition $contextResolverDefinition
    )
    {
        $useCaseAnnotation->getName()->willReturn('use_case_1');
        $inputAnnotation = new InputAnnotation(['value' => 'form']);
        $responseAnnotation = new ResponseAnnotation(['value' => 'twig']);
        $configurationArray = [
            'request_class' => \stdClass::class,
            'use_case'      => 'use_case_1',
            'input'         => ['form' => []],
            'response'      => ['twig' => []]
        ];

        $containerBuilder->getDefinitions()->willReturn(['uc1' => new Definition(UseCase1::class)]);
        $annotationReader
            ->getClassAnnotations(new \ReflectionClass(UseCase1::class))
            ->willReturn([$useCaseAnnotation, $inputAnnotation, $responseAnnotation]);

        $useCaseContainerDefinition->addMethodCall('set', ['use_case_1', new Reference('uc1')])->shouldBeCalled();
        $contextResolverDefinition->addMethodCall('addUseCaseConfiguration', [$configurationArray])->shouldBeCalled();

        $this->process($containerBuilder);
    }

    public function it_registers_tagged_use_cases(
        ContainerBuilder $containerBuilder,
        Definition $useCaseContainerDefinition,
        RequestResolver $requestResolver,
        Definition $contextResolverDefinition,
        Definition $useCaseDefinition1,
        Definition $useCaseDefinition2
    )
    {
        $useCaseDefinition1->getClass()->willReturn(UseCase1::class);
        $useCaseDefinition1->getTag('use_case')->willReturn([['alias' => 'perform_magic']]);
        $useCaseDefinition2->getClass()->willReturn(UseCase2::class);
        $useCaseDefinition2->getTag('use_case')->willReturn([['alias' => 'summon_unicorns']]);

        $containerBuilder->getDefinitions()->willReturn([
            'use_case_1' => $useCaseDefinition1,
            'use_case_2' => $useCaseDefinition2
        ]);

        $requestResolver->resolve(UseCase1::class)->willReturn(\stdClass::class);
        $requestResolver->resolve(UseCase2::class)->willReturn(\stdClass::class);

        $configurationArray1 = [
            'use_case'      => 'perform_magic',
            'request_class' => \stdClass::class
        ];
        $configurationArray2 = [
            'use_case'      => 'summon_unicorns',
            'request_class' => \stdClass::class
        ];

        $useCaseContainerDefinition->addMethodCall('set', ['perform_magic', new Reference('use_case_1')])->shouldBeCalled();
        $useCaseContainerDefinition->addMethodCall('set', ['summon_unicorns', new Reference('use_case_2')])->shouldBeCalled();

        $contextResolverDefinition->addMethodCall('addUseCaseConfiguration', [$configurationArray1])->shouldBeCalled();
        $contextResolverDefinition->addMethodCall('addUseCaseConfiguration', [$configurationArray2])->shouldBeCalled();

        $this->process($containerBuilder);
    }

    public function it_ignores_annotations_of_tagged_use_cases(
        ContainerBuilder $containerBuilder,
        Definition $useCaseContainerDefinition,
        RequestResolver $requestResolver,
        Definition $contextResolverDefinition,
        Definition $useCaseDefinition,
        AnnotationReader $annotationReader
    )
    {
        $useCaseDefinition->getClass()->willReturn(UseCase1::class);
        $useCaseDefinition->getTag('use_case')->willReturn([['alias' => 'perform_magic']]);

        $containerBuilder->getDefinitions()->willReturn(['use_case_1' => $useCaseDefinition]);
        $requestResolver->resolve(UseCase1::class)->willReturn(\stdClass::class);

        $configurationArray = [
            'use_case'      => 'perform_magic',
            'request_class' => \stdClass::class
        ];
        $useCaseContainerDefinition->addMethodCall('set', ['perform_magic', new Reference('use_case_1')])->shouldBeCalled();
        $contextResolverDefinition->addMethodCall('addUseCaseConfiguration', [$configurationArray])->shouldBeCalled();

        $annotationReader->getClassAnnotations(Argument::any())->shouldNotBeCalled();

        $this->process($containerBuilder);
    }

    public function it_throws_an_exception_if_more_than_one_use_case_tag_is_found(
        ContainerBuilder $containerBuilder,
        Definition $useCaseDefinition
    )
    {
        $useCaseDefinition->getClass()->willReturn(UseCase1::class);
        $useCaseDefinition->getTag('use_case')->willReturn([
            ['alias' => 'perform_magic'],
            ['alias' => 'perform_more_magic']
        ]);

        $containerBuilder->getDefinitions()->willReturn(['use_case_1' => $useCaseDefinition]);

        $this->shouldThrow(\InvalidArgumentException::class)->duringProcess($containerBuilder);
    }

    public function it_adds_input_processors_to_container_under_an_alias(
        ContainerBuilder $containerBuilder,
        Definition $inputProcessorContainerDefinition
    )
    {
        $inputProcessorsWithTags = [
            'input_processor_1' => [['alias' => 'foo']],
            'input_processor_2' => [['alias' => 'bar']]
        ];
        $containerBuilder->findTaggedServiceIds('use_case_input_processor')->willReturn($inputProcessorsWithTags);

        $inputProcessorContainerDefinition
            ->addMethodCall('set', ['foo', new Reference('input_processor_1')])
            ->shouldBeCalled();
        $inputProcessorContainerDefinition
            ->addMethodCall('set', ['bar', new Reference('input_processor_2')])
            ->shouldBeCalled();

        $this->process($containerBuilder);
    }

    public function it_adds_response_processors_to_container_under_an_alias(
        ContainerBuilder $containerBuilder,
        Definition $responseProcessorContainerDefinition
    )
    {
        $responseProcessorsWithTags = [
            'response_processor_1' => [['alias' => 'faz']],
            'response_processor_2' => [['alias' => 'baz']]
        ];
        $containerBuilder->findTaggedServiceIds('use_case_response_processor')->willReturn($responseProcessorsWithTags);

        $responseProcessorContainerDefinition
            ->addMethodCall('set', ['faz', new Reference('response_processor_1')])
            ->shouldBeCalled();
        $responseProcessorContainerDefinition
            ->addMethodCall('set', ['baz', new Reference('response_processor_2')])
            ->shouldBeCalled();

        $this->process($containerBuilder);
    }

    public function it_uses_resolves_the_request_class_name_using_request_resolver(
        ContainerBuilder $containerBuilder,
        AnnotationReader $annotationReader,
        RequestResolver $requestResolver,
        Definition $useCaseContainerDefinition,
        UseCaseAnnotation $useCaseAnnotation,
        Definition $contextResolverDefinition
    )
    {
        $useCaseAnnotation->getName()->willReturn('use_case_1');
        $configurationArray = ['request_class' => 'UseCase1Request', 'use_case' => 'use_case_1'];

        $containerBuilder->getDefinitions()->willReturn(['uc1' => new Definition(UseCase1::class)]);
        $annotationReader->getClassAnnotations(new \ReflectionClass(UseCase1::class))->willReturn([$useCaseAnnotation]);
        $requestResolver->resolve(UseCase1::class)->willReturn('UseCase1Request');

        $useCaseContainerDefinition->addMethodCall('set', ['use_case_1', new Reference('uc1')])->shouldBeCalled();
        $contextResolverDefinition->addMethodCall('addUseCaseConfiguration', [$configurationArray])->shouldBeCalled();

        $this->process($containerBuilder);
    }

    public function it_throws_an_exception_when_an_annotated_class_does_not_contain_execute_method(
        ContainerBuilder $containerBuilder,
        AnnotationReader $annotationReader
    )
    {
        $useCaseAnnotation = new UseCaseAnnotation(['value' => 'use_case']);
        $containerBuilder->getDefinitions()->willReturn([
            'not_a_use_case' => new Definition(NotAUseCase::class)
        ]);
        $annotationReader->getClassAnnotations(new \ReflectionClass(NotAUseCase::class))->willReturn([$useCaseAnnotation]);

        $this->shouldThrow(InvalidUseCase::class)->duringProcess($containerBuilder);
    }

    public function it_adds_service_names_instead_of_references_to_container_that_accepts_references(
        AnnotationReader $annotationReader,
        ContainerBuilder $containerBuilder,
        Definition $useCaseContainerDefinition,
        Definition $inputProcessorContainerDefinition,
        Definition $responseProcessorContainerDefinition
    )
    {
        $useCaseContainerDefinition->getClass()->willReturn(ContainerThatAcceptsReferences::class);
        $inputProcessorContainerDefinition->getClass()->willReturn(ContainerThatAcceptsReferences::class);
        $responseProcessorContainerDefinition->getClass()->willReturn(ContainerThatAcceptsReferences::class);

        $containerBuilder->getDefinitions()->willReturn(['service.use_case_1' => new Definition(UseCase1::class)]);
        $useCaseAnnotation = new UseCaseAnnotation(['value' => 'use_case_1']);
        $annotationReader->getClassAnnotations(new \ReflectionClass(UseCase1::class))->willReturn([$useCaseAnnotation]);

        $inputProcessorsWithTags = ['service.input_processor' => [['alias' => 'input']]];
        $responseProcessorsWithTags = ['service.response_processor' => [['alias' => 'response']]];
        $containerBuilder->findTaggedServiceIds('use_case_input_processor')->willReturn($inputProcessorsWithTags);
        $containerBuilder->findTaggedServiceIds('use_case_response_processor')->willReturn($responseProcessorsWithTags);

        $useCaseContainerDefinition->addMethodCall('set', Argument::is(['use_case_1', 'service.use_case_1']))->shouldBeCalled();
        $inputProcessorContainerDefinition->addMethodCall('set', Argument::is(['input', 'service.input_processor']))->shouldBeCalled();
        $responseProcessorContainerDefinition->addMethodCall('set', Argument::is(['response', 'service.response_processor']))->shouldBeCalled();

        $this->process($containerBuilder);
    }

    public function it_registers_a_use_case_under_its_snake_cased_class_name_when_name_not_specified(
        ContainerBuilder $containerBuilder,
        AnnotationReader $annotationReader,
        Definition $useCaseContainerDefinition,
        Definition $contextResolverDefinition,
        Definition $taggedDefinition
    )
    {
        $taggedDefinition->getClass()->willReturn(UseCase3::class);
        $taggedDefinition->getTag('use_case')->willReturn([['name' => 'use_case']]);

        $emptyAnnotation = new UseCaseAnnotation([]);
        $containerBuilder->getDefinitions()->willReturn([
            'my_app.use_case'   => new Definition(UseCase1::class),
            'my_app.use_case_2' => new Definition(DoImportantStuff::class),
            'my_app.use_case_3' => $taggedDefinition
        ]);
        $annotationReader->getClassAnnotations(new \ReflectionClass(UseCase1::class))->willReturn([$emptyAnnotation]);
        $annotationReader->getClassAnnotations(new \ReflectionClass(DoImportantStuff::class))->willReturn([$emptyAnnotation]);

        $useCaseContainerDefinition
            ->addMethodCall('set', ['use_case_1', new Reference('my_app.use_case')])
            ->shouldBeCalled();
        $useCaseContainerDefinition
            ->addMethodCall('set', ['do_important_stuff', new Reference('my_app.use_case_2')])
            ->shouldBeCalled();
        $useCaseContainerDefinition
            ->addMethodCall('set', ['use_case_3', new Reference('my_app.use_case_3')])
            ->shouldBeCalled();

        $contextResolverDefinition
            ->addMethodCall('addUseCaseConfiguration', [['request_class' => \stdClass::class, 'use_case' => 'use_case_1']])
            ->shouldBeCalled();
        $contextResolverDefinition
            ->addMethodCall('addUseCaseConfiguration', [['request_class' => \stdClass::class, 'use_case' => 'do_important_stuff']])
            ->shouldBeCalled();
        $contextResolverDefinition
            ->addMethodCall('addUseCaseConfiguration', [['request_class' => \stdClass::class, 'use_case' => 'use_case_3']])
            ->shouldBeCalled();

        $this->process($containerBuilder);
    }

    public function it_adds_context_definitions_to_the_resolver(
        ContainerBuilder $containerBuilder,
        Definition $contextResolverDefinition
    )
    {
        $contexts = [
            'my_default_context' => ['response' => 'json'],
            'my_other_context'   => ['input' => 'array'],
            'web'                => ['input' => ['http' => ['accept' => 'json']], 'response' => 'twig']
        ];
        $containerBuilder->getParameter('bamiz_use_case.default_context')->willReturn('my_default_context');
        $containerBuilder->getParameter('bamiz_use_case.contexts')->willReturn($contexts);

        $contextResolverDefinition
            ->addMethodCall('setDefaultContextName', ['my_default_context', []])
            ->shouldBeCalled();
        $contextResolverDefinition
            ->addMethodCall('addContextDefinition', ['my_default_context', ['response' => 'json']])
            ->shouldBeCalled();
        $contextResolverDefinition
            ->addMethodCall('addContextDefinition', ['my_other_context', ['input' => 'array']])
            ->shouldBeCalled();
        $contextResolverDefinition
            ->addMethodCall('addContextDefinition', ['web', [
                'input' => ['http' => ['accept' => 'json']],
                'response' => 'twig'
            ]])->shouldBeCalled();

        $this->process($containerBuilder);
    }

    public function it_configures_multiple_processors_with_annotations(
        ContainerBuilder $containerBuilder,
        AnnotationReader $annotationReader,
        Definition $contextResolverDefinition,
        Definition $useCaseContainerDefinition,
        UseCaseAnnotation $useCaseAnnotation
    )
    {
        $useCaseAnnotation->getName()->willReturn('use_case');
        $inputAnnotation1 = new InputAnnotation(['value' => 'http', 'order' => 'GPC']);
        $inputAnnotation2 = new InputAnnotation(['value' => 'json']);
        $responseAnnotation1 = new ResponseAnnotation(['value' => 'twig', 'template' => 'index.html.twig']);
        $responseAnnotation2 = new ResponseAnnotation(['value' => 'cli', 'format' => 'ansi']);
        $configurationArray = [
            'request_class' => \stdClass::class,
            'use_case' => 'use_case',
            'input' => [
                'http' => ['order' => 'GPC'],
                'json' => []
            ],
            'response' => [
                'twig' => ['template' => 'index.html.twig'],
                'cli'  => ['format' => 'ansi']
            ]
        ];

        $containerBuilder->getDefinitions()->willReturn(['uc1' => new Definition(UseCase1::class)]);
        $annotationReader->getClassAnnotations(new \ReflectionClass(UseCase1::class))->willReturn([
            $useCaseAnnotation, $inputAnnotation1, $inputAnnotation2, $responseAnnotation1, $responseAnnotation2
        ]);

        $useCaseContainerDefinition->addMethodCall('set', ['use_case', new Reference('uc1')])->shouldBeCalled();
        $contextResolverDefinition
            ->addMethodCall('addUseCaseConfiguration', [$configurationArray])
            ->shouldBeCalled();

        $this->process($containerBuilder);
    }

    public function it_does_not_accept_multiple_use_case_annotations(
        ContainerBuilder $containerBuilder,
        AnnotationReader $annotationReader,
        UseCaseAnnotation $useCaseAnnotation1,
        UseCaseAnnotation $useCaseAnnotation2
    )
    {
        $containerBuilder->getDefinitions()->willReturn(['uc1' => new Definition(UseCase1::class)]);

        $annotationReader->getClassAnnotations(new \ReflectionClass(UseCase1::class))->willReturn([
            $useCaseAnnotation1, $useCaseAnnotation2
        ]);
        $this->shouldThrow(\InvalidArgumentException::class)->duringProcess($containerBuilder);
    }
}

class ContainerThatAcceptsReferences implements ReferenceAcceptingContainerInterface {
    public function set($name, $item) { }
    public function get($name) { }
}

class UseCase1
{
    public function execute()
    {
    }
}

class UseCase2
{
    public function execute()
    {
    }
}

class UseCase3
{
    public function execute()
    {
    }
}

class DoImportantStuff
{
    public function execute()
    {
    }
}

class NotAUseCase
{
    public function doNothing()
    {
    }
}
