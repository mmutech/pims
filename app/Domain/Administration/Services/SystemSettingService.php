<?php

namespace App\Domain\Administration\Services;

use App\Domain\Administration\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;

class SystemSettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = SystemSetting::where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        if ($setting->is_encrypted && filled($setting->value)) {
            return Crypt::decryptString($setting->value);
        }

        return $setting->value;
    }

    public function set(string $key, mixed $value, string $group = 'general', string $type = 'string', bool $encrypted = false, ?string $description = null): SystemSetting
    {
        $stored = $encrypted && filled($value)
            ? Crypt::encryptString((string) $value)
            : ($value === null ? null : (string) $value);

        return SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $stored,
                'type' => $type,
                'description' => $description,
                'is_encrypted' => $encrypted,
            ],
        );
    }
}
