<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title',
        'image',
        'third_party_link',
        'item_id',
        'category_id',
        'status'
    ];

    // علاقة بالـ Item
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    // علاقة بالـ Category
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}

