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

use GuzzleHttp\Psr7\Response;
use Http\Client\HttpClient;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class EntryPointTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException \RuntimeException
     */
    public function testInvalidContentType()
    {
        $client = $this->getMock(HttpClient::class);
        $client->expects($this->once())->method('sendRequest')->will($this->returnValue(new Response(200, array(
            'Content-Type' => 'application/json'
        )), '{}'));

        $messageFactory = new GuzzleMessageFactory();

        $entryPoint = new EntryPoint('/', [], $client, $messageFactory);

        $entryPoint->get();
    }

    public function testVersionHeader()
    {
        $client = $this->getMock(HttpClient::class);
        $client->expects($this->once())->method('sendRequest')->will($this->returnCallback(function ($url) {
            return new Response(200, array(
                'Content-Type' => 'application/hal+json;version=42'
            ), json_encode([]));
        }));

        $messageFactory = new GuzzleMessageFactory();

        (new EntryPoint('/', [], $client, $messageFactory))->get();
    }

    public function testGetResource()
    {
        $client = $this->getMock(HttpClient::class);
        $client->expects($this->any())->method('sendRequest')
            ->will($this->returnCallback(function (RequestInterface $url) {
                if ($url->getUri() == '/') {
                    return new Response(200, array(
                        'Content-Type' => 'application/hal+json'
                    ), file_get_contents(__DIR__ . '/../fixtures/entry_point.json'));
                }

                if ($url->getUri() == 'http://propilex.herokuapp.com/documents') {
                    return new Response(200, array(
                        'Content-Type' => 'application/hal+json'
                    ), file_get_contents(__DIR__ . '/../fixtures/documents.json'));
                }
            }));

        $messageFactory = new GuzzleMessageFactory();

        $entryPoint = new EntryPoint('/', [], $client, $messageFactory);

        $resource = $entryPoint->get();

        $this->assertInstanceOf(HalResource::class, $resource);
        $this->assertCount(1, $resource->getProperties());
        $this->assertEmpty($resource->getEmbedded());

        $link = $resource->getLink('p:documents');

        $this->assertInstanceOf(Link::class, $link);

        $this->assertEquals($link->getHref(), 'http://propilex.herokuapp.com/documents');

        $this->assertNull($resource->get('fake'));

        $resource = $resource->get('p:documents');

        $this->assertInstanceOf(HalResource::class, $resource);

        $expected = array(
            "page" => 1,
            "limit" => 10,
            "pages" => 1,
        );

        $this->assertEquals($expected, $resource->getProperties());
        $this->assertEquals(1, $resource->get('page'));
        $this->assertEquals(10, $resource->get('limit'));
        $this->assertEquals(1, $resource->get('pages'));

        $collection = $resource->get('documents');

        $this->assertInstanceOf(ResourceCollection::class, $collection);

        $this->assertEquals(4, $collection->count());

        foreach ($collection as $child) {
            $this->assertInstanceOf(HalResource::class, $child);
            $this->assertNotNull($child->get('title'));
            $this->assertNotNull($child->get('body'));
            $this->assertNotNull($child->get('id'));
            $this->assertNull($child->get('fake'));
        }


        $this->assertEquals('teste', $collection[1]->get('title'));
    }
}
