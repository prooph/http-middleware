# Interop + Factories

Instead of providing a module, a bundle, a bridge or similar framework integration `prooph/http-middleware` ships with *interop factories*.

## Factory-Driven Middleware Creation

The concept behind these factories is simple but powerful. It allows us to provide you with bootstrapping logic for
the message buses without the need to rely on a specific framework. However, the factories have three requirements.

### Requirements

1. Your Inversion of Control container must implement the [interop-container interface](https://github.com/container-interop/container-interop).
2. [interop-config](https://github.com/sandrokeil/interop-config) must be installed
3. The application configuration should be registered with the service id `config` in the container.

*Note: Don't worry, if your environment doesn't provide the requirements. You can always bootstrap a middleware by hand. Just look at the factories for inspiration in this case.*

## Customizing via Configuration

In the `config` folder of `prooph/http-middleware` you will find example configuration files.
Configuration is a simple PHP array flavored with some comments to help you understand the structure.

Now follow the simple steps below to integrate `prooph/http-middleware` in your framework and/or application.

1. Merge configuration into your application config either by hand or by using the mechanism of your framework.
2. Customize the configuration so that it meet your needs. The comments in the config file will tell you more.
3. (Only required if not done by your framework) Make your application config available as a service in the Inversion of Control container. Use `config` as the service id (common id for application config).
4. Register the middleware as services in your IoC container and use the located in `src/Container` to create the different middleware.
How you can register a middlware depends on your container. Some containers like [zend-servicemanager](https://github.com/zendframework/zend-servicemanager)
or [pimple-interop](https://github.com/moufmouf/pimple-interop) allow you to map a service id to an `invokable factory`.
If you use such an IoC container you are lucky. In this case you can use the `prooph/http-middleware` factories as-is.
We recommend using `Prooph\Middleware\<CommandMiddleware/EventMiddleware/QueryMiddleware::class/MessageMiddleware::class` as service id.

*Note: If you're still unsure how to do it you might have a look at the `CommandMiddlewareFactoryTest` located in the `tests/Container` folder.
