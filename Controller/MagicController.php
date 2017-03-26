<?php

namespace Bamiz\UseCaseExecutorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MagicController extends Controller
{
    /**
     * @var string
     */
    private $actorName;

    /**
     * @param string $name
     *
     * @return $this
     */
    public function __get($name)
    {
        $this->actorName = $name;
        return $this;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function useCaseAction(Request $request)
    {
        $configuration = [];
        foreach (['input', 'response'] as $processor) {
            if ($request->attributes->has('_' . $processor)) {
                $configuration[$processor] = $request->attributes->get('_' . $processor);
            }
        }

        $executor = $this->get('bamiz_use_case.executor');
        if ($request->attributes->has('_actor')) {
            $executor = $executor->asActor($request->attributes->get('_actor'));
        }

        return $executor->execute($request->attributes->get('_use_case'), $request, $configuration);
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $useCaseName = $this->camelCaseToSnakeCase($name);
        $useCaseExecutor = $this->get('bamiz_use_case.executor');
        if ($this->actorName) {
            $useCaseExecutor = $useCaseExecutor->asActor($this->actorName);
        }

        $executorArguments = array_merge([$useCaseName], $arguments);

        return call_user_func_array([$useCaseExecutor, 'execute'], $executorArguments);
    }

    /**
     * @param $name
     *
     * @return string
     */
    private function camelCaseToSnakeCase($name)
    {
        return strtolower(preg_replace('/([A-Z])/', '_$1', $name));
    }
}
