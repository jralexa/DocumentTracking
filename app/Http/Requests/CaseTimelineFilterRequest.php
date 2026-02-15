<?php

namespace App\Http\Requests;

use App\DocumentEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaseTimelineFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['nullable', Rule::in(array_map(
                static fn (DocumentEventType $eventType): string => $eventType->value,
                DocumentEventType::cases()
            ))],
            'tracking_number' => ['nullable', 'string', 'max:50'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ];
    }
}
