<?php

namespace Laragear\Refine;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laragear\Refine\Contracts\ValidatesRefiner;
use UnexpectedValueException;

use function array_pad;
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
     * If the query search should use full-text search.
     *
     * @var bool
     *
     * @see https://laravel.com/docs/11.x/queries#full-text-where-clauses
     */
    protected bool $fullTextSearch = false;

    /**
     * Return the validation rules.
     *
     * @return array<string, string|string[]|\Illuminate\Contracts\Validation\Rule[]>
     */
    public function validationRules(): array
    {
        return [
            'query' => 'sometimes|nullable|string',
            'only' => 'sometimes|nullable|array',
            'only.*' => ['required_with:only', 'string', Rule::in($this->getOnlyColumns())],
            'has' => 'sometimes|nullable|array',
            'has.*' => ['required_with:has', 'string', Rule::in($this->getHasRelations())],
            'has_not' => 'sometimes|nullable|array',
            'has_not.*' => ['required_with:missing', 'string', Rule::in($this->getHasNotRelations())],
            'with' => 'sometimes|nullable|array',
            'with.*' => ['required_with:with', 'string', Rule::in($this->getWithRelations())],
            'with_count' => 'sometimes|nullable|array',
            'with_count.*' => ['required_with:with_count', 'string', Rule::in($this->getCountRelations())],
            'with_sum' => 'sometimes|nullable|array',
            'with_sum.*' => ['required_with:with_sum', 'string', Rule::in($this->getWithSumRelations())],
            'trashed' => 'sometimes|nullable|boolean',
            'order_by' => ['sometimes', 'nullable', Rule::in($this->getOrderByColumns())],
            'order_by_desc' => ['sometimes', 'nullable', Rule::in($this->getOrderByColumns())],
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
            'has_not',
            'with',
            'with_count',
            'with_sum',
            'trashed',
            'order',
            'order_by',
            'order_by_desc',
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
    protected function getHasNotRelations(): array
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
    protected function getWithSumRelations(): array
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
     */
    public function query(EloquentBuilder $query, string $search): void
    {
        if ($columns = (array) $this->getQueryColumns()) {
            $query->where(function (EloquentBuilder $query) use ($search, $columns): void {
                $query->whereKey($search);

                if ($this->fullTextSearch) {
                    $query->orWhereFullText($columns, $search);
                } else {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'ILIKE', Str::wrap(
                            htmlspecialchars($search, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '%')
                        );
                    }
                }
            });
        }
    }

    /**
     * Select only some columns to return in the query results.
     *
     * @param  string[]  $columns
     */
    public function only(EloquentBuilder $query, array $columns): void
    {
        $query->select($columns);
    }

    /**
     * Find records that contain at least one related model.
     *
     * @param  string[]  $relations
     */
    public function has(EloquentBuilder $query, array $relations): void
    {
        foreach ($relations as $relation) {
            $query->has($this->normalizeRelation($relation));
        }
    }

    /**
     * Find records that do not contain any related model.
     *
     * @param  string[]  $relations
     */
    public function hasNot(EloquentBuilder $query, array $relations): void
    {
        foreach ($relations as $relation) {
            $query->doesntHave($this->normalizeRelation($relation));
        }
    }

    /**
     * Load the given relations to the query results.
     *
     * @param  string[]  $relations
     */
    public function with(EloquentBuilder $query, array $relations): void
    {
        foreach ($relations as $relation) {
            $query->with($this->normalizeRelation($relation));
        }
    }

    /**
     * Load the given count of relations to the query result.
     *
     * @param  string[]  $relations
     */
    public function withCount(EloquentBuilder $query, array $relations): void
    {
        foreach ($relations as $relation) {
            $query->withCount($this->normalizeRelation($relation));
        }
    }

    /**
     * Load the given count of relations to the query result.
     *
     * @param  string[]  $relations
     */
    public function withSum(EloquentBuilder $query, array $relations): void
    {
        foreach ($relations as $relation) {
            [$relation, $column] = array_pad(explode('-', $relation), 2, null);

            if (! $relation || ! $column) {
                throw new UnexpectedValueException('Cannot find the relation or column to sum');
            }

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
     */
    public function trashed(EloquentBuilder $query, string $trashed): void
    {
        if (in_array(Str::lower($trashed), ['1', 'true', 'on']) && $query->hasNamedScope(SoftDeletingScope::class)) {
            $query->withTrashed();
        }
    }

    /**
     * Sort the query using the given column and order.
     *
     * @param  "asc"|"desc"  $direction
     */
    public function orderBy(EloquentBuilder $query, string $column, Request $request, string $direction = 'asc'): void
    {
        // Do not add another order if one is already defined.
        ! $query->getQuery()->orders && $query->orderBy($column, $direction);
    }

    /**
     * Sort the query using the given column and order.
     */
    public function orderByDesc(EloquentBuilder $query, string $column, Request $request): void
    {
        $this->orderBy($query, $column, $request, 'desc');
    }

    /**
     * Limit the query results by the given amount.
     */
    public function limit(EloquentBuilder $query, int $limit): void
    {
        // This will the limit between zero and the default model "perPage" configuration (15 by default).
        $query->limit(max(0, min($limit, $query->getModel()->getPerPage())));
    }

    /**
     * Limit the query results by the given amount. Alias for `limit`.
     *
     * @internal
     */
    public function perPage(EloquentBuilder $query, int $limit): void
    {
        $this->limit($query, $limit);
    }
}
