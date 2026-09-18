<?php

namespace App\Livewire;

use App\Actions\SendUserInvitation;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UsersUi extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $mode = 'index';

    public ?int $recordId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sort = 'first_name';

    #[Url]
    public string $direction = 'asc';

    #[Url]
    public int $perPage = 15;

    public array $form = [];

    public function mount(string $mode = 'index', ?int $recordId = null): void
    {
        $this->mode = $mode;
        $this->recordId = $recordId;
        $this->form = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'role' => UserRole::Manager->value,
        ];

        if ($mode === 'create') {
            $this->authorize('create', User::class);
        }

        if ($recordId !== null) {
            $user = $this->user();

            if ($mode === 'edit' && auth()->id() === $user->id) {
                $this->redirectRoute('profile', navigate: true);

                return;
            }

            $this->authorize($mode === 'edit' ? 'manage' : 'view', $user);
            $this->form = [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'role' => $user->role->value,
            ];
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
        $user = $this->recordId ? $this->user() : new User;
        if ($this->recordId) {
            $this->authorize('manage', $user);
        } else {
            $this->authorize('create', User::class);
        }

        $validated = $this->validate([
            'form.first_name' => ['required', 'string', 'max:80'],
            'form.last_name' => ['required', 'string', 'max:80'],
            'form.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->recordId)],
            'form.phone' => ['nullable', 'string', 'max:16'],
            'form.role' => ['required', Rule::in([UserRole::Admin->value, UserRole::Manager->value])],
        ])['form'];

        if ($this->recordId && $user->role === UserRole::Admin && $validated['role'] !== UserRole::Admin->value) {
            $hasOtherAdmins = User::query()
                ->where('role', UserRole::Admin)
                ->whereKeyNot($user->id)
                ->exists();

            if (! $hasOtherAdmins) {
                $this->addError('form.role', __('app.cannot_demote_last_admin'));

                return;
            }
        }

        $data = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] === '' ? null : $validated['phone'],
            'role' => $validated['role'],
        ];

        $isNew = ! $this->recordId;

        if ($isNew) {
            $data['password'] = Str::password(40);
            $data['email_verified_at'] = null;
        }

        DB::transaction(fn () => $user->fill($data)->save());

        if ($isNew) {
            app(SendUserInvitation::class)($user);
            session()->flash('success', __('app.user_invitation_sent'));
        } else {
            session()->flash('success', __('app.saved'));
        }

        $this->redirectRoute('users.show', $user, navigate: true);
    }

    public function resendInvitation(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('invite', $user);

        app(SendUserInvitation::class)($user);

        session()->flash('success', __('app.invitation_resent'));
    }

    public function delete(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);
        $user->delete();
        session()->flash('success', __('app.deleted'));
    }

    private function user(): User
    {
        return User::findOrFail($this->recordId);
    }

    /**
     * @return list<string>
     */
    private function sortableColumns(): array
    {
        return ['first_name', 'last_name', 'email', 'role', 'created_at'];
    }

    public function render()
    {
        $this->authorize('viewAny', User::class);

        $sort = in_array($this->sort, $this->sortableColumns(), true)
            ? $this->sort
            : 'first_name';
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
            ? User::query()
                ->when($this->search, fn ($query) => $query->where(function ($query): void {
                    $query->where('first_name', 'like', '%'.$this->search.'%')
                        ->orWhere('last_name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('phone', 'like', '%'.$this->search.'%');
                }))
                ->orderBy($sort, $direction)
                ->when($sort === 'first_name', fn ($query) => $query->orderBy('last_name', $direction))
                ->orderBy('id')
                ->paginate($perPage)
            : collect();

        $record = in_array($this->mode, ['edit', 'show'], true) && $this->recordId
            ? $this->user()
            : null;

        return view('livewire.users-ui', [
            'records' => $records,
            'record' => $record,
        ]);
    }
}
