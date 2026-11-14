<div class="space-y-6">
    <x-page-header title="Settings" description="System-wide configuration. Changes are audited.">
        <x-slot name="actions">
            @can('update', new App\Domain\Administration\Models\SystemSetting)
                <flux:button variant="primary" icon="check" wire:click="save">Save settings</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        @forelse ($groups as $group => $settings)
            <flux:card>
                <flux:heading>{{ str($group)->headline() }}</flux:heading>
                <flux:subheading>{{ $settings->count() }} setting{{ $settings->count() === 1 ? '' : 's' }} in this group.</flux:subheading>

                <div class="mt-5 grid gap-5 lg:grid-cols-2">
                    @foreach ($settings as $setting)
                        <div class="min-w-0 {{ $setting->type === 'text' ? 'lg:col-span-2' : '' }}">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ str($setting->key)->headline() }}</span>
                                @if ($setting->is_encrypted)
                                    <flux:badge size="sm" color="orange">Encrypted</flux:badge>
                                @endif
                                <span class="min-w-0 truncate font-mono text-[11px] text-zinc-400 dark:text-zinc-500">{{ $setting->key }}</span>
                            </div>

                            @if ($setting->type === 'boolean')
                                <flux:switch
                                    wire:model="values.{{ $setting->id }}"
                                    label="{{ $setting->description ?: 'Enabled' }}"
                                    :disabled="! auth()->user()->can('update', $setting)"
                                />
                            @elseif ($setting->type === 'text')
                                <flux:textarea
                                    wire:model="values.{{ $setting->id }}"
                                    rows="3"
                                    :disabled="! auth()->user()->can('update', $setting)"
                                />
                            @elseif (isset($selectOptions[$setting->key]))
                                <flux:select
                                    wire:model="values.{{ $setting->id }}"
                                    :disabled="! auth()->user()->can('update', $setting)"
                                >
                                    @foreach ($selectOptions[$setting->key] as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:input
                                    wire:model="values.{{ $setting->id }}"
                                    type="{{ $setting->type === 'integer' ? 'number' : ($setting->is_encrypted ? 'password' : 'text') }}"
                                    :placeholder="$setting->is_encrypted ? 'Leave blank to keep the current value' : null"
                                    :disabled="! auth()->user()->can('update', $setting)"
                                />
                            @endif

                            @if ($setting->description && $setting->type !== 'boolean')
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $setting->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @empty
            <flux:card>
                <p class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">No settings have been configured.</p>
            </flux:card>
        @endforelse

        @can('update', new App\Domain\Administration\Models\SystemSetting)
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Save settings</flux:button>
            </div>
        @endcan
    </form>
</div>
