<?php

namespace App\Livewire;

use App\Enums\VehicleRegulationStatus;
use App\Models\Vehicle;
use App\Models\VehicleRegulation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class VehicleRegulationsPanel extends Component
{
    use AuthorizesRequests, WithPagination;

    public Vehicle $vehicle;

    public bool $canManage = true;

    public string $newRegulationType = '';

    public string $newDueOdometer = '';

    #[Url(as: 'rq')]
    public string $upcomingSearch = '';

    #[Url(as: 'us')]
    public string $upcomingSort = 'due_odometer';

    #[Url(as: 'ud')]
    public string $upcomingDirection = 'asc';

    #[Url(as: 'up')]
    public int $upcomingPerPage = 10;

    #[Url(as: 'hq')]
    public string $historySearch = '';

    #[Url(as: 'hs')]
    public string $historySort = 'completed_at';

    #[Url(as: 'hd')]
    public string $historyDirection = 'desc';

    #[Url(as: 'hp')]
    public int $historyPerPage = 10;

    public ?int $pendingCompleteId = null;

    public bool $showCompletedToast = false;

    public function mount(Vehicle $vehicle, bool $canManage = true): void
    {
        $this->vehicle = $vehicle;
        $this->canManage = $canManage;
        $this->authorize('view', $vehicle);
    }

    public function updatingUpcomingSearch(): void
    {
        $this->resetPage('upcomingPage');
    }

    public function updatingHistorySearch(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatingUpcomingPerPage(): void
    {
        $this->resetPage('upcomingPage');
    }

    public function updatingHistoryPerPage(): void
    {
        $this->resetPage('historyPage');
    }

    public function sortUpcoming(string $column): void
    {
        if (! in_array($column, $this->upcomingSortableColumns(), true)) {
            return;
        }

        if ($this->upcomingSort === $column) {
            $this->upcomingDirection = $this->upcomingDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->upcomingSort = $column;
            $this->upcomingDirection = 'asc';
        }

        $this->resetPage('upcomingPage');
    }

    public function sortHistory(string $column): void
    {
        if (! in_array($column, $this->historySortableColumns(), true)) {
            return;
        }

        if ($this->historySort === $column) {
            $this->historyDirection = $this->historyDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->historySort = $column;
            $this->historyDirection = 'desc';
        }

        $this->resetPage('historyPage');
    }

    public function setUpcomingSort(string $value): void
    {
        [$column, $direction] = array_pad(explode(':', $value, 2), 2, null);

        if (! in_array($column, $this->upcomingSortableColumns(), true) || ! in_array($direction, ['asc', 'desc'], true)) {
            return;
        }

        $this->upcomingSort = $column;
        $this->upcomingDirection = $direction;
        $this->resetPage('upcomingPage');
    }

    public function setHistorySort(string $value): void
    {
        [$column, $direction] = array_pad(explode(':', $value, 2), 2, null);

        if (! in_array($column, $this->historySortableColumns(), true) || ! in_array($direction, ['asc', 'desc'], true)) {
            return;
        }

        $this->historySort = $column;
        $this->historyDirection = $direction;
        $this->resetPage('historyPage');
    }

    public function addRegulation(): void
    {
        $this->authorize('update', $this->vehicle);

        $validated = $this->validate([
            'newRegulationType' => ['required', 'string', 'max:120'],
            'newDueOdometer' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        VehicleRegulation::query()->create([
            'vehicle_id' => $this->vehicle->id,
            'regulation_type' => trim($validated['newRegulationType']),
            'due_odometer' => $validated['newDueOdometer'],
            'status' => VehicleRegulationStatus::Planned,
        ]);

        $this->reset(['newRegulationType', 'newDueOdometer']);
        $this->resetPage('upcomingPage');
    }

    public function requestComplete(int $regulationId): void
    {
        $this->authorize('update', $this->vehicle);

        $regulation = $this->plannedRegulation($regulationId);
        abort_if($regulation === null, 404);

        $this->pendingCompleteId = $regulation->id;
    }

    public function confirmComplete(): void
    {
        $this->authorize('update', $this->vehicle);

        if ($this->pendingCompleteId === null) {
            return;
        }

        $regulation = $this->plannedRegulation($this->pendingCompleteId);
        abort_if($regulation === null, 404);

        $regulation->update([
            'status' => VehicleRegulationStatus::Completed,
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ]);

        $this->pendingCompleteId = null;
        $this->showCompletedToast = true;
        $this->resetPage('upcomingPage');
        $this->resetPage('historyPage');
    }

    public function cancelComplete(): void
    {
        $this->pendingCompleteId = null;
    }

    public function dismissToast(): void
    {
        $this->showCompletedToast = false;
    }

    public function deletePlanned(int $regulationId): void
    {
        $this->authorize('update', $this->vehicle);

        $regulation = $this->plannedRegulation($regulationId);
        abort_if($regulation === null, 404);

        $regulation->delete();
        $this->resetPage('upcomingPage');
    }

    private function plannedRegulation(int $regulationId): ?VehicleRegulation
    {
        return VehicleRegulation::query()
            ->where('vehicle_id', $this->vehicle->id)
            ->where('status', VehicleRegulationStatus::Planned)
            ->find($regulationId);
    }

    /**
     * @return list<string>
     */
    private function upcomingSortableColumns(): array
    {
        return ['regulation_type', 'due_odometer', 'created_at'];
    }

    /**
     * @return list<string>
     */
    private function historySortableColumns(): array
    {
        return ['regulation_type', 'due_odometer', 'completed_at'];
    }

    public function render()
    {
        $upcomingSort = in_array($this->upcomingSort, $this->upcomingSortableColumns(), true)
            ? $this->upcomingSort
            : 'due_odometer';
        $upcomingDirection = in_array($this->upcomingDirection, ['asc', 'desc'], true)
            ? $this->upcomingDirection
            : 'asc';
        $upcomingPerPage = in_array($this->upcomingPerPage, [5, 10, 15], true)
            ? $this->upcomingPerPage
            : 10;

        $historySort = in_array($this->historySort, $this->historySortableColumns(), true)
            ? $this->historySort
            : 'completed_at';
        $historyDirection = in_array($this->historyDirection, ['asc', 'desc'], true)
            ? $this->historyDirection
            : 'desc';
        $historyPerPage = in_array($this->historyPerPage, [5, 10, 15], true)
            ? $this->historyPerPage
            : 10;

        $this->upcomingSort = $upcomingSort;
        $this->upcomingDirection = $upcomingDirection;
        $this->upcomingPerPage = $upcomingPerPage;
        $this->historySort = $historySort;
        $this->historyDirection = $historyDirection;
        $this->historyPerPage = $historyPerPage;

        $upcoming = VehicleRegulation::query()
            ->where('vehicle_id', $this->vehicle->id)
            ->where('status', VehicleRegulationStatus::Planned)
            ->when($this->upcomingSearch, function ($query): void {
                $query->where(function ($query): void {
                    $term = '%'.$this->upcomingSearch.'%';
                    $query->where('regulation_type', 'like', $term)
                        ->orWhere('due_odometer', 'like', $term);
                });
            })
            ->orderBy($upcomingSort, $upcomingDirection)
            ->orderBy('id')
            ->paginate($upcomingPerPage, pageName: 'upcomingPage');

        $history = VehicleRegulation::query()
            ->with('completedByUser')
            ->where('vehicle_id', $this->vehicle->id)
            ->where('status', VehicleRegulationStatus::Completed)
            ->when($this->historySearch, function ($query): void {
                $query->where(function ($query): void {
                    $term = '%'.$this->historySearch.'%';
                    $query->where('regulation_type', 'like', $term)
                        ->orWhere('due_odometer', 'like', $term);
                });
            })
            ->orderBy($historySort, $historyDirection)
            ->orderByDesc('id')
            ->paginate($historyPerPage, pageName: 'historyPage');

        $pendingRegulation = $this->pendingCompleteId
            ? VehicleRegulation::query()
                ->where('vehicle_id', $this->vehicle->id)
                ->where('status', VehicleRegulationStatus::Planned)
                ->find($this->pendingCompleteId)
            : null;

        return view('livewire.vehicle-regulations-panel', [
            'upcoming' => $upcoming,
            'history' => $history,
            'pendingRegulation' => $pendingRegulation,
        ]);
    }
}
