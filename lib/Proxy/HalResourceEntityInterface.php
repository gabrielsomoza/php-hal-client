<?php

/*
* This file is part of the Ekino HalClient package.
*
* (c) 2014 Ekino
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Ekino\HalClient\Proxy;

use Ekino\HalClient\HalResource;
use JMS\Serializer\Serializer;

interface HalResourceEntityInterface
{
    /**
     * @param HalResource $resource
     */
    public function setHalResource(HalResource $resource);

    /**
     * @return Resource
     */
    public function getHalResource();

    /**
     * @param Serializer $serializer
     */
    public function setHalSerializer(Serializer $serializer);

    /**
     * @return Serializer
     */
    public function getHalSerializer();
}
