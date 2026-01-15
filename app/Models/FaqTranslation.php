<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqTranslation extends Model
{
    protected $fillable = ['faq_id', 'locale', 'question', 'answer'];

    public function faq()
    {
        return $this->belongsTo(Faq::class);
    }
}

