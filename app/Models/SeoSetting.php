<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Storage;

class SeoSetting extends Model
{
    use HasFactory;

    protected $fillable =[
         'page',
         'title',
         'description',
         'keywords',
         'image'
    ];

    public function getImageAttribute($image) {
        if (!empty($image)) {
            return url(Storage::url($image));
        }
        return $image;
    }
    public function scopeSort($query, $column, $order) {

        $query = $query->orderBy($column, $order);

        return $query->select('seo_settings.*');
    }
}
