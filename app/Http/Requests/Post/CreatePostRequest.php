<?php


namespace App\Http\Requests\Post;


use Illuminate\Foundation\Http\FormRequest;

class CreatePostRequest extends FormRequest
{
    public function rules()
    {
        return [
            'title' => 'required|min:2|max:60',
            'content' => 'required',
            'image_files' => 'max:' . (config('filesystems.images.max_count') ?: 5),
            'image_files.*' => 'mimetypes:image/jpeg,image/png|max:' . (config('filesystems.images.max_size') ?: 2048),
            'image_data' => 'sometimes',
            'gallery_id' => 'sometimes|int',
            'poster_image' => 'sometimes|int|gt:-1'
        ];
    }

    public function messages()
    {
        return [
            'image_files.*.mimetypes' => 'The files should be images of types jpeg or png.',
            'image_files.*.max' => "The files may not be greater than 2048MB",
            'image_files.max' => "Cannot upload more than 5 files",
        ];
    }
}
