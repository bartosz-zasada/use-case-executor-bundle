<?php

namespace Bamiz\UseCaseExecutorBundle\Annotation;

abstract class ProcessorAnnotation
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param array $data
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $data)
    {
        if (!isset($data['value'])) {
            throw new \InvalidArgumentException(
                sprintf('%s Processor name must be specified.', ucfirst($this->getType()))
            );
        }

        $this->name = $data['value'];
        unset($data['value']);
        $this->options = $data;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return string
     */
    abstract public function getType();
}