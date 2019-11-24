<?php

namespace Ieu\Httpider;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

abstract class Crawler
{
    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var boolean
     */
    private $debug = false;

    protected function getDefaultCallback(): callable
    {
        return [ $this, 'parse' ];
    }

    protected static function getNoopCallback(): callable
    {
        return [ static::class, 'noop' ];
    }

    public static function noop(Response $response) {
        return $response->getMeta();
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     * @return $this
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * @return $this
     */
    public function enableDebug()
    {
        return $this->setDebug(true);
    }

    /**
     * @return $this
     */
    public function disableDebug()
    {
        return $this->setDebug(false);
    }

    /**
     * @param ClientInterface $httpClient
     * @return $this
     */
    public function setHttpClient(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * @return ClientInterface
     */
    public function getHttpClient(): ClientInterface
    {
        if (null === $this->httpClient) {
            $this->httpClient = new Client([
                RequestOptions::COOKIES => true,
                RequestOptions::ALLOW_REDIRECTS => [
                    'track_redirects' => true,
                ],
                RequestOptions::DEBUG => $this->isDebug(),
            ]);
        }

        return $this->httpClient;
    }

    /**
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
        return $this;
    }

    public function getLogger() : LoggerInterface
    {
        if (null === $this->logger) {
            $logger = new Logger('crawler');
            $logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));
            $this->logger = $logger;
        }

        return $this->logger;
    }

    abstract public function startPoint();
    abstract public function parse(Response $response);

    /**
     * @param string|Request $startPoint
     * @return Request
     */
    public function getStartPoint($startPoint): Request
    {
        if ($startPoint instanceof Request) {
            $callback = $startPoint->getCallback();
            if (empty($callback) || !is_callable($callback) || self::getNoopCallback() === $callback) {
                $startPoint = $startPoint->withCallback($this->getDefaultCallback());
            }
            return $startPoint;
        } elseif (is_string($startPoint)) {
            return $this->get($startPoint)->withCallback($this->getDefaultCallback());
        } else {
            throw new InvalidArgumentException(static::class . "::startPoint() must return instance of either Request or string");
        }
    }

    public function beforeStart(): void
    {

    }

    public function start()
    {
        $this->beforeStart();

        $startPoints = $this->startPoint();
        if (is_array($startPoints)) {
            return $this->handleCallable(function() use ($startPoints) {
                foreach ($startPoints as $startPoint) {
                    yield $this->getStartPoint($startPoint);
                }
            });
        } else {
            return $this->handleCallable(function() use ($startPoints) {
                return $this->getStartPoint($startPoints);
            });
        }
    }

    public function doStart(Request $request)
    {
        return $this->handleCallable($request->getCallback(), $this->sendRequest($request));
    }

    protected function handleCallable(callable $callable, ...$args)
    {
        $result = call_user_func_array($callable, $args);
        if ($result instanceof Generator) {
            $ret = [];
            foreach ($result as $item) {
                if ($item instanceof Request) {
                    $subResult = $this->doStart($item);
                    if (is_array($subResult)) {
                        if (isset($subResult[0])) {
                            $ret = array_merge($ret, $subResult);
                        } elseif (null !== $subResult) {
                            $ret[] = $subResult;
                        }
                    } else {
                        $ret[] = $subResult;
                    }
                } else {
                    $ret[] = $item;
                }
            }
            return $ret;
        } elseif ($result instanceof Request) {
            return $this->doStart($result);
        } else {
            return $result;
        }
    }

    public function request(string $method, string $uri, callable $callback = null, $meta = null)
    {
        $uri = new Uri($uri);

        if (null === $callback) {
            $callback = self::getNoopCallback();
        }

        $request = (new Request($method, $uri))
            ->withCallback($callback)
            ->withMeta($meta)
            ;

        return $request;
    }

    public function get(string $uri, callable $callback = null, $meta = null)
    {
        return $this->request('GET', $uri, $callback, $meta);
    }

    public function post(string $uri, callable $callback = null, $meta = null)
    {
        return $this->request('POST', $uri, $callback, $meta);
    }

    public function sendRequest(Request $request) : Response
    {
        return Response::buildFromPsrResponse($this->getHttpClient()->send($request))
            ->withUri($request->getUri())
            ->withMeta($request->getMeta());
    }
}
