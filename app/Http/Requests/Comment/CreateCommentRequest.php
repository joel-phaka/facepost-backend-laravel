<?php


namespace App\Http\Requests\Comment;


use Illuminate\Foundation\Http\FormRequest;

class CreateCommentRequest extends FormRequest
{
    public function rules()
    {
        return [
            'post_id' => 'required|exists:posts,id',
            'content' => 'required|max:255',
        ];
    }
}
