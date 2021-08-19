<?php

namespace InterNations\Component\HttpMock;

use Exception;
use GuzzleHttp\Client;
use hmmmath\Fibonacci\FibonacciFactory;
use RuntimeException;
use Symfony\Component\Process\Process;

class Server extends Process
{
    private $port;

    private $host;

    private $client;

    public function __construct($port, $host)
    {
        $this->port = $port;
        $this->host = $host;
        $packageRoot = __DIR__ . '/../';
        $command = [
            'php',
            '-dalways_populate_raw_post_data=-1',
            '-derror_log=',
            '-S=' . $this->getConnectionString(),
            '-t=public/',
            $packageRoot . 'public/index.php',
        ];

        parent::__construct($command, $packageRoot);
        $this->setTimeout(null);
    }

    public function start(callable $callback = null, array $env = [])
    {
        parent::start($callback, $env);

        $this->pollWait();
    }

    public function stop($timeout = 10, $signal = null)
    {
        return parent::stop($timeout, $signal);
    }

    public function getClient(): Client
    {
        return $this->client ?: $this->client = $this->createClient();
    }

    private function createClient(): Client
    {
        $client = new Client(['base_uri' => $this->getBaseUrl(), 'http_errors' => false]);
//        $client->
//        $client->getEventDispatcher()->addListener(
//            'request.error',
//            static function (Event $event) {
//                $event->stopPropagation();
//            }
//        );

        return $client;
    }

    public function getBaseUrl(): string
    {
        return sprintf('http://%s', $this->getConnectionString());
    }

    public function getConnectionString(): string
    {
        return sprintf('%s:%d', $this->host, $this->port);
    }

    /**
     * @param Expectation[] $expectations
     * @throws RuntimeException
     */
    public function setUp(array $expectations): void
    {
        /** @var Expectation $expectation */
        foreach ($expectations as $expectation) {
            $response = $this->getClient()->post(
                '/_expectation',
                [
                    'form_params' => [
                        'matcher'  => serialize($expectation->getMatcherClosures()),
                        'limiter'  => serialize($expectation->getLimiter()),
                        'response' => serialize($expectation->getResponse()),
                    ]
                ]
            );

            if ($response->getStatusCode() !== 201) {
                throw new RuntimeException('Could not set up expectations');
            }
        }
    }

    public function clean()
    {
        if (!$this->isRunning()) {
            $this->start();
        }

        $this->getClient()->delete($this->getBaseUrl() . '/_all');
    }

    private function pollWait(): void
    {
        foreach (FibonacciFactory::sequence(50000, 10000) as $sleepTime) {
            try {
                usleep($sleepTime);
                $this->getClient()->head($this->getBaseUrl() . '/_me');
                break;
            } catch (Exception $e) {
                continue;
            }
        }
    }

    public function getIncrementalErrorOutput()
    {
        return self::cleanErrorOutput(parent::getIncrementalErrorOutput());
    }

    public function getErrorOutput()
    {
        return self::cleanErrorOutput(parent::getErrorOutput());
    }

    private static function cleanErrorOutput($output)
    {
        if (!trim($output)) {
            return '';
        }

        $errorLines = [];

        foreach (explode(PHP_EOL, $output) as $line) {
            if (!$line) {
                continue;
            }

            if (!self::stringEndsWithAny($line, ['Accepted', 'Closing', ' started'])) {
                $errorLines[] = $line;
            }
        }

        return $errorLines ? implode(PHP_EOL, $errorLines) : '';
    }

    private static function stringEndsWithAny($haystack, array $needles)
    {
        foreach ($needles as $needle) {
            if (substr($haystack, (-1 * strlen($needle))) === $needle) {
                return true;
            }
        }

        return false;
    }

}
