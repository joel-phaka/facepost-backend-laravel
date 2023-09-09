<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Image\UploadImageRequest;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image as ImageManager;

class ImageController extends Controller
{
    public function upload(UploadImageRequest $request)
    {
        $imageFiles = is_array($request->file('image_files')) ? $request->file('image_files') : [];
        $imageData = is_array($request->input('image_data')) ? $request->input('image_data') : [];
        $imageIdsToRemove = is_array($request->input('image_remove')) ? $request->input('image_remove') : [];
        $fileData = [];
        $imageIdsToReturn = [];

        foreach ($imageData as $imageInfo) {
            $image = null;
            $isNewImage = false;
            if (!empty($imageInfo['id']) && !!($img = Auth::user()->images()->where('id', $imageInfo['id'])->first())) {
                if (!empty($imageInfo['remove']) || in_array($imageInfo['id'], $imageIdsToRemove)) {
                    continue;
                }
                $image = $img;
            } else if(!empty($imageInfo['file_id']) && !empty($imageFiles[$imageInfo['file_id']])) {
                $isNewImage = true;
            }

            if (!!$image || $isNewImage) {
                $hasChanges = false;
                $image = $image ?: new Image();
                $file = !empty($imageInfo['file_id']) && !empty($imageFiles[$imageInfo['file_id']]) ? $imageFiles[$imageInfo['file_id']] : null;
                $data = [];

                if (!!$file) {
                    $name = date('Ymd') . '-' . Auth::id() . '-' . Str::random(32);
                    $image->name = "{$name}." . $file->extension();
                    $thumbName = "{$name}_thumb." . $file->extension();
                    list($width, $height) = getimagesize($file->path());
                    $image->width = $width;
                    $image->height = $height;
                    $image->type = $file->getMimeType();
                    $image->user_id = Auth::id();
                    $image->setMetaValue('thumb', $thumbName);

                    $data = ['file' => $file, 'name' => $image->name, 'thumb_name' => $thumbName];
                    if (!$isNewImage) {
                        $imageIdsToRemove[] = $image->id;
                    }
                    $hasChanges = true;
                }

                $caption = trim(isset($imageInfo['caption']) && !empty(trim($imageInfo['caption'])) ? $imageInfo['caption'] : null);

                if ($image->caption != $caption) {
                    $image->caption = $caption;
                    $hasChanges = true;
                }

                if ($isNewImage) {
                    $image->user_id = Auth::id();
                }

                if ($hasChanges && $image->save()) {
                    if (count($data)) $fileData[] = $data;
                }

                if (!empty($image->id)) {
                    $imageIdsToReturn[] = $image->id;
                }
            }
        }

        foreach ($fileData as $fileInfo) {
            if (!empty($fileInfo['file']) && !empty($fileInfo['name'])) {
                $fileInfo['file']->storeAs('/', $fileInfo['name'], 'images');

                if (!empty($fileInfo['thumb_name'])) {
                    $width = config('filesystems.images.thumb_width') ?: 320;
                    $height = config('filesystems.images.thumb_height') ?: 180;
                    $interventionImage = ImageManager::make(Storage::disk('images')->get($fileInfo['name']));
                    $interventionImage->fit($width, $height, function ($constraint) {
                        $constraint->upsize();
                    });
                    $interventionImage->save(config('filesystems.disks.images.root') . '/' . $fileInfo['thumb_name']);
                }
            }
        }

        $imagesToRemove = Auth::user()->images()->whereIn('id', $imageIdsToRemove)->get(['id', 'name', 'meta']);
        foreach ($imagesToRemove as $image) {
            if ($image->delete()) {
                Storage::disk('images')->delete($image->name);
                if (!!$image->getMetaValue('thumb')) {
                    Storage::disk('images')->delete($image->getMetaValue('thumb'));
                }
            }
        }

        return Auth::user()->images()->whereIn('id', $imageIdsToReturn);
    }

    public function destroy(Request $request)
    {
        $imageIdsToRemove = is_array($request->input('image_remove')) ? $request->input('image_remove') : [];

        $imagesToRemove = Auth::user()->images()->whereIn('id', array_unique($imageIdsToRemove))->get(['id', 'name', 'meta']);
        foreach ($imagesToRemove as $image) {
            if ($image->delete()) {
                Storage::disk('images')->delete($image->name);
                if (!!$image->getMetaValue('thumb')) {
                    Storage::disk('images')->delete($image->getMetaValue('thumb'));
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}
