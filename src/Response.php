<?php

namespace Ieu\Httpider;

use Ieu\Httpider\Wrapper\Html;
use Ieu\Httpider\Wrapper\Json;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\RedirectMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class Response extends \GuzzleHttp\Psr7\Response
{
    /** @var UriInterface */
    private $uri;

    /** @var UriInterface */
    private $effectiveUri;

    /** @var mixed */
    private $meta;

    /** @var string */
    private $charset;

    /**
     * @return UriInterface
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * @param string|UriInterface $uri
     * @return static
     */
    public function withUri($uri)
    {
        if ($uri === $this->uri) {
            return $this;
        }

        if (is_string($uri)) {
            $uri = new Uri($uri);
        }

        $new = clone $this;
        $new->uri = $uri;

        return $new;
    }

    /**
     * @param mixed $meta
     * @return static
     */
    public function withMeta($meta)
    {
        if ($this->meta === $meta) {
            return $this;
        }

        $new = clone $this;
        $new->meta = $meta;
        return $new;
    }

    /**
     * @return mixed
     */
    public function getMeta()
    {
        return $this->meta;
    }

    public function getEffectiveUri(): UriInterface
    {
        if (null == $this->effectiveUri) {
            $header = $this->getHeader(RedirectMiddleware::HISTORY_HEADER);
            if (isset($header[0])) {
                $this->effectiveUri = new Uri($header[0]);
            } else {
                $this->effectiveUri = $this->uri;
            }
        }

        return $this->effectiveUri;
    }

    public function getEffectiveUriString(): string
    {
        return strval($this->getEffectiveUri());
    }

    public function resolveUri(UriInterface $uri): UriInterface
    {
        return UriResolver::resolve($this->getEffectiveUri(), $uri);
    }

    public function resolveUriString(string $uri): string
    {
        return strval($this->resolveUri(new Uri($uri)));
    }

    public function getCharset(): ?string
    {
        if (null === $this->charset) {
            $contentTypes = $this->getHeader('Content-Type');
            foreach ($contentTypes as $contentType) {
                if (preg_match_all('/charset\s*=\s*(?<charsets>[a-zA-Z0-9:_.-]+)/i', $contentType, $matches)) {
                    foreach ($matches['charsets'] as $this->charset) {

                    }
                }
            }
        }
        return $this->charset;
    }

    public function html(string $charset = null): Html
    {
        $dom = new Html();
        $dom->addHtmlContent($this->getBodyContents(), $charset ?? $this->getCharset() ?? 'UTF-8');
        return $dom;
    }

    public function json(): Json
    {
        return new Json($this->getBodyContents());
    }

    public function getBodyContents(): string
    {
        $this->getBody()->rewind();
        $contents = $this->getBody()->getContents();
        $this->getBody()->rewind();

        return $contents;
    }

    public static function buildFromPsrResponse(ResponseInterface $response)
    {
        return new static(
            $response->getStatusCode(),
            $response->getHeaders(),
            $response->getBody(),
            $response->getProtocolVersion(),
            $response->getReasonPhrase()
        );
    }
}
