<?php
declare(strict_types=1);

namespace VCR\PhpHttp;

use GuzzleHttp\Psr7\Response;
use h4cc\Multipart\ParserSelector;
use Http\Client\Common\Plugin;
use Http\Client\Promise\HttpFulfilledPromise;
use Http\Message\ResponseFactory;
use Psr\Http\Message\RequestInterface;
use VCR\Request as VCRRequest;
use VCR\Videorecorder;

class VcrPlugin implements Plugin
{
    /**
     * @var Videorecorder
     */
    private $videorecorder;

    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    /**
     * VcrPlugin constructor.
     */
    public function __construct(Videorecorder $videorecorder, ResponseFactory $responseFactory)
    {
        $this->videorecorder = $videorecorder;
        $this->responseFactory = $responseFactory;
    }

    public function handleRequest(RequestInterface $request, callable $next, callable $first)
    {
        if (!$this->videorecorder->isOn()) {
            return $next($request);
        }

        $vcrRequest = $this->createVcrRequest($request);
        $vcrResponse = $this->videorecorder->handleRequest($vcrRequest);

        $response = $this->responseFactory->createResponse(
            $vcrResponse->getStatusCode(),
            $vcrResponse->getStatusMessage(),
            $vcrResponse->getHeaders(),
            $vcrResponse->getBody(),
            $vcrResponse->getHttpVersion()
        );

        return new HttpFulfilledPromise($response);
    }

    private function createVcrRequest(RequestInterface $request)
    {
        $vcrRequest = new VCRRequest(
            $request->getMethod(),
            (string)$request->getUri(),
            array_map(
                function($headers) {
                    return $headers[0];
                },
                $request->getHeaders()
            )
        );

        $vcrRequest->setBody($request->getBody()->getContents());

        if (!$request->hasHeader('Content-Type')) {
            $vcrRequest->setHeader('Content-Type', '');
        }

        return $vcrRequest;
    }
}
