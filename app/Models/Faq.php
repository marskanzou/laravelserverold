<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'answer',
    ];

    /**
     * العلاقة مع جدول الترجمات (FaqTranslation)
     */
    public function translations()
    {
        return $this->hasMany(FaqTranslation::class, 'faq_id');
    }
}

