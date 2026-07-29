<?php

declare(strict_types=1);

namespace App\Domains\Providers\Http;

use Illuminate\Foundation\Http\FormRequest;

class StoreCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('providers.manage_credentials') === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'api_key' => ['required', 'string', 'min:8', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'اسم المفتاح مطلوب.',
            'api_key.required' => 'قيمة المفتاح مطلوبة.',
            'api_key.min' => 'المفتاح أقصر من المتوقع — تأكد من نسخه كاملًا.',
        ];
    }
}
