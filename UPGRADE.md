UPGRADE
=======

## From 0.2 to 0.3

* The feature to configure multiple Input and Response Processors has been introduced, which changed the configuration
format for Processor options.

    The old format:
    
    ```
    @UseCase(
        "another_use_case_name",
        input={
            "type"="http",
            "priority"="GPC"
        },
        response={
            "type"="twig",
            "template"="MyBundle:default:index.html.twig"
        }
    )
    ```
    
    The new format:
    
    ```
    @UseCase(
        "another_use_case_name",
        input={
            "http"={"priority"="GPC"}
        },
        response={
            "twig"={"template"="MyBundle:default:index.html.twig"}
        }
    )
    ```

The YAML and array structure has been changed in the same way. See chapter [Use Case Contexts](doc/03-use-case-contexts.md)
for details.

* `Lamudi\UseCaseBundle\Exception\UseCaseException` has been renamed to 
`Lamudi\UseCaseBundle\Exception\AlternativeCourseException`.
* The class names of all Processor except the default ones have been parametrized, allowing to use custom implementations
of bundles Processors without the need to register them with new aliases.

## From 0.3 to 0.4

BC breaks:

* The top-level namespace has been changed from `Lamudi` to `Bamiz`.
* Multiple `@UseCase` annotations are no longer supported.
* It is no longer possible to configure Use Cases with `input` and `response` options of the `@UseCase` annotation.
This feature has been dropped in favor of new annotations - `@InputProcessor` and `@ResponseProcessor`.
* Signatures of `processInput()` method of `InputProcessorInterface`, as well as `processResponse()` and
`handleException()` methods of `ResponseProcessorInterface` have been changed: `array` type hint has been added
to the `$options` argument.
  
New features:

* Support for actors. See chapter [Actors](doc/06-actors.md) for details.
* New annotations for configuring Input and Response processors for Use Cases: `@InputProcessor` and `@ResponseProcessor`.
It is possible to use multiple annotations per Use Case, thus using many Processors to process Input or Response.
* Ability to register Use Cases using a tag instead of the `@UseCase` annotation. In this case, the annotations
of the Use Case class are ignored.
* Base Controller that executes Use Cases. Supports magic methods and passing the name and the configuration of the
Use Case as route attributes. See chapter [Toolkit](doc/04-toolkit.md) for details.

## From 0.4 to 0.5

Use Case Executor has been extracted from Use Case Bundle, becoming a component that is 100% independent from any PHP
framework. This bundle now installs Use Case Executor as its dependency. As a result, the namespace prefix for all 
classes has been changed from `Bamiz\UseCaseBundle` to `Bamiz\UseCaseExecutorBundle`. All service names have remained
unchanged.
