<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Automations;

use Illuminate\Foundation\Http\FormRequest;

class InspectFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'feed_url' => ['required', 'string', 'max:2048'],
        ];
    }
}
