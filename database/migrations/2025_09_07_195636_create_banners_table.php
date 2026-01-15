<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id(); // معرف البانر
            $table->string('title'); // عنوان البانر
            $table->string('image'); // رابط الصورة
            $table->string('third_party_link')->nullable(); // رابط خارجي اختياري
            $table->foreignId('item_id')->nullable()->constrained('items')->onDelete('set null'); // Item مرتبط
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null'); // Category مرتبط
            $table->boolean('status')->default(1); // الحالة (مفعل/غير مفعل)
            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};

