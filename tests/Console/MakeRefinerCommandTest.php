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
        $this->artisan('make:refiner', ['name' => 'PostRefiner'])
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
}
