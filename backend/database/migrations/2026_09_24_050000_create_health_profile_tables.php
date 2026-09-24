<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('role')->constrained()->restrictOnDelete();
        });

        Schema::create('reporting_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('reporting_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_year_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['reporting_year_id', 'code']);
        });

        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_table_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('data_type');
            $table->string('unit')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['reporting_table_id', 'code']);
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->foreignId('reporting_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('reporting_table_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('not_started');
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['region_id', 'reporting_year_id', 'reporting_table_id']);
        });

        Schema::create('indicator_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained()->restrictOnDelete();
            $table->decimal('numeric_value', 20, 4)->nullable();
            $table->text('text_value')->nullable();
            $table->date('date_value')->nullable();
            $table->boolean('not_applicable')->default(false);
            $table->text('not_applicable_reason')->nullable();
            $table->timestamps();
            $table->unique(['submission_id', 'indicator_id']);
        });

        Schema::create('indicator_value_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_value_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('old_value')->nullable();
            $table->json('new_value');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('submission_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_events');
        Schema::dropIfExists('indicator_value_revisions');
        Schema::dropIfExists('indicator_values');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('indicators');
        Schema::dropIfExists('reporting_tables');
        Schema::dropIfExists('reporting_years');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
        });

        Schema::dropIfExists('regions');
    }
};
