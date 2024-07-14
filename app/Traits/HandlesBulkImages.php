<?php

namespace App\Traits;

use App\Models\Gallery;
use App\Models\Image;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image as ImageManager;

trait HandlesBulkImages
{
    protected function handleImageUploads(Gallery $gallery = null, $verifyMaxImages = false, $metaOfInterest = [])
    {
        $imageFiles = is_array(request()->file('image_files')) ? request()->file('image_files') : [];
        $imageData = is_array(request()->input('image_data')) ? request()->input('image_data') : [];
        $removeImages = is_array(request()->input('remove_images')) ? request()->input('remove_images') : [];
        $newFiles = [];
        $validImages = [];
        $metaFromImages = [];

        $galleryImagesCount = null;
        $galleryMaxImages = config('const.gallery.max_images');

        if ($gallery) {
            $gallery->verifyAuthUser(true);
            $galleryImagesCount = $gallery->images_count;
        }

        $newFilesCount = 0;

        foreach ($imageData as $i => $imageInfo) {
            if (!empty($imageInfo['id']) && (!empty($imageInfo['remove']) || in_array($imageInfo['id'], $removeImages))) {
                if (!in_array($imageInfo['id'], $removeImages)) {
                    $removeImages[] = $imageInfo['id'];
                }

                if (!!$galleryImagesCount) {
                    $galleryImagesCount--;
                }

                continue;
            }

            $image = null;
            $file = null;

            if (!empty($imageInfo['id'])) {
                $image = Image::where('id', $imageInfo['id'])
                    ->where('user_id', Auth::id());

                if ($gallery) {
                    $image = $image->where('gallery_id', $gallery->id);
                }

                $image = $image->first();

            }
            else if (array_key_exists('file_id', $imageInfo) && !empty($imageFiles[$imageInfo['file_id']])) {
                $file = $imageFiles[$imageInfo['file_id']];

                if ($file->isFile() && $file->isValid()) {
                    $newFilesCount++;

                    if (($galleryImagesCount + $newFilesCount) > $galleryMaxImages) {
                        break;
                    }

                    $image = new Image();
                    $image->user_id = Auth::id();
                    $name = date('Ymd') . '-' . Auth::id() . '-' . Str::random(32) . '-' . (!!$gallery && !!$gallery->id ? $gallery->id : '');
                    $image->name = "{$name}." . $file->extension();
                    $image->thumb_name = "{$name}_thumb." . $file->extension();
                    list($width, $height) = getimagesize($file->path());
                    $image->width = $width;
                    $image->height = $height;
                    $image->type = $file->getMimeType();

                    if (!!$gallery && !!$gallery->id) {
                        $image->gallery_id = $gallery->id;
                    }
                }
                else {
                    $file = null;
                }
            }

            if (!!$image) {
                if (!empty($imageInfo['caption']) || !empty($imageInfo['caption']) && $image->caption != $imageInfo['caption']) {
                    $image->caption = $imageInfo['caption'];
                }

                if ($image->save()) {
                    if ($file) {
                        $newFiles[] = [
                            'file' => $file,
                            'image' => $image,
                            'name' => $image->name,
                            'thumb_name' => $image->thumb_name
                        ];
                    }

                    if (empty($metaFromImages['original_indexes']) || !is_array($metaFromImages['original_indexes'])) {
                        $metaFromImages['original_indexes'] = [];
                    }
                    $metaFromImages['original_indexes'][$image->id] = $i;

                    $validImages[$image->id] = $image;

                    foreach ($metaOfInterest as $mk => $mv) {
                        if (array_key_exists($mk, $imageInfo) && (!!trim($imageInfo[$mk]) || $imageInfo[$mk] === 0)) {
                            if (empty($metaFromImages[$mk])) {
                                $metaFromImages[$mk] = [];
                            }

                            $metaFromImages[$mk][$image->id] = $image->id;
                        }
                    }
                }
            }
        }

        foreach ($newFiles as $fileInfo) {
            $file = $fileInfo['file'];
            $image = $fileInfo['image'];
            $name = $fileInfo['name'];
            $thumb_name = $fileInfo['thumb_name'];

            if ($file->storeAs('/', $name, 'images')) {
                $thumb_width = config('const.images.thumb_width') ?: 320;
                $thumb_height = config('const.images.thumb_height') ?: 180;
                $interventionImage = ImageManager::make(Storage::disk('images')->get($name));
                $interventionImage->fit($thumb_width, $thumb_height, function ($constraint) {
                    $constraint->upsize();
                });
                $interventionImage->save(config('filesystems.disks.images.root') . '/' . $thumb_name);
            }
            else {
                if (!empty($validImages[$image->id])) {
                    unset($validImages[$image->id]);
                }

                foreach ($metaFromImages as $metaName => $metaFromImage) {
                    if (!empty($metaFromImage[$image->id])) {
                        unset($metaFromImages[$metaName][$image->id]);
                    }
                }

                $image->delete();
            }
        }

        if (!!count($removeImages)) {
            $imagesToDelete = Auth::user()->images()
                ->whereIn('id', $removeImages)
                ->get();

            foreach ($imagesToDelete as $image) {
                if ($image->delete()) {
                    Storage::disk('images')->delete($image->name);
                    if (!!$image->thumb_name) {
                        Storage::disk('images')->delete($image->thumb_name);
                    }
                }
            }
        }

        return [
            'images' => $validImages,
            'meta' => $metaFromImages
        ];
    }

    protected function deleteImagesFromRequest(Gallery $gallery = null)
    {
        $removeImages = is_array(request()->input('remove_images')) ? request()->input('image_remove') : [];

        if ($gallery) {
            $gallery->verifyAuthUser(true);
        }

        $errorCount = 0;

        if (count($removeImages)) {
            $imagesToDelete = Auth::user()->images()
                ->whereIn('id', $removeImages);

            if (!!$imagesToDelete) {
                $imagesToDelete = $imagesToDelete->where('gallery_id', $gallery->id);
            }

            foreach ($imagesToDelete as $image) {
                if ($image->delete()) {
                    Storage::disk('images')->delete($image->name);
                    if (!!$image->thumb_name) {
                        Storage::disk('images')->delete($image->thumb_name);
                    }
                }
                else {
                    $errorCount++;
                }
            }
        }

        return $errorCount == 0;
    }
}
