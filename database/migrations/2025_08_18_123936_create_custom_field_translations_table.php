<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    if (!Schema::hasTable('custom_field_translations')) {
        Schema::create('custom_field_translations', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')
                  ->references('id')
                  ->on('custom_fields')
                  ->onDelete('cascade');

            $table->foreignId('language_id')
                  ->references('id')
                  ->on('languages')
                  ->onDelete('cascade');

            $table->string('name', 125);

            // خيارات مترجمة للقيم إذا النوع dropdown / radio / checkbox
            $table->json('values')->nullable();

            $table->timestamps();

            $table->unique(['custom_field_id', 'language_id']);
        });
    }
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_translations');
    }
};
