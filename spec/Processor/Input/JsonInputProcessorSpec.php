<?php

namespace spec\Bamiz\UseCaseExecutorBundle\Processor\Input;

use Bamiz\UseCaseExecutor\Processor\Exception\UnsupportedInputException;
use Bamiz\UseCaseExecutor\Processor\Input\InputProcessorInterface;
use Bamiz\UseCaseExecutorBundle\Processor\Input\JsonInputProcessor;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * @mixin JsonInputProcessor
 */
class JsonInputProcessorSpec extends ObjectBehavior
{
    public function let(DecoderInterface $jsonDecoder)
    {
        $this->beConstructedWith($jsonDecoder);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(JsonInputProcessor::class);
    }

    public function it_is_an_input_processor()
    {
        $this->shouldHaveType(InputProcessorInterface::class);
    }

    public function it_throws_an_exception_if_input_is_not_a_symfony_http_request()
    {
        $unsupportedInput = new \stdClass();
        $this->shouldThrow(UnsupportedInputException::class)->duringInitializeRequest(new \stdClass(), $unsupportedInput);
    }

    public function it_throws_an_exception_if_an_unrecognized_option_is_used(HttpRequest $input)
    {
        $options = ['what is this' => 'crazy thing'];
        $this->shouldThrow(\InvalidArgumentException::class)->duringInitializeRequest(new MyRequest(), $input, $options);
    }

    public function it_populates_the_request_with_json_body_data(HttpRequest $httpRequest, DecoderInterface $jsonDecoder)
    {
        $data = ['stringField' => 'asd', 'numberField' => 123, 'booleanField' => true, 'arrayField' => [3, 2, 1]];
        $jsonDecoder->decode(Argument::any(), 'json')->willReturn($data);

        /** @var JsonRequest $request */
        $request = $this->initializeRequest(new JsonRequest(), $httpRequest);
        $request->stringField->shouldBe($data['stringField']);
        $request->numberField->shouldBe($data['numberField']);
        $request->booleanField->shouldBe($data['booleanField']);
        $request->arrayField->shouldBe($data['arrayField']);
    }

    public function it_populates_the_request_by_mapping_json_fields_to_request_object(
        HttpRequest $httpRequest, DecoderInterface $jsonDecoder
    )
    {
        $data = ['foo' => 'qwe', 'n' => 999, 'opt' => true, 'nums' => [3, 2, 1]];
        $jsonDecoder->decode(Argument::any(), 'json')->willReturn($data);

        $options = ['map' => [
            'foo'  => 'stringField',
            'n'    => 'numberField',
            'opt'  => 'booleanField',
            'nums' => 'arrayField'
        ]];
        /** @var JsonRequest $request */
        $request = $this->initializeRequest(new JsonRequest(), $httpRequest, $options);
        $request->stringField->shouldBe($data['foo']);
        $request->numberField->shouldBe($data['n']);
        $request->booleanField->shouldBe($data['opt']);
        $request->arrayField->shouldBe($data['nums']);
    }
}

class JsonRequest
{
    public $stringField;
    public $numberField;
    public $booleanField;
    public $arrayField;
}

class MyRequest
{
    public $stringField;
    public $numberField;
    public $booleanField;
    public $arrayField;
    public $omittedField;
    public $omittedFieldWithDefaultValue = 'asdf';
}
