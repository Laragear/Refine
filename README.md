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

* Laravel 10 or later.

## Installation

Require this package into your project using Composer:

```bash
composer require laragear/refine
```

## Usage

This package solves the problem of refining a database query using the URL parameters by moving that logic out of the controller.

For example, imagine you want to show all the Posts made by a given Author ID. Normally, you would check that on the controller and modify the query inside.

```php
use App\Models\Post;
use Illuminate\Http\Request;

public function all(Request $request)
{
    $request->validate([
        'author_id' => 'sometimes|integer'
    ]);
    
    $query = Post::query()->limit(10);
    
    if ($request->has('author_id')) {
        $query->where('author_id', $request->get('author_id'));
    }

    return $query->get();
}
```

While this looks inoffensive for a couple of URL parameters, it will add up as more refinements are needed: published at a given time, with a given set of tags, ordering by a given column, etc. Eventually it will clutter your controller action.

Instead, Laragear Refine moves that logic to its own "Refiner" object, which is handled by only issuing the refiner class name to the `refineBy()` method of the query builder.

```php
use App\Models\Post;
use Illuminate\Http\Request;
use App\Http\Refiners\PostRefiner;

public function all(Request $request)
{
    return Post::query()->refineBy(PostRefiner::class);
}
```

The magic is simple: each refiner method will be executed as long the corresponding URL parameter key is present in the incoming request. Keys are automatically normalized to `camelCase` to match the method, so the `author_id` key will execute `authorId()` with its value.

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

## Creating a Refiner

Call the `make:refiner` with the name of the Refiner you want to create.

```shell
php artisan make:refiner PostRefiner
```

You will receive the refiner in the `app\Http\Refiners` directory.

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

You may define the methods you want to be executed when a URL parameter key is present by simple creating these as public, using their corresponding `camelCase` key. 

```php
// For `author_id=value`
public function authorId($query, mixed $value, Request $request)
{
    // ...
}
```

All methods you set in the Refiner class receive the Query Builder instance, the value from the request, and the `Illuminate\Http\Request` instance itself. Inside each method, you're free to modify the Query Builder as you see fit, or even call authorization gates or check the user permissions.

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

On rare occasions, you may have a method you don't want to be executed as part of the refinement procedure. In that case, you may instruct which URL parameters keys should be used to match their respective methods with the `getKeys()` method.

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

Alternatively, if you're using a `FormRequest`, you can always return the keys of the validated data.

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

### Obligatory keys

Sometimes you will want to run a method even if the key is not set in the URL parameters. For that, use the `getObligatoryKeys()` method to return the keys (and methods) that should always run.

For example, if we want to run the `orderBy()` method regardless if there is the `order_by` URL parameter, we only need to return that key.

```php
public function getObligatoryKeys(): array
{
    return ['order_by'];
}
```

Then, our method should be able to receive `null` for when the URL parameter doesn't exist.

```php
public function orderBy($query, ?string $value, Request $request)
{
    // If the value was not set, use the publishig timestamp as the column to sort.
    $value ??= 'published_at'
    
    $query->orderBy($value, $request->query('order') ?? 'asc');
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

You may also include validation logic into your Refiner by implementing the `ValidateRefiner` interface. From there, you should set your validation rules, and optionally your messages and custom attributes if you need to.

This is great if you expect a key to always be required in the query, as the `validationRules()` is an excellent place to do it.

```php
namespace App\Http\Refiners;

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

> [!NOTE]
> 
> Validation rules will run verbatim over the Request Query, not the request input.

## Applying a Refiner

In your Builder instance, simply call `refineBy()` with the name of the Refiner class (or its alias if you registered it on the application container) to apply to the query.

```php
use App\Models\Post;
use App\Http\Refiners\PostRefiner;

Post::refineBy(PostRefiner::class)->paginate();
```

The `refineBy()` is a macro registered to the Eloquent Builder and the base Query Builder, and you can use it even after your own custom refinements.

```php
use App\Http\Requests\PostRequest;
use App\Http\Refiners\PostRefiner;
use Illuminate\Support\Facades\DB;

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

You can override the keys to look for on the Request at runtime by issuing the keys as second argument. These will replace the [custom keys](#only-some-keys) you have set in the class.

```php
public function all(Request $request)
{
    $validated = $request->validate([
        // ...
    ])

    Post::query()->refineBy(PostFilter::class, ['author_id', 'order', 'order_by'])->paginate();
}
```

## Model Refiner

You may use the included `ModelRefiner` to quickly create a refiner for a model query, and automatically manage columns, relations, count, relation sum, trashed, and order.

Simply call the `make:refiner` with the `--model` option.

```shell
php artisan make:refiner ArticleRefiner --model 
```

You will receive a refiner extending the base `ModelRefiner`. Here you should set the relations, columns, sums, and order the refiner should use to validate the URL query.

```php
namespace App\Http\Refiners;

use Laragear\Refine\ModelRefiner;

class ArticleRefiner extends ModelRefiner
{
    /**
     * Return the columns that should only be included in the query.
     *
     * @return string[]
     */
    protected function getOnlyColumns(): array
    {
        return [];
    }

    /**
     * Return the columns that should be removed from the query.
     *
     * @return string[]
     */
    protected function getExceptColumns(): array
    {
        return [];
    }

    /**
     * Return the relations that should exist for the query.
     *
     * @return string[]
     */
    protected function getHasRelations(): array
    {
        return [];
    }

    /**
     * Return the relations that should be missing for the query.
     *
     * @return string[]
     */
    protected function getMissingRelations(): array
    {
        return [];
    }

    /**
     * Return the relations that can be queried.
     *
     * @return string[]
     */
    protected function getWithRelations(): array
    {
        return [];
    }

    /**
     * Return the relations that can be counted.
     *
     * @return string[]
     */
    protected function getCountRelations(): array
    {
        return [];
    }

    /**
     * Return the relations and the columns that should be sum.
     *
     * @return string[]
     */
    protected function getSumRelations(): array
    {
        // Separate the relation name using hyphen (`-`). For example, `published_posts-votes`.
        return [];
    }

    /**
     * Return the columns that can be used to sort the query.
     *
     * @return string[]
     */
    protected function getOrderByColumns(): array
    {
        return [];
    }
}
```

As with a normal refiner, you may also override the validation keys and/or the keys to check in the request, and even how each query key should be _refined_.

```php
namespace App\Http\Refiners;

use Illuminate\Support\Arr;
use Laragear\Refine\ModelRefiner;

class ArticleRefiner extends ModelRefiner
{
    public function validationRules(): array
    {
        return Arr::only(parent::validationRules(), ['with', 'with.*', 'order', 'order_by']);
    }

    public function getKeys(Request $request): array
    {
        return Arr::only(parent::getKeys(), ['with', 'order', 'order_by']);
    }
    
    public function query(Builder $query, string $search): void
    {
        $query->where('name', 'like', $this->normaliseQuery($search));
    }
    
    // ...
}
```

> [!TIP]
> 
> Even if you validate relations using `snake_case`, when building the query for relations, these will be automatically transformed into `camelCase`, even if these are separated by `dot.notation`.

### Sum relations

The `ModelRefiner` supports summing relations columns using the relation name and the column separated by a hyphen. You may want to set an array of relations and possible columns to sum by returning them in the `getSumRelations()` method.

```php
protected function getSumRelations(): array
{
    return [
        'user_comments-claps',
        'user_comments-down_votes',
        'user_comments-up_votes',
    ];
}
```

## Laravel Octane compatibility

- There are no singletons using a stale application instance.
- There are no singletons using a stale config instance.
- There are no singletons using a stale request instance.
- A static property being written is the cache of Refiner methods which grows by every unique Refiner that runs.
- A static property being written is the cache of Abstract Refiner methods which is only written once.

There should be no problems using this package with Laravel Octane.

If you can always flush the cached refiner methods using the `RefineQuery::flushCachedRefinerMethods()`.

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

# License

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2024 Laravel LLC.
