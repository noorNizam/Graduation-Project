<?php

namespace App\Models;

use Database\Factories\ServingCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServingCategory extends Model
{
    use HasFactory;

    protected static function newFactory(): ServingCategoryFactory
    {
        return ServingCategoryFactory::new();
    }

    protected $table = 'serving_categories';

    protected $fillable = [
        'name',
        'parent_id',
    ];

    /**
     * Parent category (nullable).
     */
    public function parent()
    {
        return $this->belongsTo(ServingCategory::class, 'parent_id');
    }

    /**
     * Child categories.
     */
    public function children()
    {
        return $this->hasMany(ServingCategory::class, 'parent_id');
    }
}
