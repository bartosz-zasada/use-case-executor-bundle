<?php

namespace spec\Bamiz\UseCaseExecutorBundle\Processor\Input {

    use Bamiz\UseCaseExecutor\Processor\Exception\UnsupportedInputException;
    use Bamiz\UseCaseExecutor\Processor\Input\InputProcessorInterface;
    use Bamiz\UseCaseExecutorBundle\Processor\Input\HttpInputProcessor;
    use Foo\Bar\Request\DataFromHttpRequest;
    use Foo\Bar\Request\SpecificRequest;
    use PhpSpec\ObjectBehavior;
    use Prophecy\Prophet;
    use Symfony\Component\HttpFoundation\FileBag;
    use Symfony\Component\HttpFoundation\HeaderBag;
    use Symfony\Component\HttpFoundation\ParameterBag;
    use Symfony\Component\HttpFoundation\Request as HttpRequest;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\ServerBag;

    /**
     * @mixin \Bamiz\UseCaseExecutorBundle\Processor\Input\HttpInputProcessor
     */
    class HttpInputProcessorSpec extends ObjectBehavior
    {
        public function it_is_initializable()
        {
            $this->shouldHaveType(HttpInputProcessor::class);
        }

        public function it_is_an_input_processor()
        {
            $this->shouldHaveType(InputProcessorInterface::class);
        }

        public function it_throws_an_exception_if_input_type_is_not_http_request(\stdClass $unsupportedInput)
        {
            $request = new SpecificRequest();
            $this->shouldThrow(UnsupportedInputException::class)->duringInitializeRequest($request, $unsupportedInput);
        }

        public function it_throws_an_exception_if_an_unrecognized_option_is_used()
        {
            $options = ['what is this' => 'crazy thing'];
            $request = new SpecificRequest();
            $input = new Request();
            $this->shouldThrow(\InvalidArgumentException::class)->duringInitializeRequest($request, $input, $options);
        }

        public function it_collects_data_from_http_request()
        {
            $httpRequestData = [
                'GET'     => ['query' => 'query_value'],
                'POST'    => ['request' => 'request_value'],
                'FILES'   => ['file' => 'file_value'],
                'COOKIE'  => ['cookie' => 'cookie_value'],
                'SERVER'  => ['server' => 'server_value'],
                'headers' => ['header' => 'header_value'],
                'attrs'   => ['attribute' => 'attribute_value'],
            ];
            $httpRequest = $this->initializeHttpRequest($httpRequestData);

            /** @var DataFromHttpRequest $request */
            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest);

            $request->attribute->shouldBe('attribute_value');
            $request->request->shouldBe('request_value');
            $request->query->shouldBe('query_value');
            $request->server->shouldBe('server_value');
            $request->file->shouldBe('file_value');
            $request->cookie->shouldBe('cookie_value');
            $request->header->shouldBe('header_value');
        }

        public function it_reads_data_from_http_request_with_certain_default_priority()
        {
            $this->attributesOverrideAll();
            $this->headersOverrideGetPostFilesCookiesAndServer();
            $this->serverOverridesGetPostFilesAndCookies();
            $this->cookiesOverrideGetPostAndFiles();
            $this->filesOverrideGetAndPost();
            $this->postOverridesGet();
        }

        public function it_reads_data_from_http_request_by_given_order()
        {
            $httpRequest = $this->initializeHttpRequest([
                'GET'     => ['var1' => 'G_value_1', 'var2' => 'G_value_2', 'var3' => 'G_value_3'],
                'POST'    => ['var1' => 'P_value_1', 'var2' => 'P_value_2'],
                'FILES'   => [                       'var2' => 'F_value_2', 'var3' => 'F_value_3'],
                'COOKIE'  => ['var1' => 'C_value_1'],
                'SERVER'  => ['var1' => 'S_value_1',                        'var3' => 'S_value_3'],
                'headers' => [                       'var2' => 'H_value_2'],
                'attrs'   => [                                              'var3' => 'A_value_3'],
            ]
            );

            /** @var DataFromHttpRequest $request */
            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest, ['order' => 'GPC']);
            $request->var1->shouldBe('C_value_1');
            $request->var2->shouldBe('P_value_2');
            $request->var3->shouldBe('G_value_3');

            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest, ['order' => 'PCG']);
            $request->var1->shouldBe('G_value_1');
            $request->var2->shouldBe('G_value_2');
            $request->var3->shouldBe('G_value_3');

            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest, ['order' => 'GCP']);
            $request->var1->shouldBe('P_value_1');
            $request->var2->shouldBe('P_value_2');
            $request->var3->shouldBe('G_value_3');

            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest, ['order' => 'PSA']);
            $request->var1->shouldBe('S_value_1');
            $request->var2->shouldBe('P_value_2');
            $request->var3->shouldBe('A_value_3');

            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest, ['order' => 'FSCAGH']);
            $request->var1->shouldBe('G_value_1');
            $request->var2->shouldBe('H_value_2');
            $request->var3->shouldBe('G_value_3');
        }

        public function it_maps_fields_from_array_to_object_using_custom_mappings()
        {
            $httpRequest = $this->initializeHttpRequest([
                'GET' => ['q' => 'cheap hotels', 'p' => 3],
                'COOKIE' => ['PHPSESSID' => 'asd123'],
                'SERVER' => ['REMOTE_ADDR' => '127.0.0.1']
            ]);

            $options = [
                'map' => [
                    'q'           => 'searchQuery',
                    'p'           => 'pageNumber',
                    'PHPSESSID'   => 'sessionId',
                    'REMOTE_ADDR' => 'ipAddress'
                ]
            ];

            /** @var SpecificRequest $request */
            $request = $this->initializeRequest(new SpecificRequest(), $httpRequest, $options);
            $request->searchQuery->shouldBe('cheap hotels');
            $request->pageNumber->shouldBe(3);
            $request->sessionId->shouldBe('asd123');
            $request->ipAddress->shouldBe('127.0.0.1');
        }

        public function it_restricts_data_to_be_retrieved_from_selected_sources()
        {
            $httpRequest = $this->initializeHttpRequest([
                'GET'    => ['PHPSESSID' => 'nice try', 'pageNumber' => 21],
                'POST'   => ['pageNumber' => 42],
                'COOKIE' => ['PHPSESSID' => 'not from here',     'REMOTE_ADDR' => '8.8.8.8'],
                'SERVER' => ['PHPSESSID' => 'from here', 'REMOTE_ADDR' => '127.0.0.1'],
                'attrs'  => ['sessionId' => 'how did it get here']
            ]);

            $options = [
                'map' => [
                    'PHPSESSID'   => 'sessionId',
                    'REMOTE_ADDR' => 'ipAddress'
                ],
                'restrict' => [
                    'pageNumber' => 'G',
                    'sessionId' => 'SC',
                ],
                'order' => 'GPFCSHA'
            ];

            /** @var SpecificRequest $request */
            $request = $this->initializeRequest(new SpecificRequest(), $httpRequest, $options);
            $request->pageNumber->shouldBe(21);
            $request->sessionId->shouldBe('from here');
            $request->ipAddress->shouldBe('127.0.0.1');
        }

        private function initializeHttpRequest($data)
        {
            $httpRequest = new HttpRequest();

            $prophet = new Prophet();
            $attributesBag = $prophet->prophesize(ParameterBag::class);
            $requestBag = $prophet->prophesize(ParameterBag::class);
            $queryBag = $prophet->prophesize(ParameterBag::class);
            $serverBag = $prophet->prophesize(ServerBag::class);
            $filesBag = $prophet->prophesize(FileBag::class);
            $cookiesBag = $prophet->prophesize(ParameterBag::class);
            $headersBag = $prophet->prophesize(HeaderBag::class);

            $attributesBag->all()->willReturn(isset($data['attrs']) ? $data['attrs'] : []);
            $requestBag->all()->willReturn(isset($data['POST']) ? $data['POST'] : []);
            $queryBag->all()->willReturn(isset($data['GET']) ? $data['GET'] : []);
            $serverBag->all()->willReturn(isset($data['SERVER']) ? $data['SERVER'] : []);
            $filesBag->all()->willReturn(isset($data['FILES']) ? $data['FILES'] : []);
            $cookiesBag->all()->willReturn(isset($data['COOKIE']) ? $data['COOKIE'] : []);
            $headersBag->all()->willReturn(isset($data['headers']) ? $data['headers'] : []);

            $httpRequest->attributes = $attributesBag->reveal();
            $httpRequest->request = $requestBag->reveal();
            $httpRequest->query = $queryBag->reveal();
            $httpRequest->server = $serverBag->reveal();
            $httpRequest->files = $filesBag->reveal();
            $httpRequest->cookies = $cookiesBag->reveal();
            $httpRequest->headers = $headersBag->reveal();

            return $httpRequest;
        }

        private function attributesOverrideAll()
        {
            $httpRequestData = [
                'GET'     => ['var' => 'query_value'],
                'POST'    => ['var' => 'request_value'],
                'FILES'   => ['var' => 'file_value'],
                'COOKIE'  => ['var' => 'cookie_value'],
                'SERVER'  => ['var' => 'server_value'],
                'headers' => ['var' => 'header_value'],
                'attrs'   => ['var' => 'attribute_value'],
            ];
            $httpRequest = $this->initializeHttpRequest($httpRequestData);

            /** @var DataFromHttpRequest $request */
            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest);
            $request->var->shouldBe('attribute_value');
        }

        private function headersOverrideGetPostFilesCookiesAndServer()
        {
            $httpRequestData = [
                'GET'     => ['var' => 'query_value'],
                'POST'    => ['var' => 'request_value'],
                'FILES'   => ['var' => 'file_value'],
                'COOKIE'  => ['var' => 'cookie_value'],
                'SERVER'  => ['var' => 'server_value'],
                'headers' => ['var' => 'header_value'],
            ];
            $httpRequest = $this->initializeHttpRequest($httpRequestData);

            /** @var DataFromHttpRequest $request */
            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest);
            $request->var->shouldBe('header_value');
        }

        private function serverOverridesGetPostFilesAndCookies()
        {
            $httpRequestData = [
                'GET'    => ['var' => 'query_value'],
                'POST'   => ['var' => 'request_value'],
                'FILES'  => ['var' => 'file_value'],
                'COOKIE' => ['var' => 'cookie_value'],
                'SERVER' => ['var' => 'server_value'],
            ];
            $httpRequest = $this->initializeHttpRequest($httpRequestData);

            /** @var DataFromHttpRequest $request */
            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest);
            $request->var->shouldBe('server_value');
        }

        private function cookiesOverrideGetPostAndFiles()
        {
            $httpRequestData = [
                'GET'    => ['var' => 'query_value'],
                'POST'   => ['var' => 'request_value'],
                'FILES'  => ['var' => 'file_value'],
                'COOKIE' => ['var' => 'cookie_value'],
            ];
            $httpRequest = $this->initializeHttpRequest($httpRequestData);

            /** @var DataFromHttpRequest $request */
            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest);
            $request->var->shouldBe('cookie_value');
        }

        private function filesOverrideGetAndPost()
        {
            $httpRequestData = [
                'GET'   => ['var' => 'query_value'],
                'POST'  => ['var' => 'request_value'],
                'FILES' => ['var' => 'file_value'],
            ];
            $httpRequest = $this->initializeHttpRequest($httpRequestData);

            /** @var DataFromHttpRequest $request */
            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest);
            $request->var->shouldBe('file_value');
        }

        private function postOverridesGet()
        {
            $httpRequestData = [
                'GET'  => ['var' => 'query_value'],
                'POST' => ['var' => 'request_value'],
            ];
            $httpRequest = $this->initializeHttpRequest($httpRequestData);

            /** @var DataFromHttpRequest $request */
            $request = $this->initializeRequest(new DataFromHttpRequest(), $httpRequest);
            $request->var->shouldBe('request_value');
        }
    }
}

namespace Foo\Bar\Request {

    class SomeRequest {}

    class DataFromHttpRequest
    {
        public $attribute;
        public $request;
        public $query;
        public $server;
        public $file;
        public $cookie;
        public $header;
        public $var;
        public $var1;
        public $var2;
        public $var3;
    }

    class SpecificRequest
    {
        public $searchQuery;
        public $pageNumber;
        public $sessionId;
        public $ipAddress;
    }
}
