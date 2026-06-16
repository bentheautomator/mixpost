<?php

namespace Inovector\Mixpost\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportPosts extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a CSV file to import.',
            'file.mimes' => 'The file must be a CSV file.',
        ];
    }
}
