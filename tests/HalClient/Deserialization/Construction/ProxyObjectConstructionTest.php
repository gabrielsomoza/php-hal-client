<?php

/*
* This file is part of the Ekino HalClient package.
*
* (c) 2014 Ekino
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Ekino\HalClient\Deserialization\Construction
{
    use Ekino\HalClient\HalResource;
    use Ekino\HalClient\Proxy\HalResourceEntityInterface;
    use Http\Client\HttpClient;
    use Http\Message\MessageFactory\GuzzleMessageFactory;
    use JMS\Serializer\DeserializationContext;
    use JMS\Serializer\Metadata\ClassMetadata;
    use JMS\Serializer\VisitorInterface;
    use Proxy\Acme\Post;

    class ProxyObjectConstructionTest extends \PHPUnit_Framework_TestCase
    {
        public function testConstructorWithProxy()
        {
            /** @var VisitorInterface $visitor */
            $visitor = $this->getMock(VisitorInterface::class);
            $client = $this->getMock('Ekino\HalClient\HttpClient\HttpClientInterface');

            $context = new DeserializationContext();
            $resource = new HalResource($client);

            $constructor = new ProxyObjectConstruction();
            $object = $constructor->construct($visitor, new ClassMetadata('Acme\Post'), $resource, array(), $context);

            $this->assertInstanceOf(HalResourceEntityInterface::class, $object);
            $this->assertInstanceOf(HalResource::class, $object->getHalResource());
            $this->assertInstanceOf(Post::class, $object);
        }

        public function testConstructorWithoutProxy()
        {
            /** @var VisitorInterface $visitor */
            $visitor = $this->getMock('JMS\Serializer\VisitorInterface');
            $client = $this->getMock('Ekino\HalClient\HttpClient\HttpClientInterface');
            $context = new DeserializationContext();
            $resource = new HalResource($client);

            $constructor = new ProxyObjectConstruction();
            $object = $constructor->construct($visitor, new ClassMetadata('Acme\NoProxy'), $resource, array(), $context);

            $this->assertNotInstanceOf('Ekino\HalClient\Proxy\HalResourceEntityInterface', $object);
            $this->assertInstanceOf('Acme\NoProxy', $object);
        }

        public function testWithMultiplePatterns()
        {
            /** @var VisitorInterface $visitor */
            $visitor = $this->getMock(VisitorInterface::class);
            /** @var HttpClient $client */
            $client = $this->getMock(HttpClient::class);
            $context = new DeserializationContext();
            $resource = (new HalResource())->withClient($client)->withMessageFactory(new GuzzleMessageFactory());

            $constructor = new ProxyObjectConstruction(["{ns}\\Proxy\\{ln}", "Proxy\\{ns}\\{ln}"]);
            $object = $constructor->construct($visitor, new ClassMetadata('Acme\Post'), $resource, array(), $context);

            $this->assertInstanceOf(HalResourceEntityInterface::class, $object);
            $this->assertInstanceOf(HalResource::class, $object->getHalResource());
            $this->assertInstanceOf(Post::class, $object);
        }
    }
}

namespace Acme
{
    class Post {}

    class NoProxy {}
}

namespace Proxy\Acme
{
    use Ekino\HalClient\Proxy\HalResourceEntity;
    use Ekino\HalClient\Proxy\HalResourceEntityInterface;

    class Post extends \Acme\Post implements HalResourceEntityInterface
    {
        use HalResourceEntity;
    }
}

