<?php

namespace Bamiz\UseCaseExecutorBundle\Processor\Input;

use Bamiz\UseCaseExecutor\Processor\Input\InputProcessorInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormInputProcessor implements InputProcessorInterface
{
    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @param FormFactoryInterface $formFactory
     */
    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    /**
     * Populates the request object by having a Symfony form handle the HTTP request. By default it uses
     * the entire request object as a target for data from the form.
     * Available options:
     * - name - required. The name of the form that will handle the request.
     * - data_field - optional. If specified, instead of populating the request fields, the processor dumps
     *     all the form data into this Use Case Request field as an associative array.
     *
     * @param object                 $request The Use Case Request object to be initialized.
     * @param HttpFoundation\Request $input   Symfony HTTP request object.
     * @param array                  $options An array of configuration options.
     *
     * @return object the Use Case Request object is returned for testability purposes.
     */
    public function initializeRequest($request, $input, array $options = [])
    {
        $options = $this->validateOptions($options);

        if ($options['data_field']) {
            $form = $this->formFactory->create($options['name']);
            $form->handleRequest($input);

            $fieldName = $options['data_field'];
            $request->$fieldName = $form->getData();
        } else {
            $form = $this->formFactory->create($options['name'], $request, ['data_class' => get_class($request)]);
            $form->handleRequest($input);
        }

        return $request;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    private function validateOptions($options)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['data_field' => '']);
        $resolver->setRequired(['name']);

        return $resolver->resolve($options);
    }
}
