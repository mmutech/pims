<div class="space-y-6">
    <x-page-header :title="$supplier->name" :description="'Supplier '.$supplier->code">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('suppliers.index')" wire:navigate>All suppliers</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Supplier profile</flux:heading>
                <div class="flex items-center gap-2">
                    <flux:badge size="sm" :color="$supplier->is_approved ? 'green' : 'amber'">
                        {{ $supplier->is_approved ? 'Approved' : 'Not approved' }}
                    </flux:badge>
                    <flux:badge size="sm" :color="match ($supplier->status) {
                        App\Enums\SupplierStatus::Active => 'green',
                        App\Enums\SupplierStatus::Pending => 'amber',
                        App\Enums\SupplierStatus::Suspended => 'orange',
                        App\Enums\SupplierStatus::Blacklisted => 'red',
                        App\Enums\SupplierStatus::Inactive => 'zinc',
                    }">
                        {{ $supplier->status->label() }}
                    </flux:badge>
                </div>
            </div>

            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Legal name</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $supplier->legal_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">TIN</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $supplier->tax_identification_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Email</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $supplier->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Phone</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $supplier->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">City / State</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ collect([$supplier->city, $supplier->state])->filter()->implode(', ') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Rating</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $supplier->rating !== null ? number_format((float) $supplier->rating, 2).' / 5' : 'Not rated' }}</dd>
                </div>
            </dl>

            @if ($supplier->address)
                <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-700">
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Address</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $supplier->address }}</dd>
                </div>
            @endif

            @if ($supplier->notes)
                <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-700">
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Notes</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-zinc-800 dark:text-zinc-100">{{ $supplier->notes }}</dd>
                </div>
            @endif
        </flux:card>

        <flux:card>
            <flux:heading>Approval</flux:heading>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Approved by</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $supplier->approvedBy?->name ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Approved at</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $supplier->approved_at?->format('d M Y') ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Can receive awards</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $supplier->canReceiveAwards() ? 'Yes' : 'No' }}</span>
                </div>
            </div>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading>Contacts</flux:heading>

            @can('update', $supplier)
                <form wire:submit="addContact" class="mt-4 space-y-2">
                    <div class="flex flex-wrap items-end gap-2">
                        <flux:input wire:model="contactForm.name" label="Name" class="min-w-32 flex-1" required />
                        <flux:input wire:model="contactForm.position" label="Position" class="min-w-32 flex-1" />
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <flux:input wire:model="contactForm.email" type="email" label="Email" class="min-w-32 flex-1" />
                        <flux:input wire:model="contactForm.phone" label="Phone" class="min-w-32 flex-1" />
                        <flux:switch wire:model="contactForm.is_primary" label="Primary" />
                        <flux:button type="submit" variant="primary">Add</flux:button>
                    </div>
                </form>
            @endcan

            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse ($contacts as $contact)
                    <div class="flex items-center justify-between gap-3 py-2">
                        <div>
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                {{ $contact->name }}
                                @if ($contact->is_primary)
                                    <flux:badge size="sm" color="blue" class="ms-1">Primary</flux:badge>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ collect([$contact->position, $contact->email, $contact->phone])->filter()->implode(' · ') ?: 'No details' }}
                            </div>
                        </div>
                        @can('update', $supplier)
                            <flux:button size="sm" variant="ghost" icon="x-mark" aria-label="Remove contact" wire:click="removeContact({{ $contact->id }})" wire:confirm="Remove this contact?" />
                        @endcan
                    </div>
                @empty
                    <p class="py-4 text-sm text-zinc-500 dark:text-zinc-400">No contacts recorded.</p>
                @endforelse
            </div>
        </flux:card>

        <flux:card>
            <flux:heading>Addresses</flux:heading>

            @can('update', $supplier)
                <form wire:submit="addAddress" class="mt-4 space-y-2">
                    <div class="flex flex-wrap items-end gap-2">
                        <flux:select wire:model="addressForm.type" label="Type" class="w-40">
                            @foreach ($addressTypeOptions as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="addressForm.line1" label="Line 1" class="min-w-40 flex-1" required />
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <flux:input wire:model="addressForm.city" label="City" class="min-w-28 flex-1" />
                        <flux:input wire:model="addressForm.state" label="State" class="min-w-28 flex-1" />
                        <flux:switch wire:model="addressForm.is_primary" label="Primary" />
                        <flux:button type="submit" variant="primary">Add</flux:button>
                    </div>
                </form>
            @endcan

            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse ($addresses as $address)
                    <div class="flex items-center justify-between gap-3 py-2">
                        <div>
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                {{ $address->type->label() }}
                                @if ($address->is_primary)
                                    <flux:badge size="sm" color="blue" class="ms-1">Primary</flux:badge>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ collect([$address->line1, $address->line2, $address->city, $address->state])->filter()->implode(', ') }}
                            </div>
                        </div>
                        @can('update', $supplier)
                            <flux:button size="sm" variant="ghost" icon="x-mark" aria-label="Remove address" wire:click="removeAddress({{ $address->id }})" wire:confirm="Remove this address?" />
                        @endcan
                    </div>
                @empty
                    <p class="py-4 text-sm text-zinc-500 dark:text-zinc-400">No addresses recorded.</p>
                @endforelse
            </div>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading>Documents</flux:heading>

            @can('update', $supplier)
                <form wire:submit="addDocument" class="mt-4 space-y-2">
                    <div class="flex flex-wrap items-end gap-2">
                        <flux:select wire:model="documentForm.document_type" label="Type" class="min-w-40 flex-1">
                            <option value="">Select a type</option>
                            @foreach ($documentTypes as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="documentForm.document_number" label="Number" class="min-w-32 flex-1" />
                        <flux:input wire:model="documentForm.expires_at" type="date" label="Expires" class="w-40" />
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <flux:input wire:model="documentForm.file_path" label="File path" placeholder="storage/suppliers/..." class="min-w-48 flex-1" required />
                        <flux:button type="submit" variant="primary">Record</flux:button>
                    </div>
                </form>
            @endcan

            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse ($documents as $document)
                    <div class="flex items-center justify-between gap-3 py-2">
                        <div>
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $document->document_type }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $document->document_number ?? 'No number' }}
                                @if ($document->expires_at)
                                    · expires {{ $document->expires_at->format('d M Y') }}
                                    @if ($document->isExpired())
                                        <span class="text-red-600 dark:text-red-400">(expired)</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                        @can('update', $supplier)
                            <flux:button size="sm" variant="ghost" icon="x-mark" aria-label="Remove document" wire:click="removeDocument({{ $document->id }})" wire:confirm="Remove this document record?" />
                        @endcan
                    </div>
                @empty
                    <p class="py-4 text-sm text-zinc-500 dark:text-zinc-400">No documents recorded.</p>
                @endforelse
            </div>
        </flux:card>

        <flux:card>
            <flux:heading>Evaluations</flux:heading>
            <flux:subheading>Scores are recorded on a 0 to 5 scale.</flux:subheading>

            @can('update', $supplier)
                <form wire:submit="addEvaluation" class="mt-4 space-y-2">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <flux:input wire:model="evaluationForm.quality_score" label="Quality" />
                        <flux:input wire:model="evaluationForm.delivery_score" label="Delivery" />
                        <flux:input wire:model="evaluationForm.pricing_score" label="Pricing" />
                        <flux:input wire:model="evaluationForm.service_score" label="Service" />
                    </div>
                    <flux:textarea wire:model="evaluationForm.comments" label="Comments" rows="2" />
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">Record evaluation</flux:button>
                    </div>
                </form>
            @endcan

            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse ($evaluations as $evaluation)
                    <div class="py-2">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                Overall {{ number_format((float) $evaluation->overall_score, 2) }} / 5
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $evaluation->evaluation_date?->format('d M Y') }} · {{ $evaluation->evaluator?->name ?? 'System' }}
                            </div>
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            Quality {{ $evaluation->quality_score }} · Delivery {{ $evaluation->delivery_score }} · Pricing {{ $evaluation->pricing_score }} · Service {{ $evaluation->service_score }}
                        </div>
                        @if ($evaluation->comments)
                            <div class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">{{ $evaluation->comments }}</div>
                        @endif
                    </div>
                @empty
                    <p class="py-4 text-sm text-zinc-500 dark:text-zinc-400">No evaluations recorded.</p>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>
