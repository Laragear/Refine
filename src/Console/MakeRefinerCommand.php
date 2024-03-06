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
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:refiner {--model: Creates a refiner for an Eloquent Model}';

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
        return $this->hasOption('model')
            ? $this->resolveStubPath('/stubs/model-refiner.stub')
            : $this->resolveStubPath('/stubs/refiner.stub');
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
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the cast already exists'],
        ];
    }
}
