<?php

namespace Dingo\Api\Transformer\Adapter;

use Dingo\Api\Http\Request;
use Dingo\Api\Transformer\Binding;
use Dingo\Api\Contract\Transformer\Adapter;
use League\Fractal\Manager as FractalManager;
use League\Fractal\Resource\Item as FractalItem;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use Illuminate\Support\Collection as IlluminateCollection;
use League\Fractal\Resource\Collection as FractalCollection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Contracts\Pagination\Paginator as IlluminatePaginator;

class Fractal implements Adapter
{
    /**
     * Fractal manager instance.
     *
     * @var \League\Fractal\Manager
     */
    protected $fractal;

    /**
     * The include query string key.
     *
     * @var string
     */
    protected $includeKey;

    /**
     * The include separator.
     *
     * @var string
     */
    protected $includeSeparator;

    /**
     * Indicates if eager loading is enabled.
     *
     * @var bool
     */
    protected $eagerLoading = true;

    /**
     * Create a new fractal transformer instance.
     *
     * @param \League\Fractal\Manager $fractal
     * @param string                  $includeKey
     * @param string                  $includeSeparator
     * @param bool                    $eagerLoading
     *
     * @return void
     */
    public function __construct(FractalManager $fractal, $includeKey = 'include', $includeSeparator = ',', $eagerLoading = true)
    {
        $this->fractal = $fractal;
        $this->includeKey = $includeKey;
        $this->includeSeparator = $includeSeparator;
        $this->eagerLoading = $eagerLoading;
    }

    /**
     * Transform a response with a transformer.
     *
     * @param mixed                          $response
     * @param object                         $transformer
     * @param \Dingo\Api\Transformer\Binding $binding
     * @param \Dingo\Api\Http\Request        $request
     *
     * @return array
     */
    public function transform($response, $transformer, Binding $binding, Request $request)
    {
        $this->parseFractalIncludes($request);

        $resource = $this->createResource($response, $transformer, $binding->getParameters());

        // If the response is a paginator then we'll create a new paginator
        // adapter for Laravel and set the paginator instance on our
        // collection resource.
        if ($response instanceof IlluminatePaginator) {
            $paginator = $this->createPaginatorAdapter($response);

            $resource->setPaginator($paginator);
        }

        if ($response instanceof EloquentCollection && $this->eagerLoading) {
            $eagerLoads = $this->mergeEagerLoads($transformer, $this->fractal->getRequestedIncludes());

            $response->load($eagerLoads);
        }

        foreach ($binding->getMeta() as $key => $value) {
            $resource->setMetaValue($key, $value);
        }

        $binding->fireCallback($resource);

        return $this->fractal->createData($resource)->toArray();
    }

    /**
     * Create the Fractal paginator adapter.
     *
     * @param \Illuminate\Contracts\Pagination\Paginator $paginator
     *
     * @return \League\Fractal\Pagination\IlluminatePaginatorAdapter
     */
    protected function createPaginatorAdapter(IlluminatePaginator $paginator)
    {
        return new IlluminatePaginatorAdapter($paginator);
    }

    /**
     * Create a Fractal resource instance.
     *
     * @param mixed                               $response
     * @param \League\Fractal\TransformerAbstract $transformer
     * @param array                               $parameters
     *
     * @return \League\Fractal\Resource\Item|\League\Fractal\Resource\Collection
     */
    protected function createResource($response, $transformer, array $parameters)
    {
        $key = isset($parameters['key']) ? $parameters['key'] : null;

        if ($response instanceof IlluminatePaginator || $response instanceof IlluminateCollection) {
            return new FractalCollection($response, $transformer, $key);
        }

        return new FractalItem($response, $transformer, $key);
    }

    /**
     * Parse the includes.
     *
     * @param \Dingo\Api\Http\Request $request
     *
     * @return void
     */
    public function parseFractalIncludes(Request $request)
    {
        $includes = $request->get($this->includeKey);

        if (! is_array($includes)) {
            $includes = array_filter(explode($this->includeSeparator, $includes));
        }

        $this->fractal->parseIncludes($includes);
    }

    /**
     * Get the underlying Fractal instance.
     *
     * @return \League\Fractal\Manager
     */
    public function getFractal()
    {
        return $this->fractal;
    }

    /**
     * Get includes as their array keys for eager loading.
     *
     * @param \League\Fractal\TransformerAbstract $transformer
     * @param string|array                        $requestedIncludes
     *
     * @return array
     */
    protected function mergeEagerLoads($transformer, $requestedIncludes)
    {
        $availableIncludes = array_intersect($transformer->getAvailableIncludes(), (array) $requestedIncludes);

        $includes = array_merge($availableIncludes, $transformer->getDefaultIncludes());

        $eagerLoads = [];

        foreach ($includes as $key => $value) {
            $eagerLoads[] = is_string($key) ? $key : $value;
        }

        return $eagerLoads;
    }
}
