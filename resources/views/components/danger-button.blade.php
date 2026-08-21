<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-11 items-center justify-center rounded-xl border border-transparent bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
