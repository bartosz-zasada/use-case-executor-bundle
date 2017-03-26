<?php

namespace spec\Bamiz\UseCaseExecutorBundle\Processor\Input;

use Bamiz\UseCaseExecutor\Processor\Input\InputProcessorInterface;
use Bamiz\UseCaseExecutorBundle\Processor\Input\FormInputProcessor;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @mixin \Bamiz\UseCaseExecutorBundle\Processor\Input\FormInputProcessor
 */
class FormInputProcessorSpec extends ObjectBehavior
{
    public function let(FormFactoryInterface $formFactory, FormInterface $form)
    {
        $this->beConstructedWith($formFactory);
        $formFactory->create(Argument::cetera())->willReturn($form);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(FormInputProcessor::class);
    }

    public function it_is_an_input_processor()
    {
        $this->shouldHaveType(InputProcessorInterface::class);
    }

    public function it_throws_an_exception_if_form_name_is_not_specified()
    {
        $request = new \stdClass();
        $this->shouldThrow(\InvalidArgumentException::class)->duringInitializeRequest($request, [], []);
    }

    public function it_throws_an_exception_if_an_unrecognized_option_is_used()
    {
        $options = ['name' => 'form_name', 'what is this' => 'crazy thing'];
        $request = new FormUseCaseRequest();
        $this->shouldThrow(\InvalidArgumentException::class)->duringInitializeRequest($request, [], $options);
    }

    public function it_uses_form_factory_to_create_form_by_name(FormFactoryInterface $formFactory, Request $input)
    {
        $request = new FormUseCaseRequest();
        $this->initializeRequest($request, $input, ['name' => 'order_form']);

        $formFactory->create('order_form', $request, ['data_class' => FormUseCaseRequest::class])->shouldHaveBeenCalled();
    }

    public function it_uses_the_created_form_to_populate_request_fields(
        FormFactoryInterface $formFactory, FormInterface $form, Request $input
    )
    {
        $request = new \stdClass();
        $formFactory->create('order_form', Argument::cetera())->willReturn($form);

        $this->initializeRequest($request, $input, ['name' => 'order_form']);

        $form->handleRequest($input)->shouldHaveBeenCalled();
    }

    public function it_dumps_form_data_to_specified_field(FormFactoryInterface $formFactory, FormInterface $form)
    {
        $request = new \stdClass();
        $formFactory->create('order_form')->willReturn($form);

        $input = ['foo' => 'bar', 'baz' => 213];
        $formData = ['foo' => 'bar_', 'baz' => 213312, 'csrf_token' => 'xyz'];
        $form->handleRequest($input)->shouldBeCalled();
        $form->getData()->willReturn($formData);

        $request = $this->initializeRequest($request, $input, ['name' => 'order_form', 'data_field' => 'formData']);

        $request->formData->shouldBe($formData);
    }
}

class FormUseCaseRequest {}
