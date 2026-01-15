<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{public function up(): void    {
Schema::create('number_otps', function (Blueprint $table) {         
$table->id();
$table->string('number')->index();
$table->string('otp');
$table->timestamp('expire_at');
$table->integer('attempts')->default(0);            
$table->boolean('verified')->default(false);           
$table->timestamps(); });
}
 public function down(): void{
Schema::dropIfExists('number_otps');}};
