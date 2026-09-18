<div
    class="reg-panel"
    x-data="{
        toast: @entangle('showCompletedToast'),
        upcomingOpen: false,
        historyOpen: false,
    }"
>
    @if ($canManage)
        <section class="reg-add-section rounded-2xl border-2 border-blue-300 bg-blue-50 p-4 shadow-md sm:p-5">
            <h3 class="text-sm font-bold uppercase tracking-wide text-blue-900">{{ __('app.regulations.add_title') }}</h3>
            <form wire:submit="addRegulation" class="reg-add-form">
                <label class="min-w-0">
                    <span class="sr-only">{{ __('app.fields.next_regulation') }}</span>
                    <input wire:model="newRegulationType" type="text" maxlength="120" autocomplete="off" placeholder="{{ __('app.regulations.add_type_placeholder') }}" title="{{ __('app.fields.next_regulation') }}">
                </label>
                <label class="min-w-0">
                    <span class="sr-only">{{ __('app.fields.next_regulation_odometer') }}</span>
                    <input wire:model="newDueOdometer" type="number" min="0" step="1" inputmode="numeric" placeholder="{{ __('app.regulations.add_odometer_placeholder') }}" title="{{ __('app.fields.next_regulation_odometer') }}">
                </label>
                <button type="submit" class="reg-add-btn" aria-label="{{ __('app.regulations.add') }}">
                    <span class="sm:hidden">+</span>
                    <span class="hidden sm:inline">{{ __('app.regulations.add') }}</span>
                </button>
                @if ($errors->has('newRegulationType') || $errors->has('newDueOdometer'))
                    <div class="reg-add-errors">
                        @error('newRegulationType')<p>{{ $message }}</p>@enderror
                        @error('newDueOdometer')<p>{{ $message }}</p>@enderror
                    </div>
                @endif
            </form>
        </section>
    @endif

    {{-- Планируемые работы --}}
    <section class="overflow-hidden rounded-2xl border-2 border-blue-400 bg-white shadow-lg">
        <button
            type="button"
            class="reg-acc-head reg-acc-head-upcoming"
            @click="upcomingOpen = ! upcomingOpen"
            :aria-expanded="upcomingOpen"
        >
            <span class="reg-acc-icon" aria-hidden="true">⏱</span>
            <span class="reg-acc-title">{{ __('app.regulations.upcoming_title') }}</span>
            <span class="reg-acc-count">{{ $upcoming->total() }}</span>
            <span class="reg-acc-toggle" :class="upcomingOpen ? 'is-open' : ''" aria-hidden="true">⌄</span>
        </button>

        <div
            x-show="upcomingOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="border-t-2 border-blue-200 bg-blue-50/40"
        >
            <div class="space-y-4 p-4 sm:p-5">
                <div class="reg-toolbar reg-toolbar-upcoming">
                    <label class="flex shrink-0 items-center gap-1.5">
                        <span class="whitespace-nowrap text-xs font-bold uppercase tracking-wide text-blue-800">{{ __('app.sort') }}</span>
                        <select wire:change="setUpcomingSort($event.target.value)" class="!min-h-9 !w-auto min-w-[9.5rem] rounded-lg border-blue-300 bg-blue-50 text-sm font-semibold text-blue-900 focus:border-blue-600 focus:ring-blue-600">
                            @foreach ([
                                'due_odometer:asc' => __('app.regulations.sort.due_asc'),
                                'due_odometer:desc' => __('app.regulations.sort.due_desc'),
                                'regulation_type:asc' => __('app.regulations.sort.type_asc'),
                                'created_at:desc' => __('app.regulations.sort.newest'),
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected($upcomingSort.':'.$upcomingDirection === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex min-w-[8rem] flex-1 items-center gap-1.5">
                        <span class="sr-only">{{ __('app.search') }}</span>
                        <input wire:model.live.debounce.300ms="upcomingSearch" type="search" placeholder="{{ __('app.search') }}" class="!min-h-9 min-w-[7rem] flex-1 rounded-lg border-blue-300 bg-blue-50 text-sm font-medium text-blue-900 placeholder:text-blue-400 focus:border-blue-600 focus:ring-blue-600">
                    </label>
                    <label class="flex shrink-0 items-center gap-1.5">
                        <span class="whitespace-nowrap text-xs font-bold uppercase tracking-wide text-blue-800">{{ __('app.per_page') }}</span>
                        <select wire:model.live="upcomingPerPage" class="!min-h-9 !w-auto w-16 rounded-lg border-blue-300 bg-blue-50 text-sm font-bold text-blue-900 focus:border-blue-600 focus:ring-blue-600">
                            @foreach ([5, 10, 15] as $size)
                                <option value="{{ $size }}">{{ $size }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($upcoming->hasPages())
                        <div class="ml-auto shrink-0 [&_.pagination]:!m-0 [&_nav]:flex [&_nav]:flex-nowrap [&_nav]:items-center [&_nav]:gap-1 [&_button]:!min-h-9 [&_button]:!rounded-lg [&_button]:!px-2.5 [&_button]:!text-xs [&_button]:!font-bold">
                            {{ $upcoming->links() }}
                        </div>
                    @endif
                </div>

                <section class="hidden overflow-hidden rounded-xl border-2 border-blue-300 bg-white shadow-sm md:block">
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr class="bg-blue-100">
                                    @if ($canManage)
                                        <th class="w-16 !border-blue-200 !bg-blue-100 !text-blue-900">{{ __('app.regulations.done') }}</th>
                                    @endif
                                    @foreach ([
                                        'regulation_type' => __('app.fields.next_regulation'),
                                        'due_odometer' => __('app.fields.next_regulation_odometer'),
                                        'created_at' => __('app.regulations.planned_at'),
                                    ] as $column => $label)
                                        <th class="!border-blue-200 !bg-blue-100 !text-blue-900">
                                            <button type="button" wire:click="sortUpcoming('{{ $column }}')" class="inline-flex min-h-11 items-center gap-1 text-left font-bold hover:text-blue-700">
                                                <span>{{ $label }}</span>
                                                <span aria-hidden="true">{{ $upcomingSort === $column ? ($upcomingDirection === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                            </button>
                                        </th>
                                    @endforeach
                                    @if ($canManage)
                                        <th class="!border-blue-200 !bg-blue-100 !text-right !text-blue-900">{{ __('app.actions') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($upcoming as $regulation)
                                    <tr wire:key="upcoming-row-{{ $regulation->id }}" class="even:bg-blue-50/60">
                                        @if ($canManage)
                                            <td>
                                                <button type="button" wire:click="requestComplete({{ $regulation->id }})" class="grid h-11 w-11 place-items-center rounded-xl border-2 border-emerald-400 bg-white text-xl font-bold text-emerald-600 shadow-sm transition hover:border-emerald-500 hover:bg-emerald-50 active:scale-95" aria-label="{{ __('app.regulations.mark_done') }}">☐</button>
                                            </td>
                                        @endif
                                        <td class="!font-bold !text-blue-950">{{ $regulation->regulationTypeLabel() }}</td>
                                        <td class="!font-semibold !text-slate-800">{{ number_format((float) $regulation->due_odometer, 0, '.', ' ') }} km</td>
                                        <td class="!text-slate-700">{{ $regulation->created_at?->format('d.m.Y') ?? '—' }}</td>
                                        @if ($canManage)
                                            <td class="text-right">
                                                <button type="button" wire:click="deletePlanned({{ $regulation->id }})" wire:confirm="{{ __('app.confirm_delete') }}" class="action-link !font-bold !text-red-600 hover:!bg-red-50">{{ __('app.delete') }}</button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canManage ? 5 : 3 }}"><div class="empty-state !border-blue-300 !bg-blue-50 !text-blue-800">{{ __('app.regulations.no_upcoming') }}</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="grid gap-3 md:hidden">
                    @forelse ($upcoming as $regulation)
                        <article wire:key="upcoming-card-{{ $regulation->id }}" class="rounded-xl border-2 border-blue-300 bg-white p-4 shadow-sm">
                            <div class="flex items-start gap-3">
                                @if ($canManage)
                                    <button type="button" wire:click="requestComplete({{ $regulation->id }})" class="mt-0.5 grid h-12 w-12 shrink-0 place-items-center rounded-xl border-2 border-emerald-400 bg-emerald-50 text-2xl font-bold text-emerald-600 shadow-sm transition active:scale-95 hover:border-emerald-500 hover:bg-emerald-100" aria-label="{{ __('app.regulations.mark_done') }}">☐</button>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-base font-extrabold text-blue-950">{{ $regulation->regulationTypeLabel() }}</h4>
                                    <p class="mt-1 text-sm font-medium text-slate-700">{{ __('app.fields.next_regulation_odometer') }}: <span class="font-extrabold text-blue-900">{{ number_format((float) $regulation->due_odometer, 0, '.', ' ') }} km</span></p>
                                    <p class="mt-1 text-xs font-semibold text-blue-700">{{ __('app.regulations.planned_at') }}: {{ $regulation->created_at?->format('d.m.Y') ?? '—' }}</p>
                                </div>
                                @if ($canManage)
                                    <button type="button" wire:click="deletePlanned({{ $regulation->id }})" wire:confirm="{{ __('app.confirm_delete') }}" class="rounded-lg px-2 py-1 text-xs font-bold text-red-600 hover:bg-red-50">{{ __('app.delete') }}</button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="empty-state !border-blue-300 !bg-blue-50 !text-blue-800">{{ __('app.regulations.no_upcoming') }}</div>
                    @endforelse
                </section>

                @if ($upcoming->hasPages())
                    <div class="rounded-xl border-2 border-blue-200 bg-white p-3 md:hidden">
                        {{ $upcoming->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Выполненные работы --}}
    <section class="overflow-hidden rounded-2xl border-2 border-emerald-400 bg-white shadow-lg">
        <button
            type="button"
            class="reg-acc-head reg-acc-head-history"
            @click="historyOpen = ! historyOpen"
            :aria-expanded="historyOpen"
        >
            <span class="reg-acc-icon" aria-hidden="true">✓</span>
            <span class="reg-acc-title">{{ __('app.regulations.history_title') }}</span>
            <span class="reg-acc-count">{{ $history->total() }}</span>
            <span class="reg-acc-toggle" :class="historyOpen ? 'is-open' : ''" aria-hidden="true">⌄</span>
        </button>

        <div
            x-show="historyOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="border-t-2 border-emerald-200 bg-emerald-50/40"
        >
            <div class="space-y-4 p-4 sm:p-5">
                <div class="reg-toolbar reg-toolbar-history">
                    <label class="flex shrink-0 items-center gap-1.5">
                        <span class="whitespace-nowrap text-xs font-bold uppercase tracking-wide text-emerald-800">{{ __('app.sort') }}</span>
                        <select wire:change="setHistorySort($event.target.value)" class="!min-h-9 !w-auto min-w-[9.5rem] rounded-lg border-emerald-300 bg-emerald-50 text-sm font-semibold text-emerald-900 focus:border-emerald-600 focus:ring-emerald-600">
                            @foreach ([
                                'completed_at:desc' => __('app.regulations.sort.completed_desc'),
                                'completed_at:asc' => __('app.regulations.sort.completed_asc'),
                                'regulation_type:asc' => __('app.regulations.sort.type_asc'),
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected($historySort.':'.$historyDirection === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex min-w-[8rem] flex-1 items-center gap-1.5">
                        <span class="sr-only">{{ __('app.search') }}</span>
                        <input wire:model.live.debounce.300ms="historySearch" type="search" placeholder="{{ __('app.search') }}" class="!min-h-9 min-w-[7rem] flex-1 rounded-lg border-emerald-300 bg-emerald-50 text-sm font-medium text-emerald-900 placeholder:text-emerald-400 focus:border-emerald-600 focus:ring-emerald-600">
                    </label>
                    <label class="flex shrink-0 items-center gap-1.5">
                        <span class="whitespace-nowrap text-xs font-bold uppercase tracking-wide text-emerald-800">{{ __('app.per_page') }}</span>
                        <select wire:model.live="historyPerPage" class="!min-h-9 !w-auto w-16 rounded-lg border-emerald-300 bg-emerald-50 text-sm font-bold text-emerald-900 focus:border-emerald-600 focus:ring-emerald-600">
                            @foreach ([5, 10, 15] as $size)
                                <option value="{{ $size }}">{{ $size }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($history->hasPages())
                        <div class="ml-auto shrink-0 [&_.pagination]:!m-0 [&_nav]:flex [&_nav]:flex-nowrap [&_nav]:items-center [&_nav]:gap-1 [&_button]:!min-h-9 [&_button]:!rounded-lg [&_button]:!px-2.5 [&_button]:!text-xs [&_button]:!font-bold">
                            {{ $history->links() }}
                        </div>
                    @endif
                </div>

                <section class="hidden overflow-hidden rounded-xl border-2 border-emerald-300 bg-white shadow-sm md:block">
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr class="bg-emerald-100">
                                    @foreach ([
                                        'regulation_type' => __('app.regulations.history_title'),
                                        'due_odometer' => __('app.fields.next_regulation_odometer'),
                                        'completed_at' => __('app.regulations.completed_at'),
                                    ] as $column => $label)
                                        <th class="!border-emerald-200 !bg-emerald-100 !text-emerald-900">
                                            <button type="button" wire:click="sortHistory('{{ $column }}')" class="inline-flex min-h-11 items-center gap-1 text-left font-bold hover:text-emerald-700">
                                                <span>{{ $label }}</span>
                                                <span aria-hidden="true">{{ $historySort === $column ? ($historyDirection === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                            </button>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($history as $regulation)
                                    <tr wire:key="history-row-{{ $regulation->id }}" class="even:bg-emerald-50/60">
                                        <td class="!font-bold !text-emerald-950">{{ $regulation->regulationTypeLabel() }}</td>
                                        <td class="!font-semibold !text-slate-800">{{ number_format((float) $regulation->due_odometer, 0, '.', ' ') }} km</td>
                                        <td class="!font-semibold !text-emerald-800">{{ $regulation->completed_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3"><div class="empty-state !border-emerald-300 !bg-emerald-50 !text-emerald-800">{{ __('app.regulations.no_history') }}</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="grid gap-3 md:hidden">
                    @forelse ($history as $regulation)
                        <article wire:key="history-card-{{ $regulation->id }}" class="rounded-xl border-2 border-emerald-300 bg-white p-4 shadow-sm">
                            <div class="flex items-start gap-3">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border-2 border-emerald-400 bg-emerald-100 text-lg font-bold text-emerald-700 shadow-sm">✓</span>
                                <div class="min-w-0">
                                    <h4 class="font-extrabold text-emerald-950">{{ $regulation->regulationTypeLabel() }}</h4>
                                    <p class="mt-1 text-sm font-semibold text-slate-700">{{ number_format((float) $regulation->due_odometer, 0, '.', ' ') }} km</p>
                                    <p class="mt-1 text-xs font-bold text-emerald-700">{{ __('app.regulations.completed_at') }}: {{ $regulation->completed_at?->format('d.m.Y H:i') ?? '—' }}</p>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state !border-emerald-300 !bg-emerald-50 !text-emerald-800">{{ __('app.regulations.no_history') }}</div>
                    @endforelse
                </section>

                @if ($history->hasPages())
                    <div class="rounded-xl border-2 border-emerald-200 bg-white p-3 md:hidden">
                        {{ $history->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($pendingRegulation)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/45 p-4 sm:items-center" wire:keydown.escape="cancelComplete">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-100 text-2xl text-emerald-700">✓</div>
                <h3 class="mt-4 text-center text-xl font-bold text-slate-900">{{ __('app.regulations.confirm_title') }}</h3>
                <p class="mt-2 text-center text-sm text-slate-600">{{ __('app.regulations.confirm_message') }}</p>
                <div class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm">
                    <p class="font-semibold text-slate-900">{{ $pendingRegulation->regulationTypeLabel() }}</p>
                    <p class="mt-1 text-slate-600">{{ number_format((float) $pendingRegulation->due_odometer, 0, '.', ' ') }} km</p>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" wire:click="cancelComplete" class="btn-secondary min-h-11 justify-center">{{ __('app.cancel') }}</button>
                    <button type="button" wire:click="confirmComplete" class="btn-primary min-h-11 justify-center !bg-emerald-600 hover:!bg-emerald-700">{{ __('app.regulations.confirm_yes') }}</button>
                </div>
            </div>
        </div>
    @endif

    <div
        x-cloak
        x-show="toast"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-x-4 bottom-4 z-50 mx-auto flex max-w-md items-center gap-3 rounded-2xl border border-emerald-200 bg-white p-4 shadow-xl sm:inset-x-auto sm:right-6 sm:bottom-6"
        role="status"
    >
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-100 text-xl text-emerald-700">✓</span>
        <div class="min-w-0 flex-1">
            <p class="font-bold text-slate-900">{{ __('app.regulations.completed_toast_title') }}</p>
            <p class="text-sm text-slate-600">{{ __('app.regulations.completed_toast_message') }}</p>
        </div>
        <button type="button" wire:click="dismissToast" @click="toast = false" class="rounded-lg px-2 py-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="{{ __('app.close') }}">×</button>
    </div>
</div>
