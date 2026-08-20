<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Defect;
use App\Models\Expense;
use App\Models\MaintenanceTemplate;
use App\Models\OdometerReading;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleMaintenancePlan;
use App\Observers\ExpenseObserver;
use App\Observers\VehicleObserver;
use App\Policies\AttachmentPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\DefectPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\MaintenanceTemplatePolicy;
use App\Policies\OdometerReadingPolicy;
use App\Policies\ServiceRecordPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehicleDocumentPolicy;
use App\Policies\VehicleMaintenancePlanPolicy;
use App\Policies\VehiclePolicy;
use App\Services\Odometer\ManualOdometerProvider;
use App\Services\Odometer\OdometerProviderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OdometerProviderInterface::class, ManualOdometerProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        Relation::enforceMorphMap([
            'attachment' => Attachment::class,
            'audit_log' => AuditLog::class,
            'defect' => Defect::class,
            'expense' => Expense::class,
            'maintenance_template' => MaintenanceTemplate::class,
            'odometer_reading' => OdometerReading::class,
            'service_record' => ServiceRecord::class,
            'user' => User::class,
            'vehicle' => Vehicle::class,
            'vehicle_document' => VehicleDocument::class,
            'vehicle_maintenance_plan' => VehicleMaintenancePlan::class,
        ]);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(OdometerReading::class, OdometerReadingPolicy::class);
        Gate::policy(MaintenanceTemplate::class, MaintenanceTemplatePolicy::class);
        Gate::policy(VehicleMaintenancePlan::class, VehicleMaintenancePlanPolicy::class);
        Gate::policy(Defect::class, DefectPolicy::class);
        Gate::policy(ServiceRecord::class, ServiceRecordPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(VehicleDocument::class, VehicleDocumentPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        Vehicle::observe(VehicleObserver::class);
        Expense::observe(ExpenseObserver::class);
    }
}
