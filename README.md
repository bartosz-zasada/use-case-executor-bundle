# Use Case Executor Bundle

Use Case Executor Bundle is a Symfony bundle providing an example implementation of Screaming Architecture, with help of
components that come with Symfony framework. It encourages designing your class in a fashion that reflects the intention 
of your application. The tools provided by Use Case Executor Bundle relieve you of the repetitive task of extracting the 
information required to perform the right behavior from the application input, which helps you output the results in the 
desired way. 

Installation
============

Just run 

    $ composer require bamiz/use-case-executor-bundle

Configuration
=============

Register your bundle in AppKernel.php:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...

            new Bamiz\UseCaseExecutorBundle\BamizUseCaseExecutorBundle(),
        );

        // ...
    }

    // ...
}
```

Enable serializer in app/config.yml:

```
# app/config.yml

framework:
    serializer: ~
    
```

Basic usage
===========

Register your Use Case as a Symfony service:

```
# app/services.yml

app.my_use_case:
    class: AppBundle\UseCase\MyUseCase
```

Using an annotation, name the Use Case and optionally assign an Input Processor and a Response Processor to it.
Make sure that the Use Case class contains an ```execute()``` method with one type-hinted parameter.

```php
<?php
// src/AppBundle/UseCase/MyUseCase.php

namespace AppBundle\UseCase;

use Bamiz\UseCaseExecutorBundle\Annotation\UseCase;

/**
 * @UseCase("My Use Case", input="http", response="json")
 */
class MyUseCase
{
    public function execute(MyUseCaseRequest $request)
    {
        // ...
    }
}
```

Use the Use Case Executor to execute your Use Cases:

```php
<?php
// src/AppBundle/Controller/MyController.php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class MyController extends Controller
{
    public function myAction(Request $request)
    {
        return $this->get('bamiz_use_case.executor')->execute('My Use Case', $request);
    }
}

```

Documentation
=============

* [Concept](doc/01-concept.md) - Use Cases, Requests, and Responses explained, basic architecture and Bundle usage examples
* [Use Cases in Symfony](doc/02-use-cases-in-symfony.md) - Differences between Application and Use Case layers explained, introducing concepts of Input and Output 
* [Use Case Contexts](doc/03-use-case-contexts.md) - How to configure the way your Use Cases are executed
* [Toolkit](doc/04-toolkit.md) - Input and Response Processors shipped with the bundle
* [Using multiple Input and Response Processors](doc/05-using-multiple-input-and-response-processors.md) 
* [Actors](doc/06-actors.md) 
