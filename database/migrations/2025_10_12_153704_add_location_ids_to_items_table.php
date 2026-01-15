<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationIdsToItemsTable extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            // نتحقق قبل الإضافة لتجنب الأخطاء إن كانت الأعمدة موجودة بالفعل
            if (!Schema::hasColumn('items', 'country_id')) {
                $table->unsignedBigInteger('country_id')->nullable()->after('country');
                $table->index('country_id');
            }

            if (!Schema::hasColumn('items', 'state_id')) {
                $table->unsignedBigInteger('state_id')->nullable()->after('state');
                $table->index('state_id');
            }

            if (!Schema::hasColumn('items', 'city_id')) {
                $table->unsignedBigInteger('city_id')->nullable()->after('city');
                $table->index('city_id');
            }
        });
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            // إسقاط الفهارس ثم الأعمدة فقط إن كانت موجودة
            if (Schema::hasColumn('items', 'country_id')) {
                $table->dropIndex(['country_id']);
                $table->dropColumn('country_id');
            }

            if (Schema::hasColumn('items', 'state_id')) {
                $table->dropIndex(['state_id']);
                $table->dropColumn('state_id');
            }

            if (Schema::hasColumn('items', 'city_id')) {
                $table->dropIndex(['city_id']);
                $table->dropColumn('city_id');
            }
        });
    }
}

