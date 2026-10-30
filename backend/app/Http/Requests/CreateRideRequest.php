<?php

namespace App\Http\Requests;

use App\Enums\RideCategory;
use Illuminate\Validation\Rule;

class CreateRideRequest extends EstimateRideRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'category' => ['required', Rule::enum(RideCategory::class)],
        ];
    }

    public function category(): RideCategory
    {
        return RideCategory::from($this->validated('category'));
    }
}
