# Actors

In the world of Use Cases, Actors are entities that interact with the system while not being a part of it. The most
important Actors in any application are the users, for whom the application is written. 

The Use Case Bundle provides tools to manage all Actors in the way that they are represented as classes in your
code, thus making explicit not only how your system is used, but also who is using it.

## Implementing Actors

A class that represents an Actor should be named after the Actor. For example, if our application is an online shop,
the most important Actor will be a Customer:

```php
class Customer
{
}
```

To make your Actor class work with the Use Case Bundle, it must implement `\Bamiz\UseCaseExecutorBundle\Actor\ActorInterface`.

```php
use Bamiz\UseCaseExecutorBundle\Actor\ActorInterface;

class Customer implements ActorInterface
{
}
```

`ActorInterface` comes with two methods: `canExecute()` and `getName()`.

Method `canExecute()` receives the name of the Use Case and is expected to return a boolean, which is an answer to 
the question, whether a Use Case by this name can be executed by this Actor. 
 
The implementation of `canExecute()` method is completely up to you. You might allow or deny execution of certain 
Use Cases just by their names or parts of names, e.g. Customers can only execute Use Cases with names starting with 
`customer.`. You can also use an external service that implements a more complex ACL system. The only thing that you
should keep in mind is that Actors belong to you business logic layer. Therefore, you are advised not to rely on
services belonging to other layers, like HTTP requests or concrete database implementations.

Method `getName()` is used when to refer to your Actor when you want to specify, which Actor should execute given 
Use Case in your controllers. You will find more details on this matter in subchapter Executing Use Cases by Actors.
The recommended convention for naming your Actors is snake_case, but whether you choose to follow this convention or not
is entirely up to you.

## Recognizing Actors

In order to make use of Actor classes, you must implement at least one service that will be used by the Use Case 
Executor to recognize which Actor is currently using the system. This service must implement 
`\Bamiz\UseCaseExecutorBundle\Actor\ActorRecognizerInterface` and be tagged as `actor_recognizer` in the container 
configuration:

```php
use Bamiz\UseCaseExecutorBundle\Actor\ActorRecognizerInterface;

class CustomerRecognizer implements ActorRecognizerInterface
{
}
```

```yaml
my_customer_recognizer:
    class: MyCustomerRecognizer
    tags:
        - { name: actor_recognizer }
```

`ActorRecognizerInterface` contains only one method: `recognizeActor()`. This method receives an initialized instance of 
Use Case Request that might be used to help determine which Actor is currently using your application. This method must 
return an instance of `ActorInterface`. It is not important whether this instance is created by the Recognizer, or
injected into the Recognizer and only returned when `recognizeActor()` is called. Currently it is only used to determine
whether the recognized Actor can execute desired Use Case by calling its `canExecute()` method.

## Executing Use Cases by Actors

If no Actor Recognizer is registered, any Use Case can be executed by anyone. Internally, the Use Case Executor will
receive from the internal Actor Recognizer an instance of `OmnipotentActor`, whose `canExecute` method always returns 
`true`. If at least one Actor Recognizer is registered, there are two ways to use them to recognize Actors: by name, or
try-them-all.
 
In order to recognize an Actor by its name, method `asActor()` must be called on the Use Case Executor, for example:

```php
$this->get('bamiz.use_case_executor')->asActor('my_actor')->execute('my_use_case', $input);
```

In this case, the internal Actor Recognizer will go through registered Actor Recognizers and execute `recognizeActor()`
method on each one, until it finds an instance of `ActorInterface` whose `getName()` method result is identical to
the specified Actor name. If no Actor with matching name can be found, an `UnrecognizedActorException` is thrown.

The try-them-all method looks exactly the same as executing Use Cases without registering any Actor Recognizers:

```php
$this->get('bamiz.use_case_executor')->execute('my_use_case', $input);
```

The Executor will go through all registered Actor Recognizers searching for Actors that can execute the specified
Use Case. It is possible that more than one Actor can execute the specified Use Case. When that happens, these
Actors are returned inside a single object which is an instance of `CompositeActor`. Its implementation of
`canExecute()` method calls `canExecute()` method on each contained Actor, until the result of this call equals `true`.
`CompositeActor` also allows you to check if it contains certain Actors and retrieve them by name, with methods
`hasActor()` and `getActor()` respectively.

In any case, after the Actor has been recognized, its `canExecute()` method is called to check if it's able to
execute the desired Use Case. If `canExecute()` returns `false`, the Executor throws 
`ActorCannotExecuteUseCaseException`. Otherwise, the execution continues as normal.

## Pros and Cons

### Pros

* The controller code is literally screaming who does what
* Identifying all roles present in the system is only a matter of finding all classes that implement `ActorInterface`
* All ACL-related code can be managed in a consistent way that is also reflects the different roles defined for your
system explicitly

### Cons

* Implementations of Actors must implement an interface from Use Case Bundle, thus couplings a part of your business
logic code to a third party library
* Extra overhead of recognizing Actors on call to `execute()` method of Use Case Executor 