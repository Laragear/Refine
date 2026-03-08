<?php

namespace Laragear\Refine;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

abstract class Refiner
{
    /**
     * When true, the validation will throw an exception and stop the request.
     */
    public bool $fail = true;

    /**
     * Check if the validation should throw an exception if it fails.
     */
    public function shouldFail(): bool
    {
        return $this->fail;
    }

    /**
     * Return the keys to use to refine the query.
     *
     * @return string[]
     */
    public function getKeys(Request $request): array
    {
        return array_keys($request->query());
    }

    /**
     * Return the keys that should be always run its mapped methods.
     *
     * @return string[]
     */
    public function getObligatoryKeys(Request $request): array
    {
        return [];
    }

    /**
     * Run before the refiner executes its matched methods.
     */
    public function runBefore(Builder|EloquentBuilder $query, Request $request): void
    {
        //
    }

    /**
     * Run after the refiner has executed all its matched methods.
     */
    public function runAfter(Builder|EloquentBuilder $query, Request $request): void
    {
        //
    }

    /**
     * Return the validation rules.
     *
     * @return string[]|array[]
     */
    public function validationRules(): array
    {
        return [
            // ...
        ];
    }

    /**
     * Return the validation messages.
     *
     * @return array
     */
    public function validationMessages(): array
    {
        return [
            // ...
        ];
    }

    /**
     * Return the validation custom attributes.
     *
     * @return array
     */
    public function validationCustomAttributes(): array
    {
        return [
            // ...
        ];
    }
}
