<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DbCopyRunStoreRequest extends FormRequest
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
            'source_system_db_connection' => [
                'required',
                'string',
                Rule::in($connections),
            ],
            'source_system_db_name' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_]+$/',
            ],
            'source_admin_app_connection' => [
                'required',
                'string',
                Rule::in($connections),
            ],
            'source_admin_app_name' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_]+$/',
            ],
            'source_db_connection' => [
                'required',
                'string',
                Rule::in($connections),
            ],
            'dest_db_connections' => [
                'required',
                'array',
                'min:1',
            ],
            'dest_db_connections.*' => [
                'required',
                'string',
                Rule::in($connections),
                'distinct',
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
            'createDestDbOnLaravelCloud' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
