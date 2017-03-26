<?php

namespace Bamiz\UseCaseExecutorBundle\Annotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class UseCase
{
    /**
     * @var string
     */
    private $name;

    /**
     * @param array $data
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $data)
    {
        if (isset($data['value'])) {
            $this->name = $data['value'];
            unset($data['value']);
        }

        if (!empty($data)) {
            $invalidOptions = array_keys($data);
            if (count($invalidOptions) > 0) {
                throw new \InvalidArgumentException(sprintf(
                    'Unsupported options on UseCase annotation: %s', implode(', ', $invalidOptions)
                ));
            }
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
