<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number', 32)->unique();
            $table->string('vin', 17)->nullable()->unique();
            $table->string('make', 80);
            $table->string('model', 80);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->decimal('current_odometer', 12, 1)->default(0);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('odometer_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('reading', 12, 1);
            $table->timestamp('recorded_at')->index();
            $table->string('source', 32)->default('manual');
            $table->boolean('is_admin_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'recorded_at']);
        });

        Schema::create('maintenance_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('category', 32)->index();
            $table->decimal('interval_km', 12, 1)->nullable();
            $table->unsignedInteger('interval_days')->nullable();
            $table->decimal('soon_km', 12, 1)->default(1000);
            $table->unsignedInteger('soon_days')->default(30);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('vehicle_maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_template_id')->constrained()->restrictOnDelete();
            $table->decimal('interval_km', 12, 1)->nullable();
            $table->unsignedInteger('interval_days')->nullable();
            $table->decimal('last_service_odometer', 12, 1)->nullable();
            $table->date('last_service_date')->nullable();
            $table->decimal('next_due_odometer', 12, 1)->nullable()->index();
            $table->date('next_due_date')->nullable()->index();
            $table->string('status', 24)->default('due')->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_reminded_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['vehicle_id', 'maintenance_template_id'], 'vehicle_template_unique');
        });

        Schema::create('defects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description');
            $table->string('category', 32)->index();
            $table->string('severity', 24)->index();
            $table->string('status', 24)->default('open')->index();
            $table->timestamp('reported_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['vehicle_id', 'status']);
        });

        Schema::create('defect_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('defect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('note')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['defect_id', 'changed_at']);
        });

        Schema::create('service_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained('vehicle_maintenance_plans')->nullOnDelete();
            $table->foreignId('defect_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->string('provider_name', 160)->nullable();
            $table->string('reference_number', 80)->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->decimal('odometer', 12, 1);
            $table->char('currency', 3)->default('EUR');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['vehicle_id', 'completed_at']);
        });

        Schema::create('service_record_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_record_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->default('part')->index();
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_record_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 32)->index();
            $table->string('vendor', 160)->nullable();
            $table->string('reference_number', 80)->nullable();
            $table->date('incurred_on')->index();
            $table->char('currency', 3)->default('EUR');
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['vehicle_id', 'incurred_on']);
        });

        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->string('number', 100)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->string('status', 24)->default('valid')->index();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['vehicle_id', 'type']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('attachable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 64)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sha256', 64)->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('auditable');
            $table->string('event', 80)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_subject_index');
        });

        Schema::create('reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('remindable_type');
            $table->unsignedBigInteger('remindable_id');
            $table->string('kind', 64);
            $table->date('delivery_date');
            $table->timestamps();
            $table->unique(
                ['user_id', 'remindable_type', 'remindable_id', 'kind', 'delivery_date'],
                'reminder_delivery_unique'
            );
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reminder_deliveries');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('vehicle_documents');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('service_record_items');
        Schema::dropIfExists('service_records');
        Schema::dropIfExists('defect_status_histories');
        Schema::dropIfExists('defects');
        Schema::dropIfExists('vehicle_maintenance_plans');
        Schema::dropIfExists('maintenance_templates');
        Schema::dropIfExists('odometer_readings');
        Schema::dropIfExists('vehicles');
    }
};
