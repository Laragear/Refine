<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MockModel extends Model
{
    use SoftDeletes;

    public function baz(): HasOne
    {
        return $this->hasOne(static::class);
    }

    public function quz(): HasOne
    {
        return $this->hasOne(static::class);
    }

    public function qux(): HasOne
    {
        return $this->hasOne(static::class);
    }

    public function quux(): HasOne
    {
        return $this->hasOne(static::class);
    }

    public function corge(): HasOne
    {
        return $this->hasOne(static::class);
    }
}
