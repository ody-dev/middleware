<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewarePipeline implements RequestHandlerInterface
{
    /**
     * @var array Stack of middleware
     */
    protected array $middleware = [];

    /**
     * @var callable Final handler to execute if no middleware returns a response
     */
    protected $finalHandler;

    /**
     * Constructor
     *
     * @param callable|RequestHandlerInterface $finalHandler Final handler to execute
     */
    public function __construct(callable|RequestHandlerInterface $finalHandler)
    {
        $this->finalHandler = $finalHandler;
    }

    /**
     * Add multiple middleware at once
     *
     * @param array $middlewareList
     * @return self
     */
    public function addMultiple(array $middlewareList): self
    {
        foreach ($middlewareList as $middleware) {
            if ($middleware instanceof MiddlewareInterface) {
                $this->add($middleware);
            }
        }
        return $this;
    }

    /**
     * Add middleware to the pipeline
     *
     * @param MiddlewareInterface $middleware
     * @return self
     */
    public function add(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Process the request through the middleware stack
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (empty($this->middleware)) {
            // Check the type of the final handler
            if ($this->finalHandler instanceof RequestHandlerInterface) {
                // If it's a PSR-15 handler, call its handle() method
                return $this->finalHandler->handle($request);
            } elseif (is_callable($this->finalHandler)) {
                // If it's a callable (Closure, invokable object), use call_user_func
                return call_user_func($this->finalHandler, $request);
            }
        }

        // Take the first middleware from the stack
        $middleware = array_shift($this->middleware);

        // Ensure the item is actually middleware before processing
        if (!($middleware instanceof MiddlewareInterface)) {
            throw new \LogicException('Invalid item found in middleware pipeline; does not implement MiddlewareInterface.');
        }

        // Process the request through the middleware, passing the pipeline ($this)
        // as the next handler.
        return $middleware->process($request, $this);
    }
}