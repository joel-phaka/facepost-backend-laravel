<?php

namespace App\Traits;

use App\Models\Gallery;
use App\Models\Image;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image as ImageManager;

trait HandlesBulkImages
{
    protected function handleImageUploads(?Gallery $gallery = null, array $metaOfInterest = []): array
    {
        $imageFiles = is_array(request()->file('image_files')) ? request()->file('image_files') : [];
        $imageData = is_array(request()->input('image_data')) ? request()->input('image_data') : [];
        $removeImages = is_array(request()->input('remove_images')) ? request()->input('remove_images') : [];
        $newFiles = [];
        $validImages = [];
        $metaFromImages = ['index_mapping' => []];

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

            } else if (array_key_exists('file_id', $imageInfo) && !empty($imageFiles[$imageInfo['file_id']])) {
                $file = $imageFiles[$imageInfo['file_id']];

                if ($file->isFile() && $file->isValid()) {
                    $newFilesCount++;

                    if (($galleryImagesCount + $newFilesCount) > $galleryMaxImages) {
                        break;
                    }

                    $image = new Image();
                    $image->user_id = Auth::id();
                    $name = date('Ymd') . '-' . Auth::id() . '-' . Str::random(32) . ($gallery?->id ? '-' . $gallery->id : '');
                    $image->name = "{$name}." . $file->extension();
                    $image->thumb_name = "{$name}_thumb." . $file->extension();
                    [$width, $height] = getimagesize($file->path());
                    $image->width = $width;
                    $image->height = $height;
                    $image->type = $file->getMimeType();

                    if (!!$gallery && !!$gallery->id) {
                        $image->gallery_id = $gallery->id;
                    }
                } else {
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

                    $metaFromImages['index_mapping'][$image->id] = $i;
                    $validImages[$image->id] = $image;

                    foreach ($metaOfInterest as $mk => $mv) {
                        if (array_key_exists($mk, $imageInfo) && (!!trim($imageInfo[$mk]) || is_numeric($imageInfo[$mk]))) {
                            if (empty($metaFromImages[$mk])) {
                                $metaFromImages[$mk] = [];
                            }

                            $metaFromImages[$mk][$image->id] = $image->id;
                        }
                    }
                }
            }
        }

        $invalidImageIds = [];

        foreach ($newFiles as $fileInfo) {
            $file = $fileInfo['file'];
            $image = $fileInfo['image'];
            $name = $fileInfo['name'];
            $thumb_name = $fileInfo['thumb_name'];

            $isValid = false;

            if ($file->storeAs('/', $name, 'images')) {
                $thumb_width = config('const.images.thumb_width') ?: 320;
                $thumb_height = config('const.images.thumb_height') ?: 180;
                $interventionImage = ImageManager::read(Storage::disk('images')->get($name));
                $interventionImage->scale($thumb_width, $thumb_height);
                $interventionImage->save(config('filesystems.disks.images.root') . '/' . $thumb_name);

                $isValid = Storage::disk('images')->exists($thumb_name);
            }

            if (!$isValid) {
                data_forget($validImages, $image->id);
                data_forget($metaFromImages, '*.' . $image->id);

                $invalidImageIds[] = $image->id;
            }
        }

        $removeImages = array_merge($removeImages, $invalidImageIds);

        $this->deleteImages($gallery, $removeImages);

        return [
            'images' => $validImages,
            'meta' => $metaFromImages
        ];
    }

    protected function deleteImages(?Gallery $gallery = null,  array $imageIds = []): bool
    {
        $countDeleted = false;
        $query = DB::table('images')
            ->select(['id', 'name', 'thumb_name', 'gallery_id'])
            ->where('user_id', Auth::id());

        if (!!$gallery) {
            if ($gallery->verifyAuthUser()) {
                return false;
            }

            $query->where('galley_id', $gallery->id)
                  ->when(
                      count($imageIds),
                      fn(QueryBuilder $q) => $q->whereIn('id', $imageIds)
                  );
        } else {
            $query->whereIn('id', $imageIds);
        }

        $imagesToDelete = $query->get();

        if (count($imagesToDelete)) {
            $countDeleted = Image::destroy($imagesToDelete->pluck(['id']));

            foreach ($imagesToDelete as $image) {
                Storage::disk('images')->delete($image->name);

                if (!empty($image->thumb_name)) {
                    Storage::disk('images')->delete($image->thumb_name);
                }
            }
        }

        return $countDeleted > 0;
    }
}
