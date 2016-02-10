<?php

/*
* This file is part of the Ekino HalClient package.
*
* (c) 2014 Ekino
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Exporter\Test;

use Ekino\HalClient\Curie;
use Ekino\HalClient\HalResource;
use GuzzleHttp\Psr7\Response;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Psr\Http\Message\RequestInterface;

class HalResourceTest extends \PHPUnit_Framework_TestCase
{

    public function testHandler()
    {
        /** @var HttpClient $client */
        $client = $this->getMock(HttpClient::class);
        (new HalResource())->withClient($client);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage HttpClient does not return a status code, given: 500
     */
    public function testInvalidStatus()
    {
        /** @var MockObject|HttpClient $client */
        $client = $this->getMock(HttpClient::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->will($this->returnValue(new Response(500)));

        $messageFactory = new GuzzleMessageFactory();

        $resource = (new HalResource(array(), array(
            'foo' => array(
                'href' => 'http://fake.com/foo',
                'title' => 'foo'
            )
        )))->withClient($client)
            ->withMessageFactory($messageFactory);

        $resource->get('foo');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Invalid resource, not `self` reference available
     */
    public function testRefreshWithInvalidSelfReference()
    {
        $client = $this->getMock('Ekino\HalClient\HttpClient\HttpClientInterface');
        $client->expects($this->never())->method('get');

        $resource = new HalResource($client);

        $resource->refresh();
    }

    public function testRefresh()
    {
        /** @var MockObject|HttpClient $client */
        $client = $this->getMock(HttpClient::class);
        $client->expects($this->exactly(1))
            ->method('sendRequest')
            ->will($this->returnCallback(function (RequestInterface $url) {
                if ($url->getUri() == 'http://propilex.herokuapp.com') {
                    return new Response(200, array(
                        'Content-Type' => 'application/hal+json'
                    ), file_get_contents(__DIR__ . '/../fixtures/entry_point.json'));
                }
                return null;
            }));

        $messageFactory = new GuzzleMessageFactory();

        $resource = (new HalResource(array(), array(
            'self' => array('href' => 'http://propilex.herokuapp.com')
        )))->withClient($client)
            ->withMessageFactory($messageFactory);

        $this->assertNull($resource->get('field'));
        $resource->refresh();

        $this->assertEquals($resource->get('field'), 'value');
    }

    /**
     * testParseCuries
     * @param $curies
     * @return void
     * @dataProvider parseCuriesProvider
     */
    public function testParseCuries($curies)
    {
        $resource = (new HalResource([], [
            'curies' => $curies
        ]));

        $klass = new \ReflectionClass($resource);

        $method = $klass->getMethod('parseCuries');
        $method->setAccessible(true);
        $prop = $klass->getProperty('curies');
        $prop->setAccessible(true);

        $method->invoke($resource);

        $curies = $prop->getValue($resource);
        foreach ($curies as $curie) {
            $this->assertInstanceOf(Curie::class, $curie);
        }
    }

    public function parseCuriesProvider()
    {
        return [
            [
                ['name' => 'test1', 'href' => 'http://foo.bar'],
            ],
            [
                [
                    ['name' => 'test1', 'href' => 'http://foo.bar'],
                ]
            ],
            [
                [
                    ['name' => 'test1', 'href' => 'http://foo.bar'],
                    ['name' => 'baz-test', 'href' => 'http://foo.bar.baz'],
                ]
            ],
        ];
    }
}
