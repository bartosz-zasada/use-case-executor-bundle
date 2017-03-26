<?php

namespace spec\Bamiz\UseCaseExecutorBundle\Controller;

use Bamiz\UseCaseExecutor\UseCaseExecutor;
use Bamiz\UseCaseExecutorBundle\Controller\MagicController;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MagicControllerSpec extends ObjectBehavior
{
    public function let(ContainerInterface $symfonyContainer, UseCaseExecutor $useCaseExecutor)
    {
        $symfonyContainer->get('bamiz_use_case.executor')->willReturn($useCaseExecutor);
        $this->setContainer($symfonyContainer);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(MagicController::class);
    }

    public function it_extends_base_symfony_controller()
    {
        $this->shouldHaveType(Controller::class);
    }

    public function it_uses_magic_to_execute_use_cases(UseCaseExecutor $useCaseExecutor)
    {
        $useCaseExecutor->execute('do_something')->shouldBeCalled();

        $this->doSomething();
    }

    public function it_uses_magic_to_execute_use_cases_with_input(UseCaseExecutor $useCaseExecutor)
    {
        $input = ['foo' => 'bar'];
        $useCaseExecutor->execute('do_something', $input)->shouldBeCalled();

        $this->doSomething($input);
    }

    public function it_uses_magic_to_execute_use_cases_as_actors(
        UseCaseExecutor $useCaseExecutor,
        UseCaseExecutor $useCaseAsActorExecutor
    )
    {
        $input = [];

        $useCaseExecutor->asActor('jedi')->willReturn($useCaseAsActorExecutor);
        $useCaseAsActorExecutor->execute('use_the_force', $input, ['input' => 'http', 'response' => 'json'])->shouldBeCalled();

        $this->jedi->useTheForce($input, ['input' => 'http', 'response' => 'json']);
    }

    public function it_executes_use_case_taking_its_name_and_configuration_from_request_attributes(
        UseCaseExecutor $useCaseExecutor,
        Request $request,
        Response $response,
        ParameterBag $attributeBag
    )
    {
        $useCaseExecutor->execute(Argument::cetera())->willReturn($response);

        $request->attributes = $attributeBag;
        $attributeBag->has('_input')->willReturn(false);
        $attributeBag->has('_response')->willReturn(false);
        $attributeBag->has('_actor')->willReturn(false);
        $attributeBag->get('_use_case')->willReturn('do_something');
        $this->useCaseAction($request)->shouldBe($response);
        $useCaseExecutor->execute('do_something', $request, [])->shouldHaveBeenCalled();

        $attributeBag->has('_input')->willReturn(true);
        $attributeBag->get('_input')->willReturn('http');
        $this->useCaseAction($request)->shouldBe($response);
        $useCaseExecutor->execute('do_something', $request, ['input' => 'http'])->shouldHaveBeenCalled();

        $attributeBag->has('_response')->willReturn(true);
        $attributeBag->get('_response')->willReturn('cli');
        $this->useCaseAction($request)->shouldBe($response);
        $useCaseExecutor->execute('do_something', $request, ['input' => 'http', 'response' => 'cli'])->shouldHaveBeenCalled();
    }

    public function it_executes_use_case_with_universal_action_as_actor(
        UseCaseExecutor $useCaseExecutor,
        UseCaseExecutor $useCaseAsActorExecutor,
        Request $request,
        Response $response,
        ParameterBag $attributeBag
    )
    {
        $request->attributes = $attributeBag;
        $attributeBag->has('_input')->willReturn(false);
        $attributeBag->has('_response')->willReturn(false);
        $attributeBag->has('_actor')->willReturn(true);
        $attributeBag->get('_actor')->willReturn('jedi');
        $attributeBag->get('_use_case')->willReturn('do_something');

        $useCaseExecutor->execute(Argument::cetera())->shouldNotBeCalled();
        $useCaseExecutor->asActor('jedi')->willReturn($useCaseAsActorExecutor);

        $useCaseAsActorExecutor->execute('do_something', $request, [])->willReturn($response);

        $this->useCaseAction($request)->shouldBe($response);
    }
}
