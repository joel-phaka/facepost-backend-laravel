<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Utils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Image\UploadImageRequest;
use App\Models\User;
use App\Traits\HandlesBulkImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImageController extends Controller
{
    use HandlesBulkImages;

    public function index(User $user = null)
    {
       if (!$user || $user->isAuthUser()) {
           $user = $user ?: Auth::user();
       }

       return response()->json(Utils::paginate($user->images()->latest()));
    }

    public function upload(UploadImageRequest $request)
    {
        $images = $this->handleImageUploads();

        return response()->json([
            'success' => !!count($images),
            'images' => $images
        ]);
    }

    public function destroy(Request $request)
    {
        $fileDelete = $this->deleteImagesFromRequest();

        return response()->json(['success' => $fileDelete]);
    }
}
