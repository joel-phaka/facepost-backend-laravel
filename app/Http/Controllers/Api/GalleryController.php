<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Utils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gallery\CreateGalleryRequest;
use App\Http\Requests\Gallery\UpdateGalleryRequest;
use App\Models\Gallery;
use App\Traits\HandlesBulkImages;

class GalleryController extends Controller
{
    use HandlesBulkImages;

    public function index()
    {
        return response()->json(Utils::paginate(Gallery::latest()));
    }

    public function store(CreateGalleryRequest $request)
    {
        $gallery = Gallery::create($request->only(['name']));

        if ($gallery) {
            $this->handleImageUploads($gallery, true);
        }

        return $gallery->refresh();
    }

    public function update(Gallery $gallery, UpdateGalleryRequest $request)
    {
        $gallery->verifyAuthUser(true);

        if ($gallery->name != $request->input('name')) {
            $gallery->name = $request->input('name');
            $gallery->save();
        }

        $this->handleImageUploads($gallery, true);

        return $gallery->refresh();
    }

    public function destroy(Gallery $gallery)
    {
        $gallery->verifyAuthUser(true);

        $isDeleted = $gallery->delete();
        $filesDelete = $this->deleteImagesFromRequest($gallery);

        return response()->json(
            ['success' => !!$isDeleted && $filesDelete],
            !!$isDeleted && $filesDelete ? 200 : 500
        );
    }
}
