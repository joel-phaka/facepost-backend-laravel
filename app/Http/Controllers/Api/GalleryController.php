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
        $gallery->update($request->only(['name']));

        $this->handleImageUploads($gallery, true);

        return $gallery->refresh();
    }

    public function destroy(Gallery $gallery)
    {
        $isDeleted = $gallery->delete();
        $imagesDeleted = $this->deleteImagesFromRequest($gallery);

        return response()->json(
            ['success' => !!$isDeleted && $imagesDeleted],
            $isDeleted && $imagesDeleted ? 200 : 500
        );
    }
}
