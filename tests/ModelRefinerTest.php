<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laragear\Refine\Contracts\ValidatesRefiner;
use Laragear\Refine\ModelRefiner;
use Laragear\Refine\Refiner;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function provideRulesForDefaultValidation(): array
    {
        return [
            ['query', 'sometimes|nullable|string'],
            ['only', 'sometimes|nullable|array'],
            ['only.*', 'required_with:only|string|in:'],
            ['has', 'sometimes|nullable|array'],
            ['has.*', 'required_with:has|string|in:'],
            ['has_not', 'sometimes|nullable|array'],
            ['has_not.*', 'required_with:missing|string|in:'],
            ['with', 'sometimes|nullable|array'],
            ['with.*', 'required_with:with|string|in:'],
            ['with_count', 'sometimes|nullable|array'],
            ['with_count.*', 'required_with:with_count|string|in:'],
            ['with_sum', 'sometimes|nullable|array'],
            ['with_sum.*', 'required_with:with_sum|string|in:'],
            ['trashed', 'sometimes|nullable|boolean'],
            ['order_by', 'sometimes|nullable|in:'],
            ['order_by_desc', 'sometimes|nullable|in:'],
            ['limit', 'sometimes|nullable|integer'],
            ['per_page', 'sometimes|nullable|integer'],
        ];
    }

    #[DataProvider('provideRulesForDefaultValidation')]
    public function test_creates_default_validation_rules(string $attribute, string $rules): void
    {
        static::assertSame(
            $rules, $this->stringifyValidationRule((new MockModelRefiner())->validationRules()[$attribute])
        );
    }

    public static function providesRulesForCustomColumnsForValidation(): array
    {
        return [
            ['only.*', 'required_with:only|string|in:"foo"'],
            ['has.*', 'required_with:has|string|in:"baz"'],
            ['has_not.*', 'required_with:missing|string|in:"quz"'],
            ['with.*', 'required_with:with|string|in:"qux"'],
            ['with_count.*', 'required_with:with_count|string|in:"quux"'],
            ['with_sum.*', 'required_with:with_sum|string|in:"corge-bar"'],
            ['order_by', 'sometimes|nullable|in:"grault"'],
            ['order_by_desc', 'sometimes|nullable|in:"grault"'],
        ];
    }

    #[DataProvider('providesRulesForCustomColumnsForValidation')]
    public function test_uses_custom_columns_for_validation(string $attribute, string $rules): void
    {
        static::assertSame(
            $rules, $this->stringifyValidationRule((new MockModelRefinerWithColumns())->validationRules()[$attribute])
        );
    }

    protected function mockRequest(array $data): void
    {
        $request = Request::create('/', 'GET', $data);
        $request->query->add($data);

        $this->instance('request', $request);
    }

    public function test_query_finds_record(): void
    {
        $this->mockRequest(['query' => 'search']);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('query')->with($builder, 'search', $this->app->make('request'))->once();
    }

    public function test_only_returns_some_columns(): void
    {
        $this->mockRequest(['only' => ['foo']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('only')->with($builder, ['foo'], $this->app->make('request'))->once();
    }

    public function test_has_returns_items_with_relation(): void
    {
        $this->mockRequest(['has' => ['baz']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('has')->with($builder, ['baz'], $this->app->make('request'))->once();
    }

    public function test_missing_returns_items_without_relation(): void
    {
        $this->mockRequest(['has_not' => ['quz']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('hasNot')->with($builder, ['quz'], $this->app->make('request'))->once();
    }

    public function test_with_includes_relations(): void
    {
        $this->mockRequest(['with' => ['qux']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('with')->with($builder, ['qux'], $this->app->make('request'))->once();
    }

    public function test_with_count_includes_relation_count(): void
    {
        $this->mockRequest(['with_count' => ['quux']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('withCount')->with($builder, ['quux'], $this->app->make('request'))->once();
    }

    public function test_with_sum_throws_when_malformed(): void
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

    public function test_with_sum_includes_relation_column_sum(): void
    {
        $this->mockRequest(['with_sum' => ['corge-bar']]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('withSum')->with($builder, ['corge-bar'], $this->app->make('request'))->once();
    }

    public function test_trashed_includes_deleted_items(): void
    {
        $this->mockRequest(['trashed' => '1']);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('trashed')->with($builder, '1', $this->app->make('request'))->once();
    }

    public function test_order_by_sorts_query(): void
    {
        $this->mockRequest(['order_by' => 'grault']);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('orderBy')->with($builder, 'grault', $this->app->make('request'))->once();
    }

    public function test_order_by_desc_sorts_query(): void
    {
        $this->mockRequest(['order_by_desc' => 'grault']);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('orderByDesc')->with($builder, 'grault', $this->app->make('request'))->once();
    }

    public function test_limit_takes_some_items(): void
    {
        $this->mockRequest(['limit' => 10]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('limit')->with($builder, 10, $this->app->make('request'))->once();
    }

    public function test_per_page_takes_some_items(): void
    {
        $this->mockRequest(['per_page' => 10]);

        $builder = MockModel::query();

        $mock = $this->partialMock(MockModelRefinerWithColumns::class);

        $builder->refineBy(MockModelRefinerWithColumns::class);

        $mock->shouldHaveReceived('perPage')->with($builder, 10, $this->app->make('request'))->once();
    }

    public function test_with_trashed(): void
    {
        $this->mockRequest(['trashed' => '1']);

        $builder = MockModel::query()->refineBy(MockModelRefiner::class);

        static::assertSame(
            'select * from "mock_models"',
            $builder->toSql()
        );

        $builder = (new class extends Model
        {
            protected $table = 'test_table';
        })->newQuery()->refineBy(MockModelRefinerWithFullTextSearch::class);

        static::assertSame(
            'select * from "test_table"',
            $builder->toSql()
        );
    }

    public function test_uses_full_text_search(): void
    {
        $this->app->make('config')->set('database.default', 'mariadb');
        $this->mockRequest(['query' => 'test-full-text-search']);

        $builder = MockModel::query();

        $builder->refineBy(MockModelRefinerWithFullTextSearch::class);

        static::assertSame(
            'select * from `mock_models` where (match (`garply`) against (? in natural language mode)) and `mock_models`.`deleted_at` is null',
            $builder->toSql()
        );
    }

    public function test_doesnt_uses_full_text_search_when_the_columns_are_empty(): void
    {
        $this->app->make('config')->set('database.default', 'mariadb');
        $this->mockRequest(['query' => 'test-full-text-search']);

        $builder = MockModel::query();

        $builder->refineBy(MockModelRefinerWithFullTextSearchWithEmptyColumns::class);

        static::assertSame('select * from `mock_models` where `mock_models`.`deleted_at` is null', $builder->toSql());
    }

    public function test_uses_custom_validation_rules(): void
    {
        $this->expectNotToPerformAssertions();

        $this->mockRequest(['foo' => 'test']);

        $builder = MockModel::query();

        $builder->refineBy(MockModelRefinerWithCustomValidationRules::class);
    }

    public function test_uses_custom_validation_rules_empty(): void
    {
        $this->expectNotToPerformAssertions();

        $this->mockRequest(['foo' => 'test']);

        $builder = MockModel::query();

        $builder->refineBy(MockModelRefinerWithCustomValidationRulesEmpty::class);
    }
}

class MockModelRefiner extends ModelRefiner
{
    //
}
class MockModelRefinerWithCustomValidationRulesEmpty extends Refiner implements ValidatesRefiner
{
    //
}

class MockModelRefinerWithCustomValidationRules extends Refiner implements ValidatesRefiner
{
    public function validationRules(): array
    {
        return [
            'foo' => 'required',
        ];
    }
}

class MockModelRefinerWithFullTextSearch extends ModelRefiner
{
    protected bool $fullTextSearch = true;

    protected function getQueryColumns(): string|array
    {
        return ['garply'];
    }
}

class MockModelRefinerWithFullTextSearchWithEmptyColumns extends ModelRefiner
{
    protected bool $fullTextSearch = true;
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
