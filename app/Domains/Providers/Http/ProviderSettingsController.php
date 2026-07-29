<?php

declare(strict_types=1);

namespace App\Domains\Providers\Http;

use App\Domains\Providers\Actions\UpdateProviderSetting;
use App\Domains\Providers\Enums\ProviderSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderSettingsController extends Controller
{
    public function toggle(Request $request, UpdateProviderSetting $action): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', Rule::enum(ProviderSetting::class)],
            'enabled' => ['required', 'boolean'],
        ]);

        $setting = ProviderSetting::from((string) $validated['key']);
        $enabled = (bool) $validated['enabled'];

        $action->handle($setting, $enabled);

        return back()->with(
            'success',
            $enabled ? "تم تفعيل: {$setting->label()}" : "تم إيقاف: {$setting->label()}",
        );
    }
}
