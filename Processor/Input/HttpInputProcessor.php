<?php

namespace Bamiz\UseCaseExecutorBundle\Processor\Input;

use Bamiz\UseCaseExecutor\Processor\Exception\UnsupportedInputException;
use Bamiz\UseCaseExecutor\Processor\Input\ArrayInputProcessor;
use Symfony\Component\HttpFoundation;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HttpInputProcessor extends ArrayInputProcessor
{
    const DEFAULT_ORDER = 'GPFCSHA';

    /**
     * Populates the Use Case Request object with data from the Symfony HTTP request. By default, the parameters of
     * the HTTP request are matched to the Use Case Request fields by their names in the following order, later values
     * overriding the older: GET, POST, FILES, COOKIES, SESSION, Headers, Attributes.
     * Available options:
     * - order - optional, default value: GPFCSHA. Use this option to apply a priority different from the above.
     *     The letters correspond to the first letters in the aforementioned HTTP request parameters.
     *     If some letters are omitted, the corresponding request parameters will be ignored during mapping.
     * - map - optional. This option allows to specify custom mapping from fields found in the HTTP request
     *     to the fields in the Use Case Request. Use an associative array with HTTP request parameter names as keys
     *     and Use Case Request field names as values.
     * - restrict - optional. This option allows to restrict mapping request data to specified sources only.
     *     Use an associative array with Use Case Request field names as keys and a string containing source
     *     identifiers (the first letters of GET, POST, etc.)
     *
     * @param object                 $request The Use Case Request object to be initialized.
     * @param HttpFoundation\Request $input   Symfony HTTP request object.
     * @param array                  $options An array of options to the input processor.
     *
     * @return object the Use Case Request object is returned for testability purposes.
     */
    public function initializeRequest($request, $input, array $options = [])
    {
        $this->validateInput($input);
        $options = $this->validateOptions($options);

        $inputData = [
            'G' => $input->query->all(),
            'P' => $input->request->all(),
            'F' => $input->files->all(),
            'C' => $input->cookies->all(),
            'S' => $input->server->all(),
            'H' => $input->headers->all(),
            'A' => $input->attributes->all()
        ];

        $requestData = [];
        $strlen = strlen($options['order']);
        for ($i = 0; $i < $strlen; $i++) {
            $key = $options['order'][$i];
            $dataToAdd = $this->getFilteredDataFromRequest($inputData, $key, $options);
            $requestData = array_merge($requestData, $dataToAdd);
        }

        parent::initializeRequest($request, $requestData, []);

        return $request;
    }

    /**
     * @param array  $inputData
     * @param string $sourceKey
     * @param array  $options
     *
     * @return array
     */
    private function getFilteredDataFromRequest($inputData, $sourceKey, $options)
    {
        $dataToAdd = [];

        foreach ($inputData[$sourceKey] as $inputKey => $inputValue) {
            $requestKey = $inputKey;
            if (isset($options['map'][$inputKey])) {
                $requestKey = $options['map'][$inputKey];
            }
            if ($this->isVariableRestricted($requestKey, $options, $sourceKey)) {
                continue;
            }
            if (isset($inputData[$sourceKey][$inputKey])) {
                $dataToAdd[$requestKey] = $inputData[$sourceKey][$inputKey];
            }
        }

        return $dataToAdd;
    }

    /**
     * @param string $requestKey
     * @param array  $options
     * @param string $sourceKey
     *
     * @return bool
     */
    private function isVariableRestricted($requestKey, $options, $sourceKey)
    {
        if (!isset($options['restrict'][$requestKey])) {
            return false;
        }
        if (strpos($options['restrict'][$requestKey], $sourceKey) !== false) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $input
     *
     * @throws UnsupportedInputException
     */
    private function validateInput($input)
    {
        if (!($input instanceof HttpFoundation\Request)) {
            throw new UnsupportedInputException('HTTP', HttpFoundation\Request::class, $input);
        }
    }

    /**
     * @param array $options
     *
     * @return array
     */
    private function validateOptions($options)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'order'    => self::DEFAULT_ORDER,
            'map'      => [],
            'restrict' => []
        ]);
        
        return $resolver->resolve($options);
    }
}
