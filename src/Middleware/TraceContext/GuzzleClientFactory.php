<?php
declare(strict_types=1);

namespace OtelInstrumentation\Middleware\TraceContext;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

/**
 * Factory that creates a GuzzleHttp\Client with GuzzleMiddleware pre-wired.
 *
 * Requires guzzlehttp/guzzle ^7.0 in your application's composer.json.
 *
 * DI usage in Application::services():
 *
 *   use GuzzleHttp\Client;
 *   use OtelInstrumentation\Middleware\TraceContext\GuzzleClientFactory;
 *
 *   public function services(ContainerInterface $container): void
 *   {
 *       $container->add(Client::class, function () {
 *           return GuzzleClientFactory::create([
 *               'base_uri' => Configure::read('ExternalApi.baseUri'),
 *           ]);
 *       });
 *   }
 *
 * With CLIENT span creation enabled:
 *
 *   GuzzleClientFactory::create(['base_uri' => '...'], createSpan: true);
 */
class GuzzleClientFactory
{
    /**
     * @param array<string, mixed> $clientConfig Guzzle client config. Do not include 'handler'.
     * @param bool $createSpan Whether to also create a KIND_CLIENT span per request.
     */
    public static function create(
        array $clientConfig = [],
        bool $createSpan = false,
        ?string $spanName = null,
    ): Client {
        if (isset($clientConfig['handler'])) {
            throw new \InvalidArgumentException("'handler' key is managed by GuzzleClientFactory and must not be provided.");
        }

        $stack = HandlerStack::create();
        $stack->push(new GuzzleMiddleware($createSpan, $spanName), 'traceparent');

        return new Client(array_merge($clientConfig, ['handler' => $stack]));
    }
}
