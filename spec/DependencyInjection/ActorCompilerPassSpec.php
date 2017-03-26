<?php

namespace spec\Bamiz\UseCaseExecutorBundle\DependencyInjection;

use Bamiz\UseCaseExecutor\Actor\ActorInterface;
use Bamiz\UseCaseExecutor\Actor\ActorRecognizerInterface;
use Bamiz\UseCaseExecutor\Actor\OmnipotentActor;
use Bamiz\UseCaseExecutorBundle\DependencyInjection\ActorCompilerPass;
use PhpSpec\ObjectBehavior;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ActorCompilerPassSpec extends ObjectBehavior
{
    public function let(
        ContainerBuilder $containerBuilder,
        Definition $compositeRecognizerDefinition,
        Definition $recognizerDefinition,
        Definition $wrongDefinition
    )
    {
        $recognizerDefinition->getClass()->willReturn(ActorRecognizer::class);
        $wrongDefinition->getClass()->willReturn(NotAnActorRecognizer::class);

        $containerBuilder->findDefinition('actor_recognizer_1')->willReturn($recognizerDefinition);
        $containerBuilder->findDefinition('actor_recognizer_2')->willReturn($recognizerDefinition);
        $containerBuilder->findDefinition('not_an_actor_recognizer')->willReturn($wrongDefinition);

        $containerBuilder->findDefinition(ActorCompilerPass::COMPOSITE_ACTOR_RECOGNIZER_SERVICE)
            ->willReturn($compositeRecognizerDefinition);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(ActorCompilerPass::class);
    }

    public function it_is_a_compiler_pass()
    {
        $this->shouldHaveType(CompilerPassInterface::class);
    }

    public function it_does_nothing_if_composer_actor_recognizer_service_is_not_found(
        ContainerBuilder $containerBuilder
    )
    {
        $containerBuilder->findDefinition(ActorCompilerPass::COMPOSITE_ACTOR_RECOGNIZER_SERVICE)->willReturn(null);
        $containerBuilder->findTaggedServiceIds('use_case_actor_recognizer')->shouldNotBeCalled();

        $this->process($containerBuilder);
    }

    public function it_throws_an_exception_if_one_of_the_services_does_not_implement_the_right_interface(
        ContainerBuilder $containerBuilder
    ) {
        $taggedServices = [
            'actor_recognizer_1'      => [],
            'actor_recognizer_2'      => [],
            'not_an_actor_recognizer' => []
        ];
        $containerBuilder->findTaggedServiceIds('use_case_actor_recognizer')->willReturn($taggedServices);

        $this->shouldThrow(\InvalidArgumentException::class)->duringProcess($containerBuilder);
    }

    public function it_registers_all_recognizers_in_composite_actor_recognizer(
        ContainerBuilder $containerBuilder,
        Definition $compositeRecognizerDefinition
    ) {
        $taggedServices = [
            'actor_recognizer_1' => [],
            'actor_recognizer_2' => []
        ];
        $containerBuilder->findTaggedServiceIds('use_case_actor_recognizer')->willReturn($taggedServices);

        $compositeRecognizerDefinition->addMethodCall('addActorRecognizer', [new Reference('actor_recognizer_1')])
            ->shouldBeCalled();
        $compositeRecognizerDefinition->addMethodCall('addActorRecognizer', [new Reference('actor_recognizer_2')])
            ->shouldBeCalled();

        $this->process($containerBuilder);
    }
}

class ActorRecognizer implements ActorRecognizerInterface
{
    /**
     * @param object $useCaseRequest
     *
     * @return ActorInterface
     */
    public function recognizeActor($useCaseRequest)
    {
        return new OmnipotentActor();
    }
}

class NotAnActorRecognizer
{
}
