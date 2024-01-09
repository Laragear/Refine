<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Laragear\MetaTesting\InteractsWithServiceProvider;
use Laragear\Refine\RefineServiceProvider;

class ServiceProviderTest extends TestCase
{
    use InteractsWithServiceProvider;

    public function test_published_stub(): void
    {
        static::assertSame(
            [RefineServiceProvider::STUBS => $this->app->basePath('.stubs/refine-query.php')],
            ServiceProvider::pathsToPublish(RefineServiceProvider::class, 'phpstorm')
        );
    }

    public function test_registers_command(): void
    {
        static::assertArrayHasKey('make:refiner', $this->app->make(Kernel::class)->all());
    }

    public function test_registers_builder_macro(): void
    {
        $this->assertHasMacro(Builder::class, 'refineBy');
    }

    public function test_registers_eloquent_builder_macro(): void
    {
        $this->assertHasMacro(EloquentBuilder::class, 'refineBy');
    }
}
