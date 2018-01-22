<?php

namespace VCR\Example;

use Http\Client\Common\HttpMethodsClient;
use Http\Client\Common\PluginClient;
use Http\Client\Curl\Client;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use org\bovigo\vfs\vfsStream;
use Psr\Http\Message\ResponseInterface;
use VCR\PhpHttp\VcrPlugin;
use VCR\VCRFactory;
use VCR\Videorecorder;

/**
 * Tests example request.
 */
class PhpHttpTest extends \PHPUnit_Framework_TestCase
{
    const TEST_GET_URL = 'http://httpbin.org/get';
    const TEST_POST_URL = 'http://httpbin.org/post';
    const TEST_POST_BODY = '{"foo":"bar"}';

    protected $ignoreHeaders = array(
        'Accept',
        'Connect-Time',
        'Total-Route-Time',
        'X-Request-Id',
        'X-Processed-Time',
        'Date'
    );

    public function setUp()
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testRequestGETDirect()
    {
        $this->assertValidGETResponse($this->requestGET());
    }

    public function testRequestGETIntercepted()
    {
        $this->assertValidGETResponse($this->requestGETIntercepted());
    }

    public function testRequestGETDirectEqualsIntercepted()
    {
        $this->assertResponseEquals($this->requestGET(), $this->requestGETIntercepted());
    }

    public function testRequestGETInterceptedIsRepeatable()
    {
        $this->assertResponseEquals($this->requestGETIntercepted(), $this->requestGETIntercepted());
    }

    public function testRequestPOSTDirect()
    {
        $this->assertValidPOSTResponse($this->requestPOST());
    }

    public function testRequestPOSTIntercepted()
    {
        $this->assertValidPOSTResponse($this->requestPOSTIntercepted());
    }

    public function testRequestPOSTDirectEqualsIntercepted()
    {
        $this->assertResponseEquals($this->requestPOST(), $this->requestPOSTIntercepted());
    }

    public function testRequestPOSTInterceptedIsRepeatable()
    {
        $this->assertResponseEquals($this->requestPOSTIntercepted(), $this->requestPOSTIntercepted());
    }

    protected function requestGET()
    {
        /** @var Videorecorder $videoRecorder */
        $videoRecorder = VCRFactory::get('VCR\Videorecorder');

        $client = $this->createMethodsClient($videoRecorder);

        return $client->get(self::TEST_GET_URL);
    }

    protected function requestPOST()
    {
        $videoRecorder = VCRFactory::get('VCR\Videorecorder');

        $client = $this->createMethodsClient($videoRecorder);

        return $client->post(self::TEST_POST_URL, [
            'Content-Type' => 'application/json',
        ], self::TEST_POST_BODY);
    }

    protected function requestGETIntercepted()
    {
        /** @var Videorecorder $videoRecorder */
        $videoRecorder = VCRFactory::get('VCR\Videorecorder');

        $videoRecorder->turnOn();
        $videoRecorder->insertCassette('test-cassette.yml');

        $client = $this->createMethodsClient($videoRecorder);

        $response = $client->get(self::TEST_GET_URL);

        $videoRecorder->turnOff();

        return $response;
    }

    protected function requestPOSTIntercepted()
    {
        /** @var Videorecorder $videoRecorder */
        $videoRecorder = VCRFactory::get('VCR\Videorecorder');

        $videoRecorder->turnOn();
        $videoRecorder->insertCassette('test-cassette.yml');

        $client = $this->createMethodsClient($videoRecorder);

        $response = $client->post(self::TEST_POST_URL, [
            'Content-Type' => 'application/json',
        ], self::TEST_POST_BODY);

        $videoRecorder->turnOff();

        return $response;
    }

    protected function assertValidGETResponse(ResponseInterface $response)
    {
        $info = json_decode($response->getBody()->getContents(), true);

        $this->assertInternalType('array', $info, 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, "Key 'url' not found.");
        $this->assertEquals(self::TEST_GET_URL, $info['url'], "Value for key 'url' wrong.");
        $this->assertArrayHasKey('headers', $info, "Key 'headers' not found.");
        $this->assertInternalType('array', $info['headers'], 'Headers is not an array.');
    }

    protected function assertValidPOSTResponse(ResponseInterface $response)
    {
        $info = json_decode($response->getBody()->getContents(), true);

        $this->assertInternalType('array', $info, 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, "Key 'url' not found.");
        $this->assertEquals(self::TEST_POST_URL, $info['url'], "Value for key 'url' wrong.");
        $this->assertArrayHasKey('headers', $info, "Key 'headers' not found.");
        $this->assertInternalType('array', $info['headers'], 'Headers is not an array.');
        $this->assertEquals(self::TEST_POST_BODY, $info['data'], 'Correct request body was not sent.');
    }

    protected function assertResponseEquals(ResponseInterface $a, ResponseInterface $b)
    {
        $this->assertEquals($a->getStatusCode(), $b->getStatusCode());
        $this->assertEquals($a->getProtocolVersion(), $b->getProtocolVersion());
        $this->assertEquals($a->getReasonPhrase(), $b->getReasonPhrase());
        $this->assertEquals($a->getBody()->getContents(), $b->getBody()->getContents());

        $aHeaders = $a->getHeaders();
        $bHeaders = $b->getHeaders();

        foreach ($this->ignoreHeaders as $header) {
            unset($aHeaders[$header]);
            unset($bHeaders[$header]);
        }

        $this->assertEquals($aHeaders, $bHeaders);
    }

    private function createMethodsClient(Videorecorder $videoRecorder)
    {
        $messageFactory = new GuzzleMessageFactory();
        $vcrPlugin = new VcrPlugin($videoRecorder, $messageFactory);
        return new HttpMethodsClient(new PluginClient(new Client(), [$vcrPlugin]), $messageFactory);
    }
}
