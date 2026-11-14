@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 focus:border-navy-600 dark:focus:border-navy-400 focus:ring-navy-600 dark:focus:ring-navy-400 rounded-md shadow-sm']) }}>
