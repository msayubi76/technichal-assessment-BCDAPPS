<?php

namespace App\Http\Requests;

use App\Enums\DriverStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // "on_trip" is set by accepting a ride, never by hand.
        return [
            'status' => ['required', Rule::in([DriverStatus::Available->value, DriverStatus::Offline->value])],
        ];
    }

    public function status(): DriverStatus
    {
        return DriverStatus::from($this->validated('status'));
    }
}
