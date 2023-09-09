<?php


namespace App\Http\Requests\Comment;


use Illuminate\Foundation\Http\FormRequest;

class ReplyToCommentRequest extends FormRequest
{
    public function rules()
    {
        return [
            'content' => 'required|max:255',
        ];
    }
}
