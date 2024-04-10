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
     * Return the keys that should be always run its mapped methods.
     * @return string[]
     */
    public function getObligatoryKeys(Request $request): array
    {
        return [];
    }

    /**
     * Run before the refiner executes its matched methods.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function runBefore(Builder|EloquentBuilder $query, Request $request): void
    {
        //
    }

    /**
     * Run after the refiner has executed all its matched methods.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function runAfter(Builder|EloquentBuilder $query, Request $request): void
    {
        //
    }

    /**
     * Return the validation rules
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
