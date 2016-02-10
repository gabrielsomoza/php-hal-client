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

class ResourceCollection implements \Iterator, \Countable, \ArrayAccess
{
    protected $iterator;

    protected $client;

    protected $updateIterator = true;

    /**
     * @param HttpClient $client
     * @param array               $collection
     */
    public function __construct(array $collection = array(), HttpClient $client = null)
    {
        $this->client = $client ?: HttpClientDiscovery::find();
        $this->iterator = new \ArrayIterator($collection);
    }

    /**
     * @param \Iterator $collection
     * @param HttpClient $client
     * @param bool $updateIterator if the Iterator should be updated to wrap the data inside HalResource instances
     *
     * @return ResourceCollection
     */
    public static function createFromIterator(\Iterator $collection, $updateIterator = false, HttpClient $client = null)
    {
        $col = new self([], $client);
        $col->iterator = $collection;
        $col->updateIterator = $updateIterator;

        return $col;
    }

    /**
     * @param null|array $data
     *
     * @return Resource
     */
    protected function createResource($data)
    {
        if (null === $data) {
            return null;
        }

        return HalResource::create($data, $this->client);
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        $resource = $this->iterator->current();
        if (null === $resource) {
            return null;
        }

        if ($this->updateIterator && !$resource instanceof HalResource) {
            $resource = $this->createResource($resource);
            $this->iterator->offsetSet($this->iterator->key(), $resource);
        }

        return $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->iterator->next();
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->iterator->key();
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->iterator->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->iterator->rewind();
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        if (!$this->iterator instanceof \Countable) {
            throw new \RuntimeException('Operation not allowed');
        }

        return count($this->iterator);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        if (!$this->iterator instanceof \ArrayAccess) {
            throw new \RuntimeException('Operation not allowed');
        }

        return isset($this->iterator[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        if (!$this->iterator instanceof \ArrayAccess) {
            throw new \RuntimeException('Operation not allowed');
        }

        $resource = $this->iterator->offsetGet($offset);
        if (null === $resource) {
            return null;
        }

        if ($this->updateIterator && !$resource instanceof HalResource) {
            $resource = $this->createResource($resource);
            $this->iterator->offsetSet($offset, $resource);
        }

        return $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        throw new \RuntimeException('Operation not allowed');
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        throw new \RuntimeException('Operation not allowed');
    }
}
