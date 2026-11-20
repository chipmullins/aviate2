<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        $maxKb = (int) config('aviate.uploads.max_kilobytes');

        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without:image'],
            'image' => [
                'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:$maxKb",
                // Guards against "decompression bomb" images that are tiny on disk but huge in memory.
                'dimensions:max_width=6000,max_height=6000',
            ],
            // Username of the profile being posted on. Empty means your own profile/feed.
            'wall' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return ['body.required_without' => 'Write something or add a photo.'];
    }
}
