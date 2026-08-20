<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('category', 32)->default('truck')->index();
            $table->string('body_type', 48)->nullable()->index();
            $table->string('fuel_type', 24)->default('diesel')->index();
            $table->date('commissioned_on')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('primary_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
        });

        Schema::table('service_records', function (Blueprint $table) {
            $table->string('service_type', 32)->default('other')->index();
            $table->timestamp('planned_at')->nullable()->index();
            $table->unsignedInteger('downtime_minutes')->nullable();
            $table->date('warranty_until_date')->nullable()->index();
            $table->decimal('warranty_until_odometer', 12, 1)->nullable()->index();
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();
        });

        Schema::table('service_record_items', function (Blueprint $table) {
            $table->string('unit', 24)->default('unit');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
        });

        DB::table('service_record_items')->update([
            'net_amount' => DB::raw('total_amount'),
        ]);

        Schema::table('defects', function (Blueprint $table) {
            $table->decimal('detected_odometer', 12, 1)->nullable()->index();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('source', 24)->default('manual')->index();
            $table->string('source_reference', 160)->nullable()->index();
        });

        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->unsignedSmallInteger('warning_days')->default(30);
        });

        Schema::table('maintenance_templates', function (Blueprint $table) {
            $table->json('recommended_operations')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_templates', function (Blueprint $table) {
            $table->dropColumn('recommended_operations');
        });

        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->dropColumn('warning_days');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_reference']);
        });

        Schema::table('defects', function (Blueprint $table) {
            $table->dropColumn('detected_odometer');
        });

        Schema::table('service_record_items', function (Blueprint $table) {
            $table->dropColumn(['unit', 'tax_rate', 'net_amount', 'tax_amount']);
        });

        Schema::table('service_records', function (Blueprint $table) {
            $table->dropColumn([
                'service_type',
                'planned_at',
                'downtime_minutes',
                'warranty_until_date',
                'warranty_until_odometer',
                'description',
                'internal_notes',
            ]);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_attachment_id');
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn(['category', 'body_type', 'fuel_type', 'commissioned_on']);
        });
    }
};
