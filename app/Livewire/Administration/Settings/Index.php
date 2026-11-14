<?php

namespace App\Livewire\Administration\Settings;

use App\Domain\Administration\Models\SystemSetting;
use App\Domain\Administration\Services\SystemSettingService;
use App\Enums\ValuationMethod;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings')]
class Index extends Component
{
    use HandlesDomainActions;

    /** @var array<int, mixed> */
    public array $values = [];

    public function mount(): void
    {
        $this->authorize('viewAny', SystemSetting::class);
        $this->loadValues();
    }

    public function loadValues(): void
    {
        $this->values = [];

        foreach ($this->settings() as $setting) {
            if ($setting->is_encrypted) {
                // Never expose encrypted values to the browser; blank means "leave unchanged".
                $this->values[$setting->id] = '';

                continue;
            }

            $this->values[$setting->id] = match ($setting->type) {
                'boolean' => $this->toBoolean($setting->value),
                'integer' => $setting->value === null ? null : (int) $setting->value,
                default => $setting->value,
            };
        }
    }

    public function save(SystemSettingService $service): void
    {
        $this->authorize('update', new SystemSetting);

        $settings = $this->settings();

        $rules = [];

        foreach ($settings as $setting) {
            $rules['values.'.$setting->id] = match ($setting->type) {
                'boolean' => ['boolean'],
                'integer' => ['nullable', 'integer'],
                'decimal' => ['nullable', 'numeric'],
                default => ['nullable', 'string', 'max:2000'],
            };
        }

        $this->validate($rules);

        foreach ($settings as $setting) {
            if (! auth()->user()->can('update', $setting)) {
                continue;
            }

            $value = $this->values[$setting->id] ?? null;

            if ($setting->is_encrypted && blank($value)) {
                continue;
            }

            if ($setting->type === 'boolean') {
                $value = $this->toBoolean($value) ? '1' : '0';
            }

            $service->set(
                $setting->key,
                $value,
                $setting->group,
                $setting->type,
                $setting->is_encrypted,
                $setting->description,
            );
        }

        $this->loadValues();

        $this->success('Settings saved.');
    }

    public function render()
    {
        $settings = $this->settings();

        return view('livewire.administration.settings.index', [
            'groups' => $settings->groupBy('group'),
            'selectOptions' => [
                'inventory.default_valuation_method' => ValuationMethod::options(),
            ],
        ]);
    }

    /**
     * @return Collection<int, SystemSetting>
     */
    private function settings()
    {
        return SystemSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get();
    }

    private function toBoolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }
}
