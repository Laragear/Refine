<?php

namespace Laragear\Refine;

use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laragear\Refine\Contracts\ValidatesRefiner;
use function explode;
use function htmlspecialchars;
use function in_array;
use function join;
use function max;
use function min;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

abstract class ModelRefiner extends Refiner implements ValidatesRefiner
{
    /**
     * Return the validation rules
     *
     * @return array<string, string|string[]|\Illuminate\Contracts\Validation\Rule[]>
     */
    public function validationRules(): array
    {
        return [
            'query' => 'sometimes|nullable|string',
            'only' => 'sometimes|nullable|array|in:',
            'only.*' => ['required', 'string', Rule::in($this->getOnlyColumns())],
            'except' => 'sometimes|nullable|array',
            'except.*' => ['required', 'string', Rule::in($this->getExceptColumns())],
            'has' => 'sometimes|nullable|array',
            'has.*' => ['required_with:has', 'string', Rule::in($this->getHasRelations())],
            'missing' => 'sometimes|nullable|array',
            'missing.*' => ['required_with:has', 'string', Rule::in($this->getMissingRelations())],
            'with' => 'sometimes|nullable|array',
            'with.*' => ['required', 'string', Rule::in($this->getWithRelations())],
            'with_count' => 'sometimes|nullable|array',
            'with_count.*' => ['required', 'string', Rule::in($this->getCountRelations())],
            'with_sum' => 'sometimes|nullable|array',
            'with_sum.*' => ['required_with:with_sum', 'string', Rule::in($this->getSumRelations())],
            'trashed' => 'sometimes|nullable|boolean',
            'order' => 'sometimes|in:asc,desc',
            'order_by' => ['required_with:order', 'sometimes', Rule::in($this->getOrderByColumns())],
            'limit' => 'sometimes|nullable|integer',
            'per_page' => 'sometimes|nullable|integer',
        ];
    }

    /**
     * Return the keys to use to refine the query.
     *
     * @return string[]
     */
    public function getKeys(Request $request): array
    {
        return [
            'query',
            'only',
            'except',
            'has',
            'missing',
            'with',
            'with_count',
            'with_sum',
            'trashed',
            'order',
            'order_by',
            'limit',
            'per_page',
        ];
    }

    /**
     * Return the columns that should only be included in the query.
     *
     * @return string[]
     */
    protected function getOnlyColumns(): array
    {
        return [];
    }

    /**
     * Return the columns that should be removed from the query.
     *
     * @return string[]
     */
    protected function getExceptColumns(): array
    {
        return [];
    }

    /**
     * Return the relations that should exist for the query.
     *
     * @return string[]
     */
    protected function getHasRelations(): array
    {
        return [];
    }

    /**
     * Return the relations that should be missing for the query.
     *
     * @return string[]
     */
    protected function getMissingRelations(): array
    {
        return [];
    }

    /**
     * Return the relations that can be queried.
     *
     * @return string[]
     */
    protected function getWithRelations(): array
    {
        return [];
    }

    /**
     * Return the relations that can be counted.
     *
     * @return string[]
     */
    protected function getCountRelations(): array
    {
        return [];
    }

    /**
     * Return the relations and the columns that should be sum.
     *
     * @return string[]
     */
    protected function getSumRelations(): array
    {
        // Separate the relation name using hyphen (`-`). For example, `published_posts-votes`.
        return [];
    }

    /**
     * Return the columns that can be used to sort the query.
     *
     * @return string[]
     */
    protected function getOrderByColumns(): array
    {
        return [];
    }

    /**
     * Return the column used for full-text search.
     *
     * @return string|string[]
     */
    protected function getQueryColumns(): string|array
    {
        return [];
    }

    /**
     * Filter the query by a column containing a given text.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     */
    public function query($query, string $search): void
    {
        if ($columns = (array) $this->getQueryColumns()) {
            $query->where(static function ($query) use ($search, $columns): void {
                $query->whereKey($search);

                foreach ($columns as $column) {
                    $query->orWhere($column, 'ILIKE', Str::wrap(
                        htmlspecialchars($search, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '%')
                    );
                }
            });
        }
    }

    /**
     * Load the given relations to the query results.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @param  string[]  $relations
     */
    public function with($query, array $relations): void
    {
        foreach ($relations as $relation) {
            $query->with($this->normalizeRelation($relation));
        }
    }

    /**
     * Load the given count of relations to the query result.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @param  string[]  $relations
     */
    public function withCount($query, array $relations): void
    {
        foreach ($relations as $relation) {
            $query->withCount($this->normalizeRelation($relation));
        }
    }

    /**
     * Load the given count of relations to the query result.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @param  string[]  $relations
     */
    public function withSum($query, array $relations): void
    {
        foreach ($relations as $relation) {
            [$relation, $column] = explode('-', $relation);

            $query->withSum($this->normalizeRelation($relation), $column);
        }
    }

    /**
     * Normalize the relation name or relations separated by dot notation.
     */
    protected function normalizeRelation(string $relation): string
    {
        return join('.', array_map(Str::camel(...), explode('.', $relation)));
    }

    /**
     * Load trashed models in the query.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     */
    public function trashed($query, string $trashed): void
    {
        if (in_array(Str::lower($trashed), ['1', 'true', 'on']) && $query->hasNamedScope(SoftDeletingScope::class)) {
            $query->withTrashed();
        }
    }

    /**
     * Sort the query using the given column and order.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     */
    public function order($query, string $order, Request $request): void
    {
        if ($column = $request->get('order_by')) {
            $query->orderBy($column, $order);
        }
    }

    /**
     * Limit the query results by the given amount.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     */
    public function limit($query, int $limit): void
    {
        // This will the limit between zero and the default model "perPage" configuration (15 by default).
        $query->limit(max(0, min($limit, $query->getModel()->getPerPage())));
    }

    /**
     * Limit the query results by the given amount.
     *
     * Alias for `limit`.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     *
     * @internal
     */
    public function perPage($query, int $limit): void
    {
        $this->limit($query, $limit);
    }
}
