<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MemberValidator
{
    /**
     * Reusable validation rules shared by API requests and CSV rows.
     */
    public static function rules(): array
    {
        return [
            'firstName'        => ['required', 'string', 'max:100'],
            'lastName'         => ['required', 'string', 'max:100'],
            'dateOfBirth'      => ['required', 'date', 'date_format:Y-m-d'],
            'gender'           => ['required', 'in:M,F,Other'],
            'email'            => ['required', 'email', 'max:255'],
            'phone'            => ['required', 'regex:/^\+675\s?\d{7,8}$/'],
            'employerName'     => ['required', 'string', 'max:255'],
            'employmentStatus' => ['required', 'in:Active,Inactive,Casual,Part-time,Full-time'],
            'taxFileNumber'    => ['required', 'regex:/^\d{8}$/'],
        ];
    }

    public static function messages(): array
    {
        return [
            'phone.regex'         => 'Phone must be a valid PNG number (e.g. +675 71234567).',
            'taxFileNumber.regex' => 'Tax file number must be exactly 8 digits.',
            'dateOfBirth.date'    => 'Date of birth must be a valid date (YYYY-MM-DD).',
        ];
    }

    /**
     * Validate a raw data array (used by CSV rows and tests).
     *
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        $validator = Validator::make($data, self::rules(), self::messages());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Extra business rule: age must be 18 – 65
        $dob = \Carbon\Carbon::parse($data['dateOfBirth']);
        $age = $dob->age;

        if ($age < 18 || $age > 65) {
            throw new \InvalidArgumentException("Member age must be between 18 and 65 (got {$age}).");
        }

        return $validator->validated();
    }

    /**
     * Validate without throwing — returns ['valid' => bool, 'errors' => array]
     */
    public static function check(array $data): array
    {
        try {
            self::validate($data);
            return ['valid' => true, 'errors' => []];
        } catch (ValidationException $e) {
            return ['valid' => false, 'errors' => $e->errors()];
        } catch (\InvalidArgumentException $e) {
            return ['valid' => false, 'errors' => ['age' => [$e->getMessage()]]];
        }
    }
}
