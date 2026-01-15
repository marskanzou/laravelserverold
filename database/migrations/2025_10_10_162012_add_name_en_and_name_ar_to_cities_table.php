<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddNameEnAndNameArToCitiesTable extends Migration
{
    public function up()
    {
        Schema::table('cities', function (Blueprint $table) {
            if (!Schema::hasColumn('cities', 'name_en')) {
                $table->string('name_en', 191)->nullable()->after('name');
            }
            if (!Schema::hasColumn('cities', 'name_ar')) {
                $table->string('name_ar', 191)->nullable()->after('name_en');
            }
        });

        // انسخ القيم من name إلى name_en (مرة واحدة)
        DB::statement('UPDATE cities SET name_en = name');

        // أضف فهارس لتحسين أداء البحث
        Schema::table('cities', function (Blueprint $table) {
            $table->index('name_en');
            $table->index('name_ar');
            $table->index('state_id');
            $table->index('country_id');
        });
    }

    public function down()
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex(['name_en']);
            $table->dropIndex(['name_ar']);
            $table->dropIndex(['state_id']);
            $table->dropIndex(['country_id']);

            if (Schema::hasColumn('cities', 'name_ar')) {
                $table->dropColumn('name_ar');
            }
            if (Schema::hasColumn('cities', 'name_en')) {
                $table->dropColumn('name_en');
            }
        });
    }
}

