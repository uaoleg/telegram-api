<?php

declare(strict_types=1);

namespace unreal4u\TelegramAPI;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use unreal4u\TelegramAPI\Exceptions\ClientException;
use unreal4u\TelegramAPI\InternalFunctionality\TelegramResponse;

/**
 * @param Promise $promise
 * @return PromiseInterface
 */
function reactAdapt(Promise $promise): PromiseInterface
{
    $deferred = new Deferred();

    $promise->onResolve(function ($error = null, $result = null) use ($deferred) {
        if ($error) {
            $deferred->reject($error);
        } else {
            $deferred->resolve($result);
        }
    });

    return $deferred->promise();
}

class HttpClientRequestHandlerAmp implements RequestHandlerInterface
{
    /**
     * @var \Amp\Http\Client\HttpClient
     */
    private $client;

    public function __construct()
    {
        $this->client = HttpClientBuilder::buildDefault();
    }

    /**
     * @param string $uri
     *
     * @return PromiseInterface
     */
    public function get(string $uri): PromiseInterface
    {
        return $this->processRequest(new Request($uri));
    }

    /**
     * @param string $uri
     * @param array $formFields
     *
     * @return PromiseInterface
     */
    public function post(string $uri, array $formFields): PromiseInterface
    {
        $request = new Request($uri, 'POST');

        if (!empty($formFields['headers'])) {
            $request->setHeaders($formFields['headers']);
        }

        if (!empty($formFields['body'])) {
            $request->setBody($formFields['body']);
        }

        return $this->processRequest($request);
    }

    /**
     * @param Request $request
     *
     * @return PromiseInterface
     */
    public function processRequest(Request $request): PromiseInterface
    {
        return reactAdapt(\Amp\call(function () use ($request) {
            /** @var Response $response */
            $response = yield $this->client->request($request);

            if ($response->getStatus() >= 400) {
                throw new ClientException($response->getReason(), $response->getStatus());
            }

            return new TelegramResponse(yield $response->getBody()->buffer(), $response->getHeaders());
        }));
    }
}
