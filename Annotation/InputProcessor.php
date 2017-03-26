<?php

namespace Bamiz\UseCaseExecutorBundle\Annotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class InputProcessor extends ProcessorAnnotation
{
    /**
     * @return string
     */
    public function getType()
    {
        return 'input';
    }
}