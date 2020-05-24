<?php

namespace Restql;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Restql\SchemaDefinition;
use Restql\SchemaExecutor;
use Restql\Traits\HasConfigService;

final class Builder
{
    use HasConfigService;

    /**
     * A query collection of models.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $query;

    /**
     * The array response.
     *
     * @var array
     */
    protected $response = [];

    /**
     * The application defined middleware short-hand names.
     *
     * @var array
     */
    protected $routeMiddleware = [];

    /**
     * Builder instance.
     *
     * @param \Illuminate\Support\Collection $query
     */
    public function __construct(Collection $query)
    {
        $this->query = $query;

        $this->routeMiddleware = app('router')->getMiddleware();
    }

    /**
     * Static class instance.
     *
     * @param \Illuminate\Support\Collection $query
     * @return \Illuminate\Support\Collection
     */
    public static function make(Collection $query): Collection
    {
        return (new Builder($query))->dispatch();
    }

    /**
     * Chains the methods to the eloquent query.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function dispatch(): Collection
    {
        $schema = $this->schema();

        // $this->checkMiddlewares($schema);

        // $this->checkAuthorizers($schema);

        $schema->each(function (SchemaDefinition $schema) {
            /// Executing the "handle" method in the schema definition, this will
            /// return a collection with data resolved independently.
            $this->response[$schema->getKeyName()] = $schema->handle();
        });

        return Collection::make($this->response);
    }

    /**
     * Checks middlewares for incoming request.
     *
     * @param  \Illuminate\Support\Collection $schema
     * @return void
     */
    protected function checkAuthorizers(Collection $schema): void
    {
        $request = app('request');

        $schema->each(function (SchemaDefinition $schemaDefinition) use ($request) {
            $method = Str::lower($request->method());

            $authorizer = $schemaDefinition->getAuthorizerInstance();

            $authorized = call_user_func([$authorizer, $method], $request);

            abort_if(!$authorized, 403, sprintf('Can\'t access to the resource via [%s]', $method));
        });
    }

    /**
     * Checks middlewares for incoming request.
     *
     * @param  \Illuminate\Support\Collection $schema
     * @return void
     */
    protected function checkMiddlewares(Collection $schema): void
    {
        $middlewares = $schema->reduce(
            function (array $reducer, SchemaDefinition $schemaDefinition) {
                foreach ($schemaDefinition->getMiddlewares() as $key => $value) {
                    $middlewareClass = $this->routeMiddleware[$value] ?? false;
                    if ($middlewareClass && !in_array($middlewareClass, $reducer)) {
                        $reducer[] = $middlewareClass;
                    }
                }

                return $reducer;
            },
            []
        );

        $request = app('request');

        app(Pipeline::class)->send($request)->through($middlewares)->thenReturn();
    }

    /**
     * Remove unknow model key and resolvers names from the incoming query.
     *
     * @return \Illuminate\Support\Collection
     */
    public function schema(): Collection
    {
        return $this->query->map(function ($arguments, $schemaKeyName) {
            /// Create an SchemaDefinition instance.
            return new SchemaDefinition($schemaKeyName, (array) $arguments);
        })->filter(function (SchemaDefinition $schema) {
            /// Checks if the schema class exists and be a
            /// 'Illuminate\Database\Eloquent\Model' or 'Restql\Resolver' children.
            return $schema->imValid();
        });
    }
}
