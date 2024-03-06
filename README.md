# Refiner
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/refine.svg)](https://packagist.org/packages/laragear/refine)
[![Latest stable test run](https://github.com/Laragear/Refine/workflows/Tests/badge.svg)](https://github.com/Laragear/Refine/actions)
[![Codecov coverage](https://codecov.io/gh/Laragear/Refine/branch/1.x/graph/badge.svg?token=lJMZg5mdVy)](https://codecov.io/gh/Laragear/Refine)
[![Maintainability](https://api.codeclimate.com/v1/badges/19ea8702c12213898a9c/maintainability)](https://codeclimate.com/github/Laragear/Refine/maintainability)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_Refine&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_Refine)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/10.x/octane#introduction)

Filter a database query using the request query keys and matching methods.

```php
// https://myblog.com/posts/?author_id=10

class PostController
{
    public function all(Request $request)
    {
        return Post::refineBy(PostRefiner::class)->paginate()
    }
}

class PostRefiner
{
    public function authorId($query, $value)
    {
        $query->where('author_id', $value);
    }
}
```
## Become a sponsor

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FRefine&hashtags=PHP,Laravel)**

## Requirements

* PHP 8 or later.
* Laravel 9, 10 or later.

## Installation

Require this package into your project using Composer:

```bash
composer require laragear/refine
```

## Usage

This package solves the problem of refining a Databse Query using the HTTP Request by moving that logic out of the controller.

For example, imagine you want to show all the Posts made by a given Author ID. Normally, you would check that on the controller and modify the query inside.

```php
use App\Models\Post;
use Illuminate\Http\Request;

public function all(Request $request)
{
    $request->validate([
        'author_id' => 'sometimes|integer'
    ]);

    return Post::when($request->has('author_id'), function ($query) {
        $query->where('author_id', $request->get('author_id'));
    });
}
```

While this is inoffensive, it will add up as more refinements are needed: published at a given time, with a given set of tags, ordering, etc. Eventually it will clutter your controller.

Instead, Laragear Refine moves that logic to its own "Refiner" object.

```php
use App\Models\Post;
use Illuminate\Http\Request;
use App\Http\Refiners\PostRefiner;

public function all(Request $request)
{
    return Post::query()->refineBy(PostRefiner::class);
}
```

The magic is simple: the refiner methods will be executed as long the key of the same name is present in the Request. Keys are automatically normalized to `camelCase` so these match the method, so `author_id` will become `authorId()`.

```http request
GET https://myapp.com/posts?author_id=20
```

```php
namespace App\Http\Refiners;

class PostRefiner
{
    public function authorId($query, $value)
    {
        $query->where('author_id', $value);
    }
}
```

## Creating a Filter

Call the `make:refiner` with the name of the Refiner.

```shell
php artisan make:refiner PostRefiner
```

You will receive the filter in the `app\Http\Refiners` folder:

```php
namespace App\Http\Refiners;

use Laragear\Refine\Refiner;

class PostRefiner extends Refiner
{
    /**
     * Create a new post query filter instance.
     */
    public function __construct()
    {
        //
    }
}
```

As you can see, apart from the constructor, the class is empty. The next step is to define methods to match the request keys. 

### Defining methods

Methods will be executed as long the Request key of the same name is present. Keys are normalized to `camelCase` to match the corresponding method.

All methods you set in the Refiner class receive the Query Builder instance, the value from the request, and the Request instance itself. Inside each method, you're free to modify the Query Builder as you see fit, or even call authorization gates or check the user permissions.

```php
namespace App\Http\Refiners;

use App\Models\Post;
use Illuminate\Http\Request;
use Laragear\Refine\Refiner;

class PostRefiner extends Refiner
{
    public function authorId($query, mixed $value, Request $request)
    {
        // Only apply the filter if the user has permission to see all posts.
        if ($request->user()->can('view any', Post::class)) {
            $query->where('author_id', $value);
        }
    }
}
```

### Only some keys

By default, the Refiner will check all keys of the request query. You may want to limit which of these keys respective methods will be executed if present. To do that, use the `getKeys()` method, and return that set of keys.

```php
use Illuminate\Http\Request;

public function getKeys(Request $request): array
{
    return [
        'author_id',
        'published_before',
        'published_after',
    ];
}
```

Alternatively, if you're using a `FormRequest`, you can just return the keys of the validated data.

```php
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\FormRequest;

public function getKeys(Request $request): array
{
    if ($request instanceof FormRequest) {
        return array_keys($request->validated()); 
    }
    
    return array_keys($request->keys());
}
```

### Dependency Injection

The Refiner class is always resolved using the application container. You can type-hint any dependency in the class constructor and use it later on the matching methods.

```php
namespace App\Http\Refiners;

use Illuminate\Contracts\Auth\Access\Gate;
use Laragear\Refine\Refiner;
use App\Models\Post;

class PostRefiner extends Refiner
{
    public function __construct(protected Gate $gate)
    {
        //
    }
    
    public function authorId($query, $value)
    {
        if ($this->gate->check('view any', Post::class)) {
            // ...
        }
    }
}
```

### Validation

You may also include validation logic into your Refiner by implementing the `ValidateRefiner` interface. From there, you should set your validation rules, and optionally your messages and custom attributes.

Validation rules will run verbatim over the Request Query (not the input), so if you expect a key to always be required in the query, the `validationRules()` is an excellent place to do it.

```php
use Laragear\Refine\Contracts\ValidatesRefiner;
use Laragear\Refine\Refiner;

class PostRefiner extends Refiner implements ValidatesRefiner
{
    // ...
    
    public function validationRules(): array
    {
        return ['author_id' => 'required|integer'];
    }
}
```

## Applying a Refiner

In your Builder instance, simply call `refineBy()` with the name of the Refiner class (or its alias if you registered it on the application container) to apply to the query.

```php
use App\Models\Post;
use App\Http\Refiners\PostRefiner;

Post::refineBy(PostRefiner::class)->paginate();
```

The `refineBy()` is a macro registered to the Eloquent Builder and the base Query Builder, and you can use it even after your own refinements.

```php
use App\Http\Requests\PostRequest;
use Illuminate\Support\Facades\DB;
use App\Http\Refiners\PostRefiner;

public function rawPosts(PostRequest $request)
{
    return DB::table('posts')
        ->whereNull('deleted_at')
        ->refineBy(PostRefiner::class)
        ->limit(10)
        ->get();
}
```

### Custom keys

You can override the keys to look for on the Request by issuing the keys as second argument.

```php
public function all(Request $request)
{
    $validated = $request->validate([
        // ...
    ])

    Post::query()->refineBy(PostFilter::class, ['author_id'])->paginate();
}
```

## Laravel Octane compatibility

- There are no singletons using a stale application instance.
- There are no singletons using a stale config instance.
- There are no singletons using a stale request instance.
- A static property being written is the cache of Refiner methods which grows by every unique Refiner that runs.
- A static property being written is the cache of Abstract Refiner methods which is only written once.

There should be no problems using this package with Laravel Octane.

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

# License

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2024 Laravel LLC.
