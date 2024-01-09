<?php

namespace Laragear\Refine;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\ServiceProvider;

class RefineServiceProvider extends ServiceProvider
{
    public const STUBS = __DIR__.'/../.stubs/stubs';

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        $callback = function (object|string $refiner, array $keys = null): Builder|EloquentBuilder {
            /** @var \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $this */
            return RefineQuery::refine($this, $refiner, $keys);
        };

        if (!Builder::hasMacro('refineBy')) {
            Builder::macro('refineBy', $callback);
        }

        if (!EloquentBuilder::hasGlobalMacro('refineBy')) {
            EloquentBuilder::macro('refineBy', $callback);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([static::STUBS => $this->app->basePath('.stubs/refine-query.php')], 'phpstorm');

            $this->commands(Console\MakeRefinerCommand::class);
        }
    }
}
