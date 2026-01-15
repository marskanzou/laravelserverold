<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة (تطبيق التغييرات).
     */
    public function up(): void
    {
        Schema::table('item_custom_field_values', function (Blueprint $table) {
            // إضافة الفهرس النصي الكامل
            $table->fullText('value');
        });
    }

    /**
     * التراجع عن الهجرة (إلغاء التغييرات).
     */
    public function down(): void
    {
        Schema::table('item_custom_field_values', function (Blueprint $table) {
            // حذف الفهرس (اسم الفهرس الافتراضي هو اسم الجدول_العمود_fulltext)
            $table->dropIndex('item_custom_field_values_value_fulltext'); 
        });
    }
};
