<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_five_province_denominators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_year_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('outpatient_l')->nullable();
            $table->unsignedBigInteger('outpatient_p')->nullable();
            $table->unsignedBigInteger('inpatient_l')->nullable();
            $table->unsignedBigInteger('inpatient_p')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('table_five_province_denominator_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('denominator_id')->constrained('table_five_province_denominators')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_five_province_denominator_revisions');
        Schema::dropIfExists('table_five_province_denominators');
    }
};
