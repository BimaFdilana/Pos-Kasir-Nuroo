<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'phone', 'address'];

    public function outlets(): HasMany { return $this->hasMany(Outlet::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function categories(): HasMany { return $this->hasMany(Category::class); }
    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function sales(): HasMany { return $this->hasMany(Sale::class); }
}
