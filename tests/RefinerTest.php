<?php

namespace Tests;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laragear\Refine\Contracts\ValidatesRefiner;
use Laragear\Refine\RefineQuery;
use Laragear\Refine\Refiner;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class RefinerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->afterApplicationCreated(function (): void {
            MockRefinerWithObligatoryKeys::$value = 'uninitialized';
        });

        $this->afterApplicationCreated(RefineQuery::flushCachedRefinerMethods(...));
        $this->beforeApplicationDestroyed(RefineQuery::flushCachedRefinerMethods(...));

        parent::setUp();
    }

    protected function mockRequest(array $data): void
    {
        $this->instance('request', new Request($data));
    }

    public static function provideBuilders(): array
    {
        return [
            [static function () {
                return Fixtures\MockModel::query();
            }],
            [static function () {
                return Fixtures\MockModel::query()->getQuery();
            }],
        ];
    }

    public function test_abstract_refiner_uses_query_keys_by_default(): void
    {
        $keys = (new MockRefiner())->getKeys(new Request(['foo' => 1, 'bar' => 2], ['baz' => 3, 'quz' => 4]));

        static::assertSame(['foo', 'bar'], $keys);
    }

    #[DataProvider('provideBuilders')]
    public function test_calls_run_before_with_request_and_builder(Closure $getQuery): void
    {
        $builder = $getQuery();

        $mock = $this->partialMock(MockRefiner::class);

        $builder->refineBy(MockRefiner::class);

        $mock->shouldHaveReceived('runBefore')->with($builder, $this->app->make('request'))->once();
    }

    #[DataProvider('provideBuilders')]
    public function test_calls_run_after_with_request_and_builder(Closure $getQuery): void
    {
        $builder = $getQuery();

        $mock = $this->partialMock(MockRefiner::class);

        $builder->refineBy(MockRefiner::class);

        $mock->shouldHaveReceived('runAfter')->with($builder, $this->app->make('request'))->once();
    }

    #[DataProvider('provideBuilders')]
    public function test_calls_matched_methods_from_request(Closure $getQuery): void
    {
        $this->mockRequest(['foo' => 1, 'bar' => 2]);

        $builder = $getQuery();

        $mock = $this->partialMock(MockRefiner::class);

        $builder->refineBy(MockRefiner::class);

        $mock->shouldHaveReceived('foo')->with($builder, 1, $this->app->make('request'))->once();
        $mock->shouldHaveReceived('bar')->with($builder, 2, $this->app->make('request'))->once();
        $mock->shouldNotHaveReceived('quz');
    }

    #[DataProvider('provideBuilders')]
    public function test_calls_matched_method_using_camel_Case(Closure $getQuery): void
    {
        $this->mockRequest(['foo-bar' => 1, 'bar_Quz' => 2, 'QUZ-FOX' => 3]);

        $builder = $getQuery();

        $mock = $this->partialMock(MockCamelCaseRefiner::class);

        $builder->refineBy(MockCamelCaseRefiner::class);

        $mock->shouldhaveReceived('fooBar')->with($builder, 1, $this->app->make('request'))->once();
        $mock->shouldhaveReceived('barQuz')->with($builder, 2, $this->app->make('request'))->once();
        $mock->shouldhaveReceived('qUZFOX')->with($builder, 3, $this->app->make('request'))->once();
    }

    #[DataProvider('provideBuilders')]
    public function test_doesnt_calls_non_callable_methods(Closure $getQuery): void
    {
        $this->mockRequest(['__construct' => 1, 'protected' => 2, 'static' => 3, '__destruct' => 4]);

        $mock = $this->partialMock(MockVariedMethodsRefiner::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
        });

        $getQuery()->refineBy(MockVariedMethodsRefiner::class);

        $mock->shouldNotHaveReceived('__construct');
        $mock->shouldNotHaveReceived('protected');
        $mock->shouldNotHaveReceived('static');
        $mock->shouldNotHaveReceived('__destruct');
    }

    #[DataProvider('provideBuilders')]
    public function test_doesnt_calls_refiner_included_methods(Closure $getQuery): void
    {
        $this->mockRequest(['get-keys' => 1, 'run-before' => 2, 'run-after' => 4]);

        $mock = $this->partialMock(MockVariedMethodsRefiner::class);

        $getQuery()->refineBy(MockRefiner::class);

        $mock->shouldNotHaveReceived('getKeys');
        $mock->shouldNotHaveReceived('runBefore');
        $mock->shouldNotHaveReceived('runAfter');
    }

    #[DataProvider('provideBuilders')]
    public function test_calls_matched_methods_from_request_using_custom_keys(Closure $getQuery): void
    {
        $this->mockRequest(['foo' => 1, 'bar' => 2]);

        $builder = $getQuery();

        $mock = $this->partialMock(MockRefiner::class);

        $builder->refineBy(MockRefiner::class, ['bar']);

        $mock->shouldHaveReceived('bar')->with($builder, 2, $this->app->make('request'))->once();
        $mock->shouldNotHaveReceived('foo');
        $mock->shouldNotHaveReceived('quz');
    }

    #[DataProvider('provideBuilders')]
    public function test_validates_refiner(Closure $getQuery): void
    {
        $this->mockRequest(['foo' => 1, 'bar' => 2]);

        $builder = $getQuery();

        $this->partialMock(MockValidatesRefiner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('validationRules')->once();
            $mock->shouldReceive('validationMessages')->once();
            $mock->shouldReceive('validationCustomAttributes')->once();
        });

        $builder->refineBy(MockValidatesRefiner::class, ['bar']);
    }

    #[DataProvider('provideBuilders')]
    public function test_doesnt_validates_refiner_if_doesnt_implement_interface(Closure $getQuery): void
    {
        $this->mockRequest(['foo' => 1, 'bar' => 2]);

        $builder = $getQuery();

        $this->partialMock(MockRefiner::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('validationRules');
            $mock->shouldNotReceive('validationMessages');
            $mock->shouldNotReceive('validationCustomAttributes');
        });

        $builder->refineBy(MockRefiner::class, ['bar']);
    }

    #[DataProvider('provideBuilders')]
    public function test_runs_obligatory_key_without_value(Closure $getQuery): void
    {
        $this->mockRequest(['foo' => 1, 'bar' => 2]);

        $builder = $getQuery();

        $builder->refineBy(MockRefinerWithObligatoryKeys::class);

        static::assertNull(MockRefinerWithObligatoryKeys::$value);
    }

    #[DataProvider('provideBuilders')]
    public function test_runs_obligatory_key_with_value(Closure $getQuery): void
    {
        $this->mockRequest(['foo' => 1, 'bar' => 2, 'qux' => 'value']);

        $builder = $getQuery();

        $builder->refineBy(MockRefinerWithObligatoryKeys::class);

        static::assertSame('value', MockRefinerWithObligatoryKeys::$value);
    }

    #[DataProvider('provideBuilders')]
    public function test_runs_obligatory_key_without_overriding(Closure $getQuery): void
    {
        $this->mockRequest(['foo' => 1, 'bar' => 2]);

        $builder = $getQuery();

        $builder->refineBy(MockRefinerWithObligatoryKeys::class, ['bar']);

        static::assertNull(MockRefinerWithObligatoryKeys::$value);
    }

    #[DataProvider('provideBuilders')]
    public function test_uses_validation_custom_Data(Closure $getQuery): void
    {
        $this->mockRequest(['foo' => '']);

        $builder = $getQuery();

        $this->expectException(ValidationException::class);

        try {
            $builder->refineBy(MockRefinerWithValidationData::class);
        } catch (ValidationException $e) {
            static::assertSame(['foo' => ['test-message test-foo']], $e->errors());

            throw $e;
        }

    }
}

class MockRefiner extends Refiner
{
    public function foo()
    {
    }

    public function bar()
    {
    }

    public function quz()
    {
    }
}

class MockCamelCaseRefiner extends Refiner
{
    public function fooBar()
    {
        //
    }

    public function barQuz()
    {
        //
    }

    public function qUZFOX()
    {
        //
    }
}

class MockVariedMethodsRefiner extends Refiner
{
    public function __construct()
    {
    }

    protected function protected()
    {
    }

    public static function static()
    {
    }

    public function __destruct()
    {
    }
}

class MockRefinerWithObligatoryKeys extends MockRefiner
{
    public static $value;

    public function getObligatoryKeys(Request $request): array
    {
        return ['qux'];
    }

    public function qux($query, $value): void
    {
        static::$value = $value;
    }
}

class MockValidatesRefiner extends Refiner implements ValidatesRefiner
{
}

class MockRefinerWithValidationData extends Refiner implements ValidatesRefiner
{
    public function validationRules(): array
    {
        return [
            'foo' => 'required|string',
        ];
    }

    public function validationMessages(): array
    {
        return [
            'foo' => 'test-message :attribute'
        ];
    }

    public function validationCustomAttributes(): array
    {
        return [
            'foo' => 'test-foo',
        ];
    }
}
