<?php


namespace App\Http\Requests\Image;


use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function rules()
    {
        return [
            'image_files' => 'sometimes',
            'image_files.*' => 'sometimes|mimetypes:image/jpeg,image/png|max:' . (config('filesystems.images.max_size') ?: 2048),
            'image_data' => 'sometimes'
        ];
    }
}
