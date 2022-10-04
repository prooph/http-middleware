# Laminas Expressive integration

The [Laminas Expressive](https://github.com/laminas/laminas-expressive) integration is very easy, because you can use the predefined factories and configuration examples of the specific prooph component.

> Take a look at the Laminas Expressive [prooph components in action](https://github.com/prooph/proophessor-do "proophessor-do example app") example app.

## Routes

Here is an example for the `AuraRouter` to call the `CommandMiddleware` for the `register-user` command.

```php
// routes.php

/** @var \Laminas\Expressive\Application $app */
$app->post('/api/commands/register-user', [
    \Prooph\HttpMiddleware\CommandMiddleware::class,
], 'command::register-user')
    ->setOptions([
        'values' => [
            \Prooph\HttpMiddleware\CommandMiddleware::NAME_ATTRIBUTE => \Prooph\ProophessorDo\Model\User\Command\RegisterUser::class,
        ],
    ]);
```

## Metadata Gatherer

QueryMiddleware, CommandMiddleware and EventMiddleware have a MetadataGatherer injected that is capable of retrieving attributes derived from the ServerRequestInterface and pass those with messages as metadata.

By default a Noop (returns an empty array) instance is used, but it is very easy to change that.

First write an implementation of MetadataGatherer;

```php
namespace My\HttpMiddleware;

use Psr\Http\Message\ServerRequestInterface;
use Prooph\HttpMiddleware\MetadataGatherer;

final class MyMetadataGatherer implements MetadataGatherer
{
    /**
     * @inheritdoc
     */
    public function getFromRequest(ServerRequestInterface $request) {
        return [
            'identity' => $request->getAttribute('identity'),
            'request_uuid' => $request->getAttribute('request_uuid')
        ];
    }
}

```

Then define it in container and prooph configuration;

```php
return [
    'dependencies' => [
        'factories' => [
            \My\HttpMiddleware\MyMetadataGatherer::class => \ Laminas\ServiceManager\Factory\InvokableFactory::class
        ],
    ],
    'prooph' => [
        'middleware' => [
            'query' => [
                'metadata_gatherer' => \My\HttpMiddleware\MyMetadataGatherer::class
            ],
        ],
    ],
    ...
```
