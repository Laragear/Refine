<?php

namespace Tests;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laragear\Refine\Contracts\ValidatesRefiner;
use Laragear\Refine\Refiner;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class RefinerTest extends TestCase
{
    protected function mockRequest(array $data): void
    {
        $this->instance('request', new Request($data));
    }

    public static function provideBuilders(): array
    {
        return [
            [static function () { return MockModel::query(); }],
            [static function () { return MockModel::query()->getQuery(); }]
        ];
    }

    public function test_abstract_refiner_uses_query_keys_by_default(): void
    {
        $keys = (new MockRefiner())->getKeys(new Request(['foo' => 1, 'bar' => 2], ['baz' => 3, 'quz' => 4]));

        static::assertSame(['foo', 'bar'], $keys);
    }

    /**
     * @param  \Closure():\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $getQuery
     * @dataProvider provideBuilders
     */
    #[DataProvider('provideBuilders')]
    public function test_calls_run_before_with_request_and_builder(Closure $getQuery): void
    {
        $builder = $getQuery();

        $mock = $this->partialMock(MockRefiner::class);

        $builder->refineBy(MockRefiner::class);

        $mock->shouldHaveReceived('runBefore')->with($builder, $this->app->make('request'))->once();
    }

    /**
     * @param  \Closure():\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $getQuery
     * @dataProvider provideBuilders
     */
    #[DataProvider('provideBuilders')]
    public function test_calls_run_after_with_request_and_builder(Closure $getQuery): void
    {
        $builder = $getQuery();

        $mock = $this->partialMock(MockRefiner::class);

        $builder->refineBy(MockRefiner::class);

        $mock->shouldHaveReceived('runAfter')->with($builder, $this->app->make('request'))->once();
    }

    /**
     * @param  \Closure():\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $getQuery
     * @dataProvider provideBuilders
     */
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

    /**
     * @param  \Closure():\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $getQuery
     * @dataProvider provideBuilders
     */
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

    /**
     * @param  \Closure():\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $getQuery
     * @dataProvider provideBuilders
     */
    #[DataProvider('provideBuilders')]
    public function test_doesnt_calls_non_callable_methods(Closure $getQuery): void
    {
        $this->mockRequest(['__construct' => 1, 'protected' => 2, 'static' => 3, '__destruct' => 4]);

        $mock = $this->partialMock(MockVariedMethodsRefiner::class, function (MockInterface  $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
        });

        $getQuery()->refineBy(MockVariedMethodsRefiner::class);

        $mock->shouldNotHaveReceived('__construct');
        $mock->shouldNotHaveReceived('protected');
        $mock->shouldNotHaveReceived('static');
        $mock->shouldNotHaveReceived('__destruct');
    }

    /**
     * @param  \Closure():\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $getQuery
     * @dataProvider provideBuilders
     */
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

    /**
     * @param  \Closure():\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $getQuery
     * @dataProvider provideBuilders
     */
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

    /**
     * @param  \Closure():\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $getQuery
     * @dataProvider provideBuilders
     */
    #[DataProvider('provideBuilders')]
    public function test_validates_refiner(Closure $getQuery): void
    {
        $this->mockRequest(['foo' => 1, 'bar' => 2]);

        $builder = $getQuery();

        $this->partialMock(MockValidatesRefiner::class, function (MockInterface $mock) use ($builder): void {
            $mock->shouldReceive('validationRules')->once();
            $mock->shouldReceive('validationMessages')->once();
            $mock->shouldReceive('validationCustomAttributes')->once();
        });

        $builder->refineBy(MockValidatesRefiner::class, ['bar']);
    }

    /**
     * @param  \Closure():\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $getQuery
     * @dataProvider provideBuilders
     */
    #[DataProvider('provideBuilders')]
    public function test_doesnt_validates_refiner_if_doesnt_implement_interface(Closure $getQuery): void
    {
        $this->mockRequest(['foo' => 1, 'bar' => 2]);

        $builder = $getQuery();

        $this->partialMock(MockRefiner::class, function (MockInterface $mock) use ($builder): void {
            $mock->shouldNotReceive('validationRules');
            $mock->shouldNotReceive('validationMessages');
            $mock->shouldNotReceive('validationCustomAttributes');
        });

        $builder->refineBy(MockRefiner::class, ['bar']);
    }
}

class MockModel extends Model
{
    //
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

class MockValidatesRefiner extends Refiner implements ValidatesRefiner
{

}
