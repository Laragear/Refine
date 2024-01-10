<?php

namespace Laragear\Refine;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use function app;
use function array_flip;
use function array_values;
use function get_class;
use function get_class_methods;
use function is_string;

/**
 * @internal
 * @phpstan-consistent-constructor
 */
class RefineQuery
{
    /**
     * The array of cached methods list for the Refiner classes.
     *
     * @var array<class-string,string[]>
     */
    protected static array $cachedMethods = [];

    /**
     * The Refiner abstract class internal methods.
     *
     * @var string[]|null
     */
    protected static ?array $uncallableBaseRefinerMethods = null;

    /**
     * Create a new refine query instance.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Http\Request  $request
     * @param  \Laragear\Refine\Refiner  $refiner
     */
    public function __construct(
        protected Builder|EloquentBuilder $builder,
        protected Request $request,
        protected Refiner $refiner
    )
    {
        //
    }

    /**
     * Refine the database query using the HTTP Request query.
     *
     * @param  string[]|null  $keys
     * @return void
     */
    public function match(array $keys = null): void
    {
        $request = $this->request();

        $this->refiner->runBefore($this->builder, $request);

        // Take only the query keys that are going to be matched and run them.
        foreach ($this->queryValuesFromRequest($request, $keys) as $method => $value) {
            $this->refiner->{$method}($this->builder, $value, $request);
        }

        $this->refiner->runAfter($this->builder, $request);
    }

    /**
     * Retrieve all the query values from the keys to look for.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string[]|null  $keys
     * @return string[]
     */
    protected function queryValuesFromRequest(Request $request, ?array $keys): array
    {
        return Collection::make($keys ?? $this->getKeysFromRefiner($this->request))
            // Transforms all items to $method => $key
            ->mapWithKeys(static function (string $key): array {
                return [Str::camel($key) => $key];
            })
            // Remove all keys that are not present in the request query.
            ->filter(static function (string $key) use ($request): bool {
                return null !== $request->query($key);
            })
            // Keep all items which method is present in the refiner object.
            ->intersectByKeys(array_flip($this->getPublicMethodsFromRefiner()))
            // Remove all items which method are part of the abstract refiner object.
            ->diffKeys(array_flip($this->getRefinerClassMethods()))
            // Transforms all items into $method => $value
            ->map(static function (string $key) use ($request): string {
                return $request->query($key);
            })
            ->toArray();
    }

    /**
     * Retrieves the key to use from the Refiner instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string[]
     */
    protected function getKeysFromRefiner(Request $request): array
    {
        // Get the array of keys without taking into account the internal methods.
        return array_values($this->refiner->getKeys($request));
    }

    /**
     * Return the methods already used for the Refiner class.
     *
     * @return string[]
     */
    protected function getRefinerClassMethods(): array
    {
        return static::$uncallableBaseRefinerMethods ??= get_class_methods(Refiner::class);
    }

    /**
     * Resolve the current request.
     *
     * @return \Illuminate\Http\Request
     */
    public function request(): Request
    {
        return app('request');
    }

    /**
     * Return the public methods from the refiner to be called.
     *
     * @return string[]
     */
    protected function getPublicMethodsFromRefiner(): array
    {
        $class = get_class($this->refiner);

        if (!isset(static::$cachedMethods[$class])) {
            static::$cachedMethods[$class] = Collection::make((new ReflectionClass($class))->getMethods())
                ->filter(static function (ReflectionMethod $method): bool {
                    return $method->isPublic()
                        && !$method->isStatic()
                        && !$method->isAbstract()
                        && !$method->isDestructor()
                        && !$method->isConstructor();
                })
                ->map(static function (ReflectionMethod $method): string {
                    return $method->name;
                })
                ->toArray();
        }

        return static::$cachedMethods[$class];
    }

    /**
     * Create a new refine query instance.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Laragear\Refine\Refiner|class-string|string  $refiner
     * @param  array|null  $keys
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public static function refine(
        Builder|EloquentBuilder $builder,
        Refiner|string $refiner,
        array $keys = null
    ): Builder|EloquentBuilder {
        // @
        $instance = new static($builder, app('request'), is_string($refiner) ? app($refiner) : $refiner);

        $instance->match($keys);

        return $builder;
    }
}
