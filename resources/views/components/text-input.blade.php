@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600']) }}>
