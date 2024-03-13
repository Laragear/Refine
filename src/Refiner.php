<?php

namespace Laragear\Refine;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

abstract class Refiner
{
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
     * Return the keys as "foo_bar" that should always execute, even with "null" values.
     *
     * @return string[]
     */
    public function getObligatoryKeys(Request $request): array
    {
        return [
            // ...
        ];
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
     * @return array<string, string|string[]|\Illuminate\Contracts\Validation\Rule[]>
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
     * @return array<string, string>
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
     * @return array<string, string>
     */
    public function validationCustomAttributes(): array
    {
        return [
            // ...
        ];
    }
}
