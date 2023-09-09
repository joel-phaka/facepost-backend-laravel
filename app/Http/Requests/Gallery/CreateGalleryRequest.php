<?php


namespace App\Http\Requests\Gallery;


use Illuminate\Foundation\Http\FormRequest;

class CreateGalleryRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required',
            'image_files' => 'sometimes|max:' . (config('filesystems.images.max_count') ?: 5),
            'image_files.*' => 'sometimes|mimetypes:image/jpeg,image/png|max:' . (config('filesystems.images.max_size') ?: 2048),
            'image_data' => 'sometimes'
        ];
    }
}
