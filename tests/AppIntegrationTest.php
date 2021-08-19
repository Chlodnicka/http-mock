<?php

namespace InterNations\Component\HttpMock\Tests;

use GuzzleHttp\Client;
use InterNations\Component\HttpMock\Server;
use InterNations\Component\Testing\AbstractTestCase;
use Opis\Closure\SerializableClosure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @large
 * @group integration
 */
class AppIntegrationTest extends AbstractTestCase
{
    private static Server $server1;
    private Client $client;

    public static function setUpBeforeClass(): void
    {
        static::$server1 = new Server(HTTP_MOCK_PORT, HTTP_MOCK_HOST);
        static::$server1->start();
    }

    public static function tearDownAfterClass(): void
    {
        static::assertSame('', (string)static::$server1->getOutput(), (string)static::$server1->getOutput());
        static::assertSame('', (string)static::$server1->getErrorOutput(), (string)static::$server1->getErrorOutput());
        static::$server1->stop();
    }

    public function setUp(): void
    {
        static::$server1->clean();
        $this->client = static::$server1->getClient();
    }

    public function testSimpleUseCase(): void
    {
        $response = $this->client->post(
            '/_expectation',
            $this->createExpectationParams(
                [
                    static function ($request) {
                        return $request instanceof Request;
                    }
                ],
                new Response('fake body', 200)
            )
        );
        $this->assertSame('', (string)$response->getBody());
        $this->assertSame(201, $response->getStatusCode());

        $response = $this->client->post('/foobar', ['X-Special' => 1], ['post' => 'data']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('fake body', (string)$response->getBody());

        $response = $this->client->get('/_request/latest');

        $request = $this->parseRequestFromResponse($response);
        $this->assertSame('1', (string)$request->getHeader('X-Special'));
        $this->assertSame('post=data', (string)$request->getBody());
    }

    public function testRecording()
    {
        $this->client->delete('/_all');

        $this->assertSame(404, $this->client->get('/_request/latest')->getStatusCode());
        $this->assertSame(404, $this->client->get('/_request/0')->getStatusCode());
        $this->assertSame(404, $this->client->get('/_request/first')->getStatusCode());
        $this->assertSame(404, $this->client->get('/_request/last')->getStatusCode());

        $this->client->get('/req/0');
        $this->client->get('/req/1');
        $this->client->get('/req/2');
        $this->client->get('/req/3');

        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->get('/_request/last'))->getPath()
        );
        $this->assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->get('/_request/0'))->getPath()
        );
        $this->assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->get('/_request/1'))->getPath()
        );
        $this->assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->get('/_request/2'))->getPath()
        );
        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->get('/_request/3'))->getPath()
        );
        $this->assertSame(404, $this->client->get('/_request/4')->getStatusCode());

        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->delete('/_request/last'))->getPath()
        );
        $this->assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->delete('/_request/first'))->getPath()
        );
        $this->assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->get('/_request/0'))->getPath()
        );
        $this->assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->get('/_request/1'))->getPath()
        );
        $this->assertSame(404, $this->client->get('/_request/2')->getStatusCode());
    }

    public function testErrorHandling()
    {
        $this->client->delete('/_all');

        $response = $this->client->post('/_expectation', null, ['matcher' => '']);
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame(
            'POST data key "matcher" must be a serialized list of closures',
            (string)$response->getBody()
        );

        $response = $this->client->post('/_expectation', null, ['matcher' => ['foo']]);
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame(
            'POST data key "matcher" must be a serialized list of closures',
            (string)$response->getBody()
        );

        $response = $this->client->post('/_expectation', null, []);
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "response" not found in POST data', (string)$response->getBody());

        $response = $this->client->post('/_expectation', null, ['response' => '']);
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame(
            'POST data key "response" must be a serialized Symfony response',
            (string)$response->getBody()
        );

        $response = $this->client->post(
            '/_expectation',
            null,
            ['response' => serialize(new Response()), 'limiter' => 'foo']
        );
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "limiter" must be a serialized closure', (string)$response->getBody());
    }

    public function testServerParamsAreRecorded()
    {
        $this->client
            ->setUserAgent('CUSTOM UA')
            ->get('/foo')
            ->setAuth('username', 'password')
            ->setProtocolVersion('1.0');

        $latestRequest = unserialize($this->client->get('/_request/latest')->getBody());

        $this->assertSame(HTTP_MOCK_HOST, $latestRequest['server']['SERVER_NAME']);
        $this->assertSame(HTTP_MOCK_PORT, $latestRequest['server']['SERVER_PORT']);
        $this->assertSame('username', $latestRequest['server']['PHP_AUTH_USER']);
        $this->assertSame('password', $latestRequest['server']['PHP_AUTH_PW']);
        $this->assertSame('HTTP/1.0', $latestRequest['server']['SERVER_PROTOCOL']);
        $this->assertSame('CUSTOM UA', $latestRequest['server']['HTTP_USER_AGENT']);
    }

    public function testNewestExpectationsAreFirstEvaluated()
    {
        $this->client->post(
            '/_expectation',
            $this->createExpectationParams(
                [
                    static function ($request) {
                        return $request instanceof Request;
                    }
                ],
                new Response('first', 200)
            )
        );
        $this->assertSame('first', $this->client->get('/')->getBody()->getContents());

        $this->client->post(
            '/_expectation',
            $this->createExpectationParams(
                [
                    static function ($request) {
                        return $request instanceof Request;
                    }
                ],
                new Response('second', 200)
            )
        );
        $this->assertSame('second', $this->client->get('/')->getBody()->getContents());
    }

    public function testServerLogsAreNotInErrorOutput()
    {
        $this->client->delete('/_all');

        $expectedServerErrorOutput = '[404]: (null) / - No such file or directory';

        self::$server1->addErrorOutput('PHP 7.4.2 Development Server (http://localhost:8086) started' . PHP_EOL);
        self::$server1->addErrorOutput('Accepted' . PHP_EOL);
        self::$server1->addErrorOutput($expectedServerErrorOutput . PHP_EOL);
        self::$server1->addErrorOutput('Closing' . PHP_EOL);

        $actualServerErrorOutput = self::$server1->getErrorOutput();

        $this->assertEquals($expectedServerErrorOutput, $actualServerErrorOutput);

        self::$server1->clearErrorOutput();
    }

    private function parseRequestFromResponse(\GuzzleHttp\Psr7\Response $response)
    {
        $body = unserialize($response->getBody());

        return RequestFactory::getInstance()->fromMessage($body['request']);
    }

    private function createExpectationParams(array $closures, Response $response)
    {
        foreach ($closures as $index => $closure) {
            $closures[$index] = new SerializableClosure($closure);
        }

        return [
            'matcher'  => serialize($closures),
            'response' => serialize($response),
        ];
    }
}
