<?php


namespace App\Http\Requests\Gallery;


use Illuminate\Foundation\Http\FormRequest;

class UpdateGalleryRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'sometimes|required',
            'image_files' => 'sometimes',
            'image_files.*' => 'sometimes|mimetypes:image/jpeg,image/png|max:' . (config('filesystems.images.max_size') ?: 2048),
            'image_data' => 'sometimes'
        ];
    }
}
