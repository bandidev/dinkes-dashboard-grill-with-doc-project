<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporting_tables', function (Blueprint $table) {
            $table->string('source_sheet')->nullable()->after('description');
            $table->string('mapping_status')->default('ready')->after('source_sheet');
            $table->json('source_metadata')->nullable()->after('mapping_status');
        });

        Schema::create('catalog_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_year_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('checksum', 64);
            $table->json('report');
            $table->timestamp('imported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_imports');

        Schema::table('reporting_tables', function (Blueprint $table) {
            $table->dropColumn(['source_sheet', 'mapping_status', 'source_metadata']);
        });
    }
};
