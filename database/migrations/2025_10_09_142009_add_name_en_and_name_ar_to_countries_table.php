<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddNameEnAndNameArToCountriesTable extends Migration
{
    public function up()
    {
        Schema::table('countries', function (Blueprint $table) {
            // أضف الأعمدة كقابلة لأن تكون null مبدئياً
            if (!Schema::hasColumn('countries', 'name_en')) {
                $table->string('name_en', 191)->nullable()->after('name');
            }
            if (!Schema::hasColumn('countries', 'name_ar')) {
                $table->string('name_ar', 191)->nullable()->after('name_en');
            }
        });

        // انسخ القيم من name إلى name_en (مرة واحدة)
        DB::statement('UPDATE countries SET name_en = name');

        // أضف index للأعمدة كي يتحسن أداء البحث
        Schema::table('countries', function (Blueprint $table) {
            $table->index('name_en');
            $table->index('name_ar');
        });
    }

    public function down()
    {
        Schema::table('countries', function (Blueprint $table) {
            // إسقاط الفهارس ثم الأعمدة
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            // dropIndex by name safe approach:
            $table->dropIndex(['name_en']);
            $table->dropIndex(['name_ar']);

            if (Schema::hasColumn('countries', 'name_ar')) {
                $table->dropColumn('name_ar');
            }
            if (Schema::hasColumn('countries', 'name_en')) {
                $table->dropColumn('name_en');
            }
        });
    }
}
