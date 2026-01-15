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
    Schema::create('email_verifications', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();   // الإيميل
        $table->string('otp');               // الكود OTP
        $table->timestamp('expires_at');     // تاريخ انتهاء الكود
        $table->timestamps();                // created_at + updated_at
    });
}

public function down(): void
{
    Schema::dropIfExists('email_verifications');
}

};
