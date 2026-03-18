<?php

namespace App\Http\Requests;

use App\Validators\MemberValidator;
use Illuminate\Foundation\Http\FormRequest;

class CreateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add auth logic here if needed
    }

    public function rules(): array
    {
        return MemberValidator::rules();
    }

    public function messages(): array
    {
        return MemberValidator::messages();
    }
}
