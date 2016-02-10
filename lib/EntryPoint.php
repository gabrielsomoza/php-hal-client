<?php

/*
* This file is part of the Ekino HalClient package.
*
* (c) 2014 Ekino
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Ekino\HalClient;

use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\MessageFactory;
use Psr\Http\Message\ResponseInterface;

class EntryPoint
{
    /** @var string */
    protected $url;

    /** @var array */
    protected $headers;

    /** @var HttpClient */
    protected $client;

    /** @var Resource */
    protected $resource;

    /**
     * @param string $url
     * @param array $headers
     * @param HttpClient $httpClient
     * @param MessageFactory $messageFactory
     */
    public function __construct(
        $url,
        array $headers = array(),
        HttpClient $httpClient = null,
        MessageFactory $messageFactory = null
    ) {
        $this->url     = $url;
        $this->headers = $headers;
        $this->client  = $httpClient ?: HttpClientDiscovery::find();
        $this->messageFactory = $messageFactory ?: MessageFactoryDiscovery::find();
        $this->resource = false;
    }

    /**
     * @param ResponseInterface $response
     * @param HttpClient|null $client
     * @param MessageFactory $messageFactory
     * @return HalResource
     */
    public static function parse(
        ResponseInterface $response,
        HttpClient $client = null,
        MessageFactory $messageFactory = null
    ) {
        if ($response->hasHeader('Content-Type')
            && substr($response->getHeader('Content-Type')[0], 0, 20) !== 'application/hal+json')
        {
            throw new \RuntimeException('Invalid content type');
        }

        $data = @json_decode($response->getBody(), true);

        if ($data === null) {
            throw new \RuntimeException('Invalid JSON format');
        }

        $client = $client ?: HttpClientDiscovery::find();

        return HalResource::create($data, $client, $messageFactory);
    }

    /**
     * @param string $name
     *
     * @return HalResource
     */
    public function get($name = null)
    {
        $this->initialize();

        if ($name) {
            return $this->resource->get($name);
        }

        return $this->resource;
    }

    /**
     * Initialize the resource.
     */
    protected function initialize()
    {
        if ($this->resource) {
            return;
        }

        $request = $this->messageFactory->createRequest('get', $this->url, $this->headers);

        $this->resource = static::parse($this->client->sendRequest($request), $this->client, $this->messageFactory);
    }
}
