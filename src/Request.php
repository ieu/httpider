<?php

namespace Ieu\Httpider;

class Request extends \GuzzleHttp\Psr7\Request
{
    /** @var callable */
    private $callback;

    /** @var mixed */
    private $meta;

    /**
     * @param callable $callback
     * @return static
     */
    public function withCallback(Callable $callback)
    {
        if ($this->callback === $callback) {
            return $this;
        }
        $new = clone $this;
        $new->callback = $callback;
        return $new;
    }

    public function getCallback() : ?callable
    {
        return $this->callback;
    }

    /**
     * @param array $headers
     * @return static
     */
    public function withHeaders(array $headers)
    {
        return (new static(
            $this->getMethod(),
            $this->getUri(),
            $headers,
            $this->getBody(),
            $this->getProtocolVersion()
        ))
            ->withCallback($this->getCallback())
            ->withMeta($this->getMeta())
            ;
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
}
