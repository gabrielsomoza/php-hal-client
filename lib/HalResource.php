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

class HalResource implements \ArrayAccess
{
    protected $properties;

    protected $links;

    protected $embedded;

    protected $curies;

    protected $client;

    private $messageFactory;
    /**
     * @var array
     */
    private $defaultHeaders;

    /**
     * @param array $properties
     * @param array $links
     * @param array $embedded
     * @param HttpClient|null $client
     * @param MessageFactory $messageFactory
     * @param array $defaultHeaders
     */
    public function __construct(
        $properties = array(),
        $links = array(),
        $embedded = array(),
        HttpClient $client = null,
        MessageFactory $messageFactory = null,
        array $defaultHeaders = []
    ) {
        $this->properties = $properties;
        $this->links      = $links;
        $this->embedded   = $embedded;

        $this->client = $client;
        $this->messageFactory = $messageFactory;

        $this->parseCuries();
        $this->defaultHeaders = $defaultHeaders;
    }

    /**
     * withClient
     * @param HttpClient $client
     * @return static
     */
    public function withClient(HttpClient $client)
    {
        return new static(
            $this->properties,
            $this->links,
            $this->embedded,
            $client,
            $this->messageFactory,
            $this->defaultHeaders
        );
    }

    /**
     * withMessageFactory
     * @param MessageFactory $messageFactory
     * @return static
     */
    public function withMessageFactory(MessageFactory $messageFactory)
    {
        return new static(
            $this->properties,
            $this->links,
            $this->embedded,
            $this->client,
            $messageFactory,
            $this->defaultHeaders
        );
    }

    /**
     * withDefaultHeaders
     * @param array $defaultHeaders
     * @return static
     */
    public function withDefaultHeaders(array $defaultHeaders)
    {
        return new static(
            $this->properties,
            $this->links,
            $this->embedded,
            $this->client,
            $this->messageFactory,
            $defaultHeaders
        );
    }

    /**
     * @return array
     */
    public function getEmbedded()
    {
        return $this->embedded;
    }

    /**
     * @return array
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * Reloads the HalResource by using the self reference
     *
     * @throws \RuntimeException
     */
    public function refresh()
    {
        $link = $this->getLink('self');

        if (!$link) {
            throw new \RuntimeException('Invalid resource, not `self` reference available');
        }

        $r = $this->getResource($link);

        $this->properties = $r->getProperties();
        $this->links      = $r->getLinks();
        $this->embedded   = $r->getEmbedded();

        $this->parseCuries();
    }

    /**
     * @param $name
     *
     * @return Link
     */
    public function getLink($name)
    {
        if (!array_key_exists($name, $this->links)) {
            return null;
        }

        if (!$this->links[$name] instanceof Link) {
            $this->links[$name] = new Link(array_merge(array('name' => $name), $this->links[$name]));
        }

        return $this->links[$name];
    }

    /**
     * @param $name
     *
     * @return Curie
     */
    public function getCurie($name)
    {
        if (!array_key_exists($name, $this->curies)) {
            return null;
        }

        return $this->curies[$name];
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * parseCuries
     * @return void
     */
    protected function parseCuries()
    {
        $this->curies = array();

        if (!array_key_exists('curies', $this->links) || empty($this->links['curies'])) {
            return;
        }

        $curies = $this->links['curies'];
        $firstItem = current($this->links['curies']);
        if (!is_array($firstItem)) {
            // this is a single curie and is therefore not in an array (e.g. Apigility / ZF-Hal)
            $curies = [$curies];
        }

        foreach ($curies as $curie) {
            $this->curies[$curie['name']] = new Curie($curie);
        }
    }

    /**
     * @param $name
     *
     * @return HalResource|ResourceCollection|null
     */
    public function get($name)
    {
        if (array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        }

        if (!array_key_exists($name, $this->embedded)) {
            if (!$this->buildResourceValue($name)) {
                return null;
            }
        }

        return $this->getEmbeddedValue($name);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function has($name)
    {
        return $this->hasProperty($name) || $this->hasLink($name) || $this->hasEmbedded($name);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasLink($name)
    {
        return isset($this->links[$name]);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasProperty($name)
    {
        return isset($this->properties[$name]);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasEmbedded($name)
    {
        return isset($this->embedded[$name]);
    }

    /**
     * @param $name
     *
     * @return boolean
     */
    protected function buildResourceValue($name)
    {
        $link = $this->getLink($name);

        if (!$link) {
            return false;
        }

        $this->embedded[$name] = $this->getResource($link);

        return true;
    }

    /**
     * @param $name
     *
     * @return Resource|ResourceCollection
     */
    protected function getEmbeddedValue($name)
    {
        if ( !is_object($this->embedded[$name])) {
            if (is_integer(key($this->embedded[$name])) || empty($this->embedded[$name])) {
                $this->embedded[$name] = new ResourceCollection($this->embedded[$name], $this->getClient());
            } else {
                $this->embedded[$name] = self::create($this->embedded[$name], $this->getClient());
            }
        }

        return $this->embedded[$name];
    }

    /**
     * @param array $data
     * @param HttpClient $client
     *
     * @param MessageFactory $messageFactory
     * @param array $defaultHeaders
     * @return Resource
     */
    public static function create(
        array $data,
        HttpClient $client = null,
        MessageFactory $messageFactory = null,
        array $defaultHeaders = []
    ) {
        $links    = isset($data['_links']) ? $data['_links'] : array();
        $embedded = isset($data['_embedded']) ? $data['_embedded'] : array();

        unset(
            $data['_links'],
            $data['_embedded']
        );

        return new self($data, $links, $embedded, $client, $messageFactory, $defaultHeaders);
    }

    /**
     * Create a resource from link href.
     *
     * @param Link $link
     * @param array $variables Required if the link is templated
     * @return HalResource
     */
    public function getResource(Link $link, array $variables = array())
    {
        $href = $link->getHref($variables);
        $request = $this->getMessageFactory()->createRequest('get', $href, $this->defaultHeaders);
        $response = $this->getClient()->sendRequest($request);

        if (!$response instanceof ResponseInterface) {
            throw new \RuntimeException(sprintf('HttpClient does not return a valid HttpResponse object, given: %s', $response));
        }

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('HttpClient does not return a status code, given: %s', $response->getStatusCode()));
        }

        return EntryPoint::parse($response, $this->getClient(), $this->getMessageFactory(), $this->defaultHeaders);
    }

    /**
     * Returns the href of curie assoc given by link.
     *
     * @param Link $link
     *
     * @return string
     */
    public function getCurieHref(Link $link)
    {
        if (null === $link->getNCName() || null === $this->getCurie($link->getNCName())) {
            return null;
        }

        return $this->getCurie($link->getNCName())->getHref(array('rel' => $link->getReference()));
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
       return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        throw new \RuntimeException('Operation not available');
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        throw new \RuntimeException('Operation not available');
    }

    /**
     * getClient
     * @return HttpClient|null
     */
    protected function getClient()
    {
        if (!$this->client) {
            $this->client = HttpClientDiscovery::find();
        }
        return $this->client;
    }

    /**
     * getMessageFactory
     * @return MessageFactory
     */
    protected function getMessageFactory()
    {
        if (!$this->messageFactory) {
            $this->messageFactory = MessageFactoryDiscovery::find();
        }
        return $this->messageFactory;
    }
}
