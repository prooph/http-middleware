# HTTP Middleware
For every bus system a middleware exists and one Middleware to rule them all. If you use JSON or XML in the request body
for your message data, you have to convert this data to an array before you can call the middleware.

> Note: The middleware uses an array for the message data

## CommandMiddleware
The `CommandMiddleware` dispatches the message data to the command bus system. This middleware needs an request attribute
(`$request->getAttribute(\Prooph\HttpMiddleware\CommandMiddleware::NAME_ATTRIBUTE)`) called `prooph_command_name`.
This name is used for the `\Prooph\Common\Messaging\MessageFactory` to create the `\Prooph\Common\Messaging\Message`
object. The data for the command is extracted from the body of the request (`$request->getParsedBody()`) and must be an
array.

## QueryMiddleware
The `QueryMiddleware` is used to dispatch messages to the query bus system. Unlike the other middleware, the `QueryMiddleware`
supports sending multiple messages. Each query
is an object inside of the root `queries` object on the request payload and is indexed using a unique key. The response will include the query results
for each query, indexed using the same key as the request.  An example request/response would look like:

**Request**

`POST /query`

```json
{
    "queries": {
        "getUsers": {
            "prooph_query_name": "query:get-users",
            "filter": [
                "12"
            ]
        },
        "getTodos": {
            "prooph_query_name": "query:get-todos",
            "status": [
                "OPEN"
            ]
        }
    }
}
```

**Response**

```json
{
    "getUsers": [
        {
            "username": "John"
        }
    ],
    "getTodos": [
        {
            "task": "Write some docs"
        },
        {
            "task": "Build cool things"
        }
    ]
}
```

## EventMiddleware
The `EventMiddleware` dispatches the message data to the event bus system. This middleware needs an request attribute
(`$request->getAttribute(\Prooph\HttpMiddleware\EventMiddleware::NAME_ATTRIBUTE)`) called `prooph_event_name`.
This name is used for the `\Prooph\Common\Messaging\MessageFactory` to create the `\Prooph\Common\Messaging\Message`
object. The data for the event is extracted from the body of the request (`$request->getParsedBody()`) and must be an
array.

*Note:*

The `EventMiddleware` is commonly used for external event messages. An event comes from your domain, which was caused
by a command. It makes no sense to use this middleware in your project, if you only use a command bus with event sourcing.
In  this case you will use the [event store bus bridge)](https://github.com/prooph/event-store-bus-bridge "Marry CQRS with Event Sourcing").

## MessageMiddleware
The `MessageMiddleware` dispatches the message data to the suitable bus system depending on message type. The data
for the message is extracted from the body of the request (`$request->getParsedBody()`) and must be an array. The
`message_name` is extracted from the parsed body data. This name is used for the `\Prooph\Common\Messaging\MessageFactory`
to create the `\Prooph\Common\Messaging\Message` object. Your specific message data must be located under the `payload`
key. The value of `$request->getParsedBody()` is an array like this:

```
[
    'message_name' => 'command:register-user',
    'payload' => [
        'name' => 'prooph'
    ],
    'metadata' => []
    // other keys like uuid
]
```

**Important:** The provided message factory must handle all 3 types (command, query, event) of messages depending on
provided message name. It's recommended to use an prefix or something else in the message name to determine the correct
message type.
