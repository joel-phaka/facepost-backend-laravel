<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Utils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gallery\CreateGalleryRequest;
use App\Http\Requests\Gallery\UpdateGalleryRequest;
use App\Models\Gallery;
use App\Traits\HandlesBulkImages;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    use HandlesBulkImages;

    public function index(Request $request)
    {
        $sort = in_array(($s = strval($request->input('sort'))), ['asc', 'desc']) ? $s : 'desc';
        $perPage = $request->integer('per_page');

        $galleries = Gallery::ofActiveUsers()
            ->orderByCreated($sort);

        return response()->json(Utils::paginate($galleries, ['per_page' => $perPage, 'appends' => compact('sort')]));
    }

    public function store(CreateGalleryRequest $request)
    {
        $gallery = Gallery::create($request->only(['name']));

        if ($gallery) {
            $this->handleImageUploads($gallery);
        }

        return $gallery->refresh();
    }

    public function show(Gallery $gallery)
    {
        return $gallery->load('images');
    }

    public function update(Gallery $gallery, UpdateGalleryRequest $request)
    {
        $gallery->update($request->only(['name']));

        $this->handleImageUploads($gallery);

        return $gallery->refresh();
    }

    public function destroy(Gallery $gallery)
    {
        $success = $gallery->delete();
        $this->deleteImages($gallery);
        $status = $success ? 200 : 500;

        return response()->json(['success' => $success], $status);
    }
}
