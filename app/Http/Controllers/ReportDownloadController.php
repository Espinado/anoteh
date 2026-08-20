<?php

namespace App\Http\Controllers;

use App\Models\Defect;
use App\Models\Expense;
use App\Models\ServiceRecord;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleMaintenancePlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportDownloadController extends Controller
{
    private const TYPES = [
        'expenses', 'expenses_by_category', 'cost_per_km', 'service_history', 'defects',
        'maintenance_period', 'downtime', 'expiring_documents',
    ];

    public function __invoke(Request $request): Response
    {
        $filters = $this->validated($request, true);
        [$headings, $rows] = $this->reportData($filters);
        $filename = $filters['type'].'-'.now('Europe/Riga')->format('Y-m-d');

        if ($filters['format'] === 'xlsx') {
            $export = new class($rows, $headings) implements FromCollection, WithHeadings
            {
                public function __construct(private Collection $rows, private array $headers) {}

                public function collection(): Collection
                {
                    return $this->rows;
                }

                public function headings(): array
                {
                    return $this->headers;
                }
            };

            return Excel::download($export, $filename.'.xlsx');
        }

        return response()->streamDownload(function () use ($rows, $headings): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headings);
            $rows->each(fn ($row) => fputcsv($stream, $row));
            fclose($stream);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Request $request): View
    {
        $filters = $this->validated($request, false);
        [$headings, $rows] = $this->reportData($filters);

        return view('reports.print', compact('filters', 'headings', 'rows'));
    }

    private function validated(Request $request, bool $download): array
    {
        $rules = [
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', 'string', 'max:40'],
        ];
        if ($download) {
            $rules['format'] = ['required', 'in:csv,xlsx'];
        }

        return $request->validate($rules);
    }

    private function reportData(array $filters): array
    {
        return match ($filters['type']) {
            'expenses' => $this->modelReport(
                Expense::class, 'incurred_on', $filters,
                ['ID', 'Vehicle', 'Date', 'Category', 'Vendor', 'Net', 'Tax', 'Total', 'Notes'],
                ['id', 'vehicle_id', 'incurred_on', 'category', 'vendor', 'net_amount', 'tax_amount', 'total_amount', 'notes'],
            ),
            'expenses_by_category' => $this->expensesByCategory($filters),
            'service_history' => $this->modelReport(
                ServiceRecord::class, 'started_at', $filters,
                ['ID', 'Vehicle', 'Type', 'Status', 'Planned', 'Started', 'Completed', 'Odometer', 'Provider', 'Total'],
                ['id', 'vehicle_id', 'service_type', 'status', 'planned_at', 'started_at', 'completed_at', 'odometer', 'provider_name', 'total_amount'],
            ),
            'defects' => $this->modelReport(
                Defect::class, 'reported_at', $filters,
                ['ID', 'Vehicle', 'Reported', 'Title', 'Category', 'Severity', 'Status', 'Detected odometer'],
                ['id', 'vehicle_id', 'reported_at', 'title', 'category', 'severity', 'status', 'detected_odometer'],
            ),
            'maintenance_period' => $this->modelReport(
                VehicleMaintenancePlan::class, 'next_due_date', $filters,
                ['ID', 'Vehicle', 'Template', 'Status', 'Next due date', 'Next due odometer'],
                ['id', 'vehicle_id', 'maintenance_template_id', 'status', 'next_due_date', 'next_due_odometer'],
            ),
            'downtime' => $this->modelReport(
                ServiceRecord::class, 'started_at', $filters,
                ['ID', 'Vehicle', 'Type', 'Started', 'Completed', 'Downtime minutes', 'Description'],
                ['id', 'vehicle_id', 'service_type', 'started_at', 'completed_at', 'downtime_minutes', 'description'],
            ),
            'expiring_documents' => $this->modelReport(
                VehicleDocument::class, 'expires_on', $filters,
                ['ID', 'Vehicle', 'Type', 'Number', 'Status', 'Expires', 'Warning days'],
                ['id', 'vehicle_id', 'type', 'number', 'status', 'expires_on', 'warning_days'],
            ),
            'cost_per_km' => $this->costPerKm($filters),
        };
    }

    private function modelReport(string $model, string $dateColumn, array $filters, array $headings, array $columns): array
    {
        Gate::authorize('viewAny', $model);
        $query = $model::query();
        $this->applyFilters($query, $dateColumn, $filters);
        $rows = $query->orderBy($dateColumn)->get()->map(fn ($row) => collect($columns)->map(function ($column) use ($row) {
            $value = data_get($row, $column);
            if ($value instanceof \BackedEnum) {
                return $value->value;
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->setTimezone(new \DateTimeZone('Europe/Riga'))->format('d.m.Y H:i');
            }

            return $value;
        })->all());

        return [$headings, $rows];
    }

    private function costPerKm(array $filters): array
    {
        Gate::authorize('viewAny', Expense::class);
        Gate::authorize('viewAny', Vehicle::class);
        $vehicles = Vehicle::query()->when($filters['vehicle_id'] ?? null, fn ($query, $id) => $query->whereKey($id))->get();
        $from = filled($filters['from'] ?? null)
            ? CarbonImmutable::parse($filters['from'], 'Europe/Riga')->startOfDay()->utc()
            : null;
        $to = filled($filters['to'] ?? null)
            ? CarbonImmutable::parse($filters['to'], 'Europe/Riga')->endOfDay()->utc()
            : CarbonImmutable::now('UTC');

        $rows = $vehicles->map(function (Vehicle $vehicle) use ($filters, $from, $to) {
            $query = Expense::where('vehicle_id', $vehicle->id);
            $this->applyFilters($query, 'incurred_on', $filters);
            $cost = (float) $query->sum('total_amount');
            $end = $vehicle->odometerReadings()->where('recorded_at', '<=', $to)->latest('recorded_at')->first();
            $start = $from
                ? $vehicle->odometerReadings()->where('recorded_at', '<', $from)->latest('recorded_at')->first()
                : $vehicle->odometerReadings()->where('recorded_at', '<=', $to)->oldest('recorded_at')->first();
            if (! $start && $from) {
                $start = $vehicle->odometerReadings()
                    ->whereBetween('recorded_at', [$from, $to])
                    ->oldest('recorded_at')
                    ->first();
            }
            $distance = $start && $end ? max(0, (float) $end->reading - (float) $start->reading) : 0;

            return [
                $vehicle->registration_number,
                $start?->reading,
                $end?->reading,
                $distance,
                $cost,
                $distance > 0 ? round($cost / $distance, 4) : null,
            ];
        });

        return [['Vehicle', 'Start odometer', 'End odometer', 'Distance', 'Total cost', 'Cost per km'], $rows];
    }

    private function expensesByCategory(array $filters): array
    {
        Gate::authorize('viewAny', Expense::class);
        $query = Expense::query();
        $this->applyFilters($query, 'incurred_on', $filters);
        $rows = $query
            ->selectRaw('category, currency, COUNT(*) as expense_count, SUM(net_amount) as net_total, SUM(tax_amount) as tax_total, SUM(total_amount) as grand_total')
            ->groupBy('category', 'currency')
            ->orderBy('category')
            ->get()
            ->map(fn ($row) => [
                $row->category instanceof \BackedEnum ? $row->category->value : $row->category,
                $row->currency,
                (int) $row->expense_count,
                $row->net_total,
                $row->tax_total,
                $row->grand_total,
            ]);

        return [['Category', 'Currency', 'Count', 'Net', 'Tax', 'Total'], $rows];
    }

    private function applyFilters(Builder $query, string $dateColumn, array $filters): void
    {
        $table = $query->getModel()->getTable();
        $query
            ->when(($filters['vehicle_id'] ?? null) && Schema::hasColumn($table, 'vehicle_id'), fn ($query) => $query->where('vehicle_id', $filters['vehicle_id']))
            ->when(($filters['from'] ?? null) && Schema::hasColumn($table, $dateColumn), fn ($query) => $query->whereDate($dateColumn, '>=', $filters['from']))
            ->when(($filters['to'] ?? null) && Schema::hasColumn($table, $dateColumn), fn ($query) => $query->whereDate($dateColumn, '<=', $filters['to']))
            ->when(($filters['status'] ?? null) && Schema::hasColumn($table, 'status'), fn ($query) => $query->where('status', $filters['status']))
            ->when(($filters['category'] ?? null) && Schema::hasColumn($table, 'category'), fn ($query) => $query->where('category', $filters['category']));
    }
}
