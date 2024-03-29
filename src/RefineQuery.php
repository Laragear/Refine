<?php

namespace Laragear\Refine;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Precognition;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laragear\Refine\Contracts\ValidatesRefiner;
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
 *
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
     */
    public function __construct(
        protected Builder|EloquentBuilder $builder,
        protected Request $request,
        protected Refiner $refiner
    ) {
        //
    }

    /**
     * Refine the database query using the HTTP Request query.
     *
     * @param  string[]|null  $keys
     */
    public function match(array $keys = null): void
    {
        $request = $this->request();

        $this->refiner->runBefore($this->builder, $request);

        if ($this->refiner instanceof ValidatesRefiner) {
            $this->validateRefiner();
        }

        // Take only the query keys that are going to be matched and run them.
        $this->executeRefinerMethodsFromRequest($request, $keys);

        $this->refiner->runAfter($this->builder, $request);
    }

    /**
     * Validate the refiner.
     */
    protected function validateRefiner(): void
    {
        $validator = app(ValidationFactory::class)->make(
            $this->request->query(),
            $this->refiner->validationRules(),
            $this->refiner->validationMessages(),
            $this->refiner->validationCustomAttributes()
        );

        if ($this->request->isPrecognitive()) {
            $validator->after(Precognition::afterValidationHook($this->request))
                ->setRules($this->request->filterPrecognitiveRules($validator->getRulesWithoutPlaceholders()));
        }

        $validator->validate();
    }

    /**
     * Retrieve all the query values from the keys to look for.
     *
     * @param  string[]|null  $keys
     */
    protected function executeRefinerMethodsFromRequest(Request $request, ?array $keys): void
    {
        $placeholder = (object) [];

        Collection::make($keys ?? $this->getKeysFromRefiner($this->request))
            // Transforms all items to $method => $key
            ->mapWithKeys(static function (string $key): array {
                return [Str::camel($key) => $key];
            })
            // Remove all keys that are not present in the request query.
            // @phpstan-ignore-next-line
            ->filter(static fn (string $key): bool => $placeholder !== $request->query($key, $placeholder))
            // Add "obligatory" keys set by the refiner that will always run.
            ->merge($this->getObligatoryKeysFromRefiner($request))
            // Keep all items which method is present in the refiner object.
            ->intersectByKeys(array_flip($this->getPublicMethodsFromRefiner()))
            // Remove all items which method are part of the abstract refiner object.
            ->diffKeys(array_flip($this->getRefinerClassMethods()))
            // Transforms all items into $method => $value
            ->each(function (string $key, string $method) use ($request): void {
                $this->refiner->{$method}($this->builder, $request->query($key), $request);
            });
    }

    /**
     * Retrieves the key to use from the Refiner instance.
     *
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
     */
    public function request(): Request
    {
        return app('request');
    }

    /**
     * Return the obligatory keys from the refiner.
     *
     * @return \Illuminate\Support\Collection<string, string>
     */
    protected function getObligatoryKeysFromRefiner(Request $request): Collection
    {
        return Collection::make($this->refiner->getObligatoryKeys($request))
            ->mapWithKeys(static function (string $key): array {
                return [Str::camel($key) => $key];
            });
    }

    /**
     * Return the public methods from the refiner to be called.
     *
     * @return string[]
     */
    protected function getPublicMethodsFromRefiner(): array
    {
        $class = get_class($this->refiner);

        if (! isset(static::$cachedMethods[$class])) {
            static::$cachedMethods[$class] = Collection::make((new ReflectionClass($class))->getMethods())
                ->filter(static function (ReflectionMethod $method): bool {
                    return $method->isPublic()
                        && ! $method->isStatic()
                        && ! $method->isAbstract()
                        && ! $method->isDestructor()
                        && ! $method->isConstructor();
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
     * @param  \Laragear\Refine\Refiner|class-string<\Laragear\Refine\Refiner>  $refiner
     * @param  string[]|null  $keys
     */
    public static function refine(
        Builder|EloquentBuilder $builder,
        Refiner|string $refiner,
        array $keys = null
    ): Builder|EloquentBuilder {
        $instance = new static($builder, app('request'), is_string($refiner) ? app($refiner) : $refiner);

        $instance->match($keys);

        return $builder;
    }
}
