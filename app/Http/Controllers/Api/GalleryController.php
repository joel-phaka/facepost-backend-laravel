<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Utils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gallery\CreateGalleryRequest;
use App\Http\Requests\Gallery\UpdateGalleryRequest;
use App\Models\Gallery;
use App\Models\Image;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image as ImageManager;

class GalleryController extends Controller
{
    public function index()
    {
        return response()->json(Utils::paginate(Gallery::latest()));
    }

    public function store(CreateGalleryRequest $request)
    {
        $imageData = is_array($request->input('image_data')) ? $request->input('image_data') : [];
        $imageFiles = is_array($request->input('image_files')) ? $request->file('image_files') : [];

        $gallery = Gallery::create($request->only(['name']));
        $maxImageCount = config('filesystems.images.max_count') ?: 5;

        if (count($imageData)) {
            $images = ['data' => [], 'files' => []];
            $count = 0;

            foreach ($imageData as $imageInfo) {
                if ($count < $maxImageCount) break;

                if (!(!empty($imageInfo['file_id']) && !empty($imageFiles[$imageInfo['file_id']]))) {
                    continue;
                }
                $file = $request->file($imageInfo['file_id']);
                $name = date('Ymd') . '-' . Auth::id() . '-' . Str::random(32) . '-' . $gallery->id;
                $imageName = "{$name}." . $file->extension();
                $thumbName = "{$name}_thumb." . $file->extension();
                list($width, $height) = getimagesize($file->path());
                $caption = trim(isset($imageInfo['caption']) && !empty(trim($imageInfo['caption'])) ? $imageInfo['caption'] : null);
                $timestamp = now();

                $data = [
                    'caption' => $caption,
                    'name' => $imageName,
                    'width' => $width,
                    'height' => $height,
                    'type' => $file->getMimeType(),
                    'user_id' => Auth::id(),
                    'gallery_id' => $gallery->id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'meta' => ['thumb' => $thumbName]
                ];

                $images['data'][] = $data;
                $images['files'][] = $file;
                $count++;
            }

            if (count($images['data'])) {
                if (!!Image::insert($images['data'])) {
                    foreach ($images['files'] as $index => $file) {
                        $file->storeAs('/', $images['data'][$index]['name'], 'images');

                        if (!empty($images['data'][$index]['meta']['thumb'])) {
                            $width = config('filesystems.images.thumb_width') ?: 320;
                            $height = config('filesystems.images.thumb_height') ?: 180;
                            $interventionImage = ImageManager::make(Storage::disk('images')->get($images['data'][$index]['meta']['thumb']));
                            $interventionImage->fit($width, $height, function ($constraint) {
                                $constraint->upsize();
                            });
                            $interventionImage->save(config('filesystems.disks.images.root') . '/' . $images['data'][$index]['meta']['thumb']);
                            $interventionImage->destroy();
                        }
                    }
                }
            }
        }

        abort_if(!$gallery, 500,"Could not create Gallery");

        return $gallery->refresh();
    }

    public function update(Gallery $gallery, UpdateGalleryRequest $request)
    {
        $gallery->verifyAuthUser(true);

        $imageData = is_array($request->input('image_data')) ? $request->input('image_data') : [];
        $imageFiles = is_array($request->input('image_files')) ? $request->file('image_files') : [];
        $imageIdsToRemove = array_unique(is_array($request->input('image_remove')) ? $request->input('image_remove') : []);

        if (count($imageData)) {
            $maxImagesAllowed = (config('filesystems.images.max_count') ?: 5) ;
            $galleryImageCount = $gallery->images_count;
            $countNewImage = 0;
            $fileData = [];

            foreach ($imageData as $imageInfo) {
                $image = null;
                $isNewImage = false;
                if (!empty($imageInfo['id']) && !!($img = $gallery->images()->where('id', $imageInfo['id'])->first())) {
                    if (!empty($imageInfo['remove']) || in_array($imageInfo['id'], $imageIdsToRemove)) {
                        continue;
                    }
                    $image = $img;
                } else if(!empty($imageInfo['file_id']) && !empty($imageFiles[$imageInfo['file_id']])) {
                    $isNewImage = true;
                    $countNewImage++;
                }

                if (!!$image || $isNewImage) {
                    $hasChanges = false;
                    $image = $image ?: new Image();
                    $file = !empty($imageInfo['file_id']) && !empty($imageFiles[$imageInfo['file_id']]) ? $imageFiles[$imageInfo['file_id']] : null;
                    $data = [];

                    if (!!$file) {
                        $name = date('Ymd') . '-' . Auth::id() . '-' . Str::random(32) . '-' . $gallery->id;
                        $image->name = "{$name}." . $file->extension();
                        $thumbName = "{$name}_thumb." . $file->extension();
                        list($width, $height) = getimagesize($file->path());
                        $image->width = $width;
                        $image->height = $height;
                        $image->type = $file->getMimeType();
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
                        $image->gallery_id = $gallery->id;
                    }

                    if ($hasChanges && $image->save()) {
                        if (count($data)) $fileData[] = $data;
                    }
                }

                if (($galleryImageCount + $countNewImage) == $maxImagesAllowed) {
                    break;
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

            $imagesToRemove = $gallery->images()->whereIn('id', $imageIdsToRemove)->get(['id', 'name']);
            foreach ($imagesToRemove as $image) {
                if ($image->delete()) {
                    Storage::disk('images')->delete($image->name);

                    if (!!$image->getMetaValue('thumb')) {
                        Storage::disk('images')->delete($image->getMetaValue('thumb'));
                    }
                }
            }
        }

        return $gallery->refresh();
    }

    public function destroy(Gallery $gallery)
    {
        $gallery->verifyAuthUser(true);

        $isDeleted = $gallery->delete();
        $images = $gallery->images()->get(['id', 'name']);
        $imageIds = $images->pluck('id');
        $imageNames = $images->pluck('name');

        Image::whereIn('id', $imageIds)->delete();

        foreach ($imageNames as $imageName) {
            Storage::disk('images')->delete($imageName);
        }

        return response()->json(['deleted' => (bool)$isDeleted], $isDeleted ? 200 : 500);
    }
}
