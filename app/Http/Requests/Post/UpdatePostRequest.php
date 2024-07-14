<?php


namespace App\Http\Requests\Post;


use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function rules()
    {
        return [
            'title' => 'sometimes|required|min:2|max:60',
            'content' => 'sometimes|required',
            'image_files' => 'max:' . config('const.images.max_uploads'),
            'image_files.*' => 'sometimes|mimetypes:' . implode(', ', config('const.images.mimetypes')) . '|max:' . config('const.images.max_filesize'),
            'image_data' => 'sometimes',
            'gallery_id' => 'sometimes|int',
            'poster_image' => 'sometimes|int|gt:-1'
        ];
    }

    public function messages()
    {
        return [
            'image_files.*.mimetypes' => 'The files should be images of types: ' . implode(', ', array_keys(config('const.images.mimetypes'))) . '.',
            'image_files.*.max' => "The files may not be greater than " . config('const.images.max_filesize') . "MB.",
            'image_files.max' => "Too many images uploaded. Only " . config('const.images.max_uploads') . " images allowed.",
        ];
    }
}
