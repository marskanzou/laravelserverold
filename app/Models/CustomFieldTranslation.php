<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_field_id',
        'language_id',
        'name',
        'values',
    ];

    protected $casts = [
        'values' => 'array', // لتحويل JSON تلقائياً إلى مصفوفة
    ];

    public function customField()
    {
        return $this->belongsTo(CustomField::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}


