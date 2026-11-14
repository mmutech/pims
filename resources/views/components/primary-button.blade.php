<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-navy-900 dark:bg-navy-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-navy-800 dark:hover:bg-navy-700 focus:bg-navy-800 dark:focus:bg-navy-700 active:bg-navy-950 dark:active:bg-navy-900 focus:outline-none focus:ring-2 focus:ring-navy-600 dark:focus:ring-navy-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
