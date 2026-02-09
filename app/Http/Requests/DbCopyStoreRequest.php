<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DbCopyStoreRequest extends FormRequest
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
        $connections = array_keys(config('database.connections', []));

        return [
            'source.connection' => [
                'required',
                'string',
                Rule::in($connections),
            ],
            'source.database' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_]+$/',
            ],
            'destination.connection' => [
                'required',
                'string',
                Rule::in($connections),
            ],
            'destination.database' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_]+$/',
            ],
            'threads' => [
                'nullable',
                'integer',
                'min:1',
                'max:64',
            ],
            'recreateDestination' => [
                'nullable',
                'boolean',
            ],
            'callback_url' => [
                'required',
                'url',
            ],
        ];
    }
}
