<?php

namespace App\Livewire;

use App\Models\Vehicle;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AdminUi extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $section = 'vehicles';

    public string $mode = 'index';

    public ?int $recordId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sort = 'registration_number';

    #[Url]
    public string $direction = 'asc';

    #[Url]
    public int $perPage = 15;

    public array $form = [];

    public function mount(string $section = 'vehicles', string $mode = 'index', ?int $recordId = null): void
    {
        abort_unless($section === 'vehicles', 404);

        $this->section = $section;
        $this->mode = $mode;
        $this->recordId = $recordId;
        $this->form = [
            'registration_number' => '',
            'make' => '',
            'model' => '',
            'year' => '',
            'vin' => '',
            'fuel_type' => 'diesel',
            'inspection_until' => '',
            'octa_until' => '',
        ];

        if ($mode === 'create') {
            $this->authorize('create', Vehicle::class);
        }

        if ($recordId !== null) {
            $vehicle = $this->vehicle();
            $this->authorize($mode === 'edit' ? 'update' : 'view', $vehicle);
            $this->form = array_merge($this->form, [
                'registration_number' => $vehicle->registration_number,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year ?? '',
                'vin' => $vehicle->vin ?? '',
                'fuel_type' => $vehicle->fuel_type?->value ?? (string) $vehicle->getRawOriginal('fuel_type'),
                'inspection_until' => $vehicle->inspection_until?->format('Y-m-d') ?? '',
                'octa_until' => $vehicle->octa_until?->format('Y-m-d') ?? '',
            ]);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }

        $this->resetPage();
    }

    public function setSort(string $value): void
    {
        [$column, $direction] = array_pad(explode(':', $value, 2), 2, null);

        if (! in_array($column, $this->sortableColumns(), true) || ! in_array($direction, ['asc', 'desc'], true)) {
            return;
        }

        $this->sort = $column;
        $this->direction = $direction;
        $this->resetPage();
    }

    public function save(): void
    {
        $vehicle = $this->recordId ? $this->vehicle() : new Vehicle;
        $this->authorize($this->recordId ? 'update' : 'create', $this->recordId ? $vehicle : Vehicle::class);

        $validated = $this->validate([
            'form.registration_number' => ['required', 'string', 'max:32', Rule::unique('vehicles', 'registration_number')->ignore($this->recordId)],
            'form.make' => ['required', 'string', 'max:80'],
            'form.model' => ['required', 'string', 'max:80'],
            'form.year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'form.vin' => ['nullable', 'string', 'size:17', Rule::unique('vehicles', 'vin')->ignore($this->recordId)],
            'form.fuel_type' => ['required', Rule::in(['petrol', 'diesel'])],
            'form.inspection_until' => ['nullable', 'date'],
            'form.octa_until' => ['nullable', 'date'],
        ])['form'];

        $data = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);

        DB::transaction(fn () => $vehicle->fill($data)->save());

        session()->flash('success', __('app.saved'));
        $this->redirectRoute('vehicles.show', $vehicle, navigate: true);
    }

    public function delete(int $id): void
    {
        $vehicle = Vehicle::findOrFail($id);
        $this->authorize('delete', $vehicle);
        $vehicle->delete();
        session()->flash('success', __('app.deleted'));
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::findOrFail($this->recordId);
    }

    /**
     * @return list<string>
     */
    private function sortableColumns(): array
    {
        return ['registration_number', 'make', 'model', 'year', 'inspection_until', 'octa_until'];
    }

    public function render()
    {
        $this->authorize('viewAny', Vehicle::class);

        $sort = in_array($this->sort, $this->sortableColumns(), true)
            ? $this->sort
            : 'registration_number';
        $direction = in_array($this->direction, ['asc', 'desc'], true)
            ? $this->direction
            : 'asc';
        $perPage = in_array($this->perPage, [10, 15, 25], true)
            ? $this->perPage
            : 15;
        $this->sort = $sort;
        $this->direction = $direction;
        $this->perPage = $perPage;

        $records = $this->mode === 'index'
            ? Vehicle::query()
                ->when($this->search, fn ($query) => $query->where(function ($query): void {
                    $query->where('registration_number', 'like', '%'.$this->search.'%')
                        ->orWhere('make', 'like', '%'.$this->search.'%')
                        ->orWhere('model', 'like', '%'.$this->search.'%')
                        ->orWhere('vin', 'like', '%'.$this->search.'%');
                }))
                ->orderBy($sort, $direction)
                ->orderBy('id')
                ->paginate($perPage)
            : collect();

        return view('livewire.admin-ui', [
            'records' => $records,
            'record' => $this->recordId ? $this->vehicle() : null,
        ]);
    }
}
