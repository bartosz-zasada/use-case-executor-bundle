<?php

namespace spec\Bamiz\UseCaseExecutorBundle\Annotation;

use PhpSpec\ObjectBehavior;

class ResponseProcessorSpec extends ObjectBehavior
{
    public function it_sets_name_and_options_through_constructor()
    {
        $annotationOptions = [
            'value' => 'http',
            'order' => 'GPCS',
            'map'   => ['foo' => 'bar']
        ];

        $this->beConstructedWith($annotationOptions);

        $this->getName()->shouldBe('http');
        $this->getOptions()->shouldBe([
            'order' => 'GPCS',
            'map'   => ['foo' => 'bar']
        ]);
    }

    public function it_throws_an_exception_if_no_value_was_given()
    {
        $this->beConstructedWith([]);
        $this->shouldThrow(\InvalidArgumentException::class)->duringInstantiation();
    }
}
