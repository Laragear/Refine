<?php

namespace Tests;

use Illuminate\Http\Request;
use Laragear\Refine\ModelRefiner;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\MockModel;
use UnexpectedValueException;

use function array_map;
use function join;

class ModelRefinerTest extends TestCase
{
    protected function stringifyValidationRule(string|array $rule): string
    {
        return join('|', array_map(fn ($rule): string => (string) $rule, (array) $rule));
    }

    #[Test]
    public function creates_default_validation_rules(): void
    {
        $rules = [
            'query' => 'sometimes|nullable|string',
            'only' => 'sometimes|nullable|array',
            'only.*' => 'required_with:only|string|in:',
            'has' => 'sometimes|nullable|array',
            'has.*' => 'required_with:has|string|in:',
            'has_not' => 'sometimes|nullable|array',
            'has_not.*' => 'required_with:missing|string|in:',
            'with' => 'sometimes|nullable|array',
            'with.*' => 'required_with:with|string|in:',
            'with_count' => 'sometimes|nullable|array',
            'with_count.*' => 'required_with:with_count|string|in:',
            'with_sum' => 'sometimes|nullable|array',
            'with_sum.*' => 'required_with:with_sum|string|in:',
            'trashed' => 'sometimes|nullable|boolean',
            'order_by' => 'sometimes|nullable|in:',
            'order_by_desc' => 'sometimes|nullable|in:',
            'limit' => 'sometimes|nullable|integer',
            'per_page' => 'sometimes|nullable|integer',
        ];

        foreach ($rules as $key => $rule) {
            static::assertSame(
                $rule, $this->stringifyValidationRule((new MockModelRefiner())->validationRules()[$key])
            );
        }
    }

    #[Test]
    public function uses_custom_columns_for_validation(): void
    {
        $rules = [
            'only.*' => 'required_with:only|string|in:"foo"',
            'has.*' => 'required_with:has|string|in:"baz"',
            'has_not.*' => 'required_with:missing|string|in:"quz"',
            'with.*' => 'required_with:with|string|in:"qux"',
            'with_count.*' => 'required_with:with_count|string|in:"quux"',
            'with_sum.*' => 'required_with:with_sum|string|in:"corge-bar"',
            'order_by' => 'sometimes|nullable|in:"grault"',
            'order_by_desc' => 'sometimes|nullable|in:"grault"',
        ];

        foreach ($rules as $key => $rule) {
            static::assertSame(
                $rule, $this->stringifyValidationRule((new MockModelRefinerWithColumns())->validationRules()[$key])
            );
        }
    }

    protected function mockRequest(array $data): void
    {
        $this->instance('request', new Request($data));
    }

    /** @test */
    #[Test]
    public function query_finds_record(): void
    {
        $this->mockRequest(['query' => 'search']);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('query')->with($builder, 'search', $this->app->make('request'))->once();
    }

    /** @test */
    #[Test]
    public function only_returns_some_columns(): void
    {
        $this->mockRequest(['only' => ['foo']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('only')->with($builder, ['foo'], $this->app->make('request'))->once();
    }

    #[Test]
    public function has_returns_items_with_relation(): void
    {
        $this->mockRequest(['has' => ['baz']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('has')->with($builder, ['baz'], $this->app->make('request'))->once();
    }

    #[Test]
    public function missing_returns_items_without_relation(): void
    {
        $this->mockRequest(['has_not' => ['quz']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('hasNot')->with($builder, ['quz'], $this->app->make('request'))->once();
    }

    #[Test]
    public function with_includes_relations(): void
    {
        $this->mockRequest(['with' => ['qux']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('with')->with($builder, ['qux'], $this->app->make('request'))->once();
    }

    #[Test]
    public function with_count_includes_relation_count(): void
    {
        $this->mockRequest(['with_count' => ['quux']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('withCount')->with($builder, ['quux'], $this->app->make('request'))->once();
    }

    #[Test]
    public function with_sum_throws_when_malformed(): void
    {
        $this->mockRequest(['with_sum' => ['invalid']]);

        $builder = MockModel::query();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot find the relation or column to sum');

        $builder->refineBy(new class extends ModelRefiner
        {
            protected function getWithSumRelations(): array
            {
                return ['invalid'];
            }
        });
    }

    #[Test]
    public function with_sum_includes_relation_column_sum(): void
    {
        $this->mockRequest(['with_sum' => ['corge-bar']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('withSum')->with($builder, ['corge-bar'], $this->app->make('request'))->once();
    }

    #[Test]
    public function trashed_includes_deleted_items(): void
    {
        $this->mockRequest(['trashed' => '1']);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('trashed')->with($builder, '1', $this->app->make('request'))->once();
    }

    #[Test]
    public function order_by_sorts_query(): void
    {
        $this->mockRequest(['order_by' => 'grault']);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('orderBy')->with($builder, 'grault', $this->app->make('request'))->once();
    }

    #[Test]
    public function order_by_desc_sorts_query(): void
    {
        $this->mockRequest(['order_by_desc' => 'grault']);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('orderByDesc')->with($builder, 'grault', $this->app->make('request'))->once();
    }

    #[Test]
    public function limit_takes_some_items(): void
    {
        $this->mockRequest(['limit' => 10]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('limit')->with($builder, 10, $this->app->make('request'))->once();
    }

    #[Test]
    public function per_page_takes_some_items(): void
    {
        $this->mockRequest(['per_page' => 10]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('perPage')->with($builder, 10, $this->app->make('request'))->once();
    }
}

class MockModelRefiner extends ModelRefiner
{
}

class MockModelRefinerWithColumns extends ModelRefiner
{
    protected function getOnlyColumns(): array
    {
        return ['foo'];
    }

    protected function getHasRelations(): array
    {
        return ['baz'];
    }

    protected function getHasNotRelations(): array
    {
        return ['quz'];
    }

    protected function getWithRelations(): array
    {
        return ['qux'];
    }

    protected function getCountRelations(): array
    {
        return ['quux'];
    }

    protected function getWithSumRelations(): array
    {
        return ['corge-bar'];
    }

    protected function getOrderByColumns(): array
    {
        return ['grault'];
    }

    protected function getQueryColumns(): string|array
    {
        return ['garply'];
    }
}
