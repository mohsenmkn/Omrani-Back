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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('نام پروژه');
            $table->string('code')->unique()->comment('کد اختصاصی پروژه');
            $table->string('location')->nullable()->comment('محل اجرای پروژه');
            $table->decimal('budget', 15, 2)->nullable()->comment('بودجه مصوب');
            $table->date('start_date')->nullable()->comment('تاریخ شروع');
            $table->date('end_date')->nullable()->comment('تاریخ پایان پیش‌بینی شده');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'halted'])->default('pending')->comment('وضعیت پروژه');
            $table->text('description')->nullable()->comment('توضیحات تکمیلی');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
