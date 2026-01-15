<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddNameEnAndNameArToStatesTable extends Migration
{
    public function up()
    {
        Schema::table('states', function (Blueprint $table) {
            if (!Schema::hasColumn('states', 'name_en')) {
                $table->string('name_en', 191)->nullable()->after('name');
            }
            if (!Schema::hasColumn('states', 'name_ar')) {
                $table->string('name_ar', 191)->nullable()->after('name_en');
            }
        });

        // انسخ الاسم الحالي إلى name_en دفعة واحدة (آمن طالما الاسم الحالي إنجليزي)
        DB::statement('UPDATE states SET name_en = name');

        // أنشئ فهارس لتحسين البحث
        Schema::table('states', function (Blueprint $table) {
            $table->index('name_en');
            $table->index('name_ar');
            $table->index('country_id');
        });
    }

    public function down()
    {
        Schema::table('states', function (Blueprint $table) {
            // حاول إسقاط الفهارس ثم الأعمدة (قد تحتاج أسماء الفهارس عند بعض قواعد البيانات)
            $table->dropIndex(['name_en']);
            $table->dropIndex(['name_ar']);
            $table->dropIndex(['country_id']);

            if (Schema::hasColumn('states', 'name_ar')) {
                $table->dropColumn('name_ar');
            }
            if (Schema::hasColumn('states', 'name_en')) {
                $table->dropColumn('name_en');
            }
        });
    }
}

