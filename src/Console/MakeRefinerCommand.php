<?php

namespace Laragear\Refine\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 */
#[AsCommand('make:refiner', 'Create a new custom Refiner class')]
class MakeRefinerCommand extends GeneratorCommand
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new custom Refiner class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Refiner';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath($this->option('model') ? '/stubs/model-refiner.stub' : '/stubs/refiner.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath($stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/'))) ? $customPath : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Http\Refiners';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['model', 'm', InputOption::VALUE_NONE, 'Creates a refiner for an Eloquent Model'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the cast already exists'],
        ];
    }
}
