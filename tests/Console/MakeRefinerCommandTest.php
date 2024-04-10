<?php

namespace Tests\Console;

use Tests\TestCase;

class MakeRefinerCommandTest extends TestCase
{
    protected function filepath(): string
    {
        return $this->app->basePath('app/Http/Refiners/PostRefiner.php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $clear = function () {
            $this->app['files']->delete($this->filepath());
        };

        $this->afterApplicationCreated($clear);
        $this->beforeApplicationDestroyed($clear);
    }

    public function test_command_generates_file(): void
    {
        $this->artisan('make:refiner PostRefiner')
            ->assertExitCode(0);

        $this->assertTrue($this->app['files']->exists($this->filepath()));

        $needles = [
            'namespace App\Http\Refiners;',
            'use Laragear\Refine\Refiner;',
            'class PostRefiner extends Refiner',
            'public function __construct()',
        ];

        $file = $this->app['files']->get($this->filepath());

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $file);
        }
    }

    public function test_command_generates_model_refiner_file(): void
    {
        $this->artisan('make:refiner PostRefiner --model')->assertExitCode(0);

        $this->assertTrue($this->app['files']->exists($this->filepath()));

        $needles = [
            'namespace App\Http\Refiners;',
            'use Laragear\Refine\ModelRefiner;',
            'class PostRefiner extends ModelRefiner',
            'public function __construct()',
            'protected function getOnlyColumns(): array',
            'protected function getHasRelations(): array',
            'protected function getMissingRelations(): array',
            'protected function getWithRelations(): array',
            'protected function getCountRelations(): array',
            'protected function getSumRelations(): array',
            'protected function getOrderByColumns(): array',
        ];

        $file = $this->app['files']->get($this->filepath());

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $file);
        }
    }
}
