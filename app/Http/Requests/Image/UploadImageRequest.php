<?php


namespace App\Http\Requests\Image;


use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image_files' => 'sometimes',
            'image_files.*' => 'sometimes|mimetypes:' . implode(', ', config('const.images.mimetypes')) . '|max:' . config('const.images.max_filesize'),
            'image_data' => 'sometimes'
        ];
    }

    public function messages(): array
    {
        return [
            'image_files.*.mimetypes' => 'The files should be images of types: ' . implode(', ', array_keys(config('const.images.mimetypes'))) . '.',
            'image_files.*.max' => "The files may not be greater than " . config('const.images.max_filesize'). "MB.",
        ];
    }
}
