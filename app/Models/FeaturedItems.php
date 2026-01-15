<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FeaturedItems extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'item_id',
        'package_id',
        'user_purchased_package_id',
    ];

    /**
     * لإضافة section_id لكل JSON بدون تخزينه في قاعدة البيانات
     */
    protected $appends = ['section_id'];

    public function getSectionIdAttribute()
    {
        return null; // ثابت حسب المطلوب
    }

    /**
     * العلاقة مع المستخدم — إن أردت استخدامها مستقبلاً
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * فلترة العروض المفعّلة فقط
     */
    public function scopeOnlyActive($query)
    {
        return $query->whereDate('start_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereDate('end_date', '>=', now()->toDateString())
                  ->orWhereNull('end_date');
            });
    }

    /**
     * تحويل مسار الصورة إلى URL كامل
     */
    public function getImageAttribute($image)
    {
        if (!empty($image)) {
            return url(Storage::url($image));
        }
        return $image;
    }

    /**
     * العلاقة مع الـ Item
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}

