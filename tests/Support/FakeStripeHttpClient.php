<?php

namespace Gnarhard\StripeStorefront\Tests\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Records outgoing Stripe API requests and replies with canned JSON so the real
 * SDK request/response pipeline is exercised without hitting the network.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
    public array $requests = [];

    /** @var array<string, array{0: int, 1: array<string, mixed>}> */
    private array $nextPageResponses = [];

    /**
     * @param  array<string, array{0: int, 1: array<string, mixed>}>  $responses  keyed by "METHOD /v1/path"
     */
    public function __construct(private array $responses = []) {}

    /**
     * Reply with $response when the SDK requests the page after the first one (starting_after is set).
     *
     * @param  array{0: int, 1: array<string, mixed>}  $response
     */
    public function respondToNextPage(string $route, array $response): void
    {
        $this->nextPageResponses[$route] = $response;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = parse_url($absUrl, PHP_URL_PATH);

        $this->requests[] = [
            'method' => strtoupper($method),
            'url' => $path,
            'params' => $params,
        ];

        $route = strtoupper($method).' '.$path;
        $responses = isset($params['starting_after']) ? $this->nextPageResponses : $this->responses;

        [$status, $body] = $responses[$route] ?? [404, [
            'error' => ['type' => 'invalid_request_error', 'message' => "No fake response for {$method} {$path}"],
        ]];

        return [json_encode($body), $status, ['request-id' => 'req_fake']];
    }

    /**
     * @return array{method: string, url: string, params: array<string, mixed>}|null
     */
    public function lastRequestTo(string $method, string $path): ?array
    {
        return collect($this->requests)
            ->last(fn (array $request) => $request['method'] === $method && $request['url'] === $path);
    }
}
