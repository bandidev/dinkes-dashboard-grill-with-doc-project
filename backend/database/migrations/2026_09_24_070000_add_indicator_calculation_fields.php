<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->string('value_kind')->default('base')->after('data_type');
            $table->json('formula')->nullable()->after('value_kind');
            $table->unsignedTinyInteger('decimal_places')->default(0)->after('unit');
            $table->boolean('is_active')->default(true)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->dropColumn(['value_kind', 'formula', 'decimal_places', 'is_active']);
        });
    }
};
