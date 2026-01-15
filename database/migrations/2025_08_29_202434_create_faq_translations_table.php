<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('faq_translations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('faq_id')->constrained('faqs')->onDelete('cascade');
        $table->string('locale', 5)->default('ar'); // يمكن لاحقاً إضافة لغات أخرى
        $table->text('question');
        $table->text('answer');
        $table->timestamps();

        $table->unique(['faq_id', 'locale']); // لكل faq لغة واحدة فقط
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faq_translations');
    }
};
