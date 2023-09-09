<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Utils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\CreatePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\Gallery;
use App\Models\Image;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Intervention\Image\Facades\Image as ImageManager;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $searchQuery = trim(str_replace('%', null, $request->input('search_query')));

        if (!!$searchQuery) {
            $posts = Post::where('title', 'LIKE', "%{$searchQuery}%")
                ->orWhere('content', 'LIKE', "%{$searchQuery}%")
                ->latest();

            return response()->json(Utils::paginate($posts, 20, ['search_query' => $request->input('search_query')]));
        }

        return response()->json(Utils::paginate(Post::latest()));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreatePostRequest $request)
    {
        $imageData = is_array($request->input('image_data')) ? $request->input('image_data') : [];
        $imageFiles = is_array($request->file('image_files')) ? $request->file('image_files') : [];

        $gallery = null;
        $isCopiedGallery = false;
        $galleryCopyResult = null;

        $posterImageId = null;

        if ($request->has('gallery_id')) {
            $gallery = $this->copyGalleryFromRequest( $galleryCopyResult);
            $isCopiedGallery = true;
        }
        else {
            $gallery = Gallery::create([
                'name' => $request->input('title'),
                'user_id' => Auth::id()
            ]);
        }

        $post = new Post();
        $post->title = $request->input('title');
        $post->content = $request->input('content');
        $post->user_id = Auth::id();
        $post->gallery_id = $gallery->id;

        if (!$post->save()) abort( 500, json_encode(['error' => 'Could not create post']));

        if ($isCopiedGallery) {
            if (is_numeric($request->input('poster_image')) && $request->input('poster_image_type') == 'id' && $galleryCopyResult && isset($galleryCopyResult['images'][$request->input('poster_image')])) {
                $imageId = $galleryCopyResult['images'][$request->input('poster_image')];

                if ($post->gallery->images->where('id', $posterImageId)->exists()) {
                    $posterImageId = $imageId;
                }
            }
        }

        if (!!count($imageData)) {
            $images = ['data' => [], 'files' => []];
            $count = 0;

            foreach ($imageData as $imageInfo) {
                //if ($count < $maxImageCount) break;

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

        if (!!$posterImageId) {
            $post->meta = ['poster_image' => $posterImageId];
            $post->save();
        }

        $post->load('gallery');

        return response()->json($post->refresh());
    }

    /**
     * Display the specified resource.
     *
     * @param Post $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Post $post)
    {
        $post->load('gallery');

        return response()->json($post);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Post $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdatePostRequest $request, Post $post)
    {
        $postData = Utils::extractNonNullOrEmpty($request->only(['title', 'content']));
        $imageFiles = is_array($request->file('image_files')) ? $request->file('image_files') : [];
        $imageData = is_array($request->input('image_data')) ? $request->input('image_data') : [];
        $imageIdsToRemove = is_array($request->input('image_remove')) ? $request->input('image_remove') : [];
        $posterImageId = (int)$request->input('poster_image');
        $removePosterImage = !!$request->input('remove_poster_image');
        $isCopiedGallery = false;
        $galleryCopyResult = null;

        if ($request->has('gallery_id')) {
            if ($post->gallery_id != $request->has('gallery_id')) {
                $post->gallery = $this->copyGalleryFromRequest($galleryCopyResult);
                $post->gallery_id = $post->gallery->id;
                $isCopiedGallery = true;
            }
        } else if (!$post->gallery) {
            $post->gallery = Gallery::create([
                'name' => isset($postData['title']) ? $postData['title'] : $post->title,
                'user_id' => Auth::id()
            ]);
        } else {
            $post->gallery->update([
                'name' => !empty($postData['title']) ? $postData['title'] : $post->title
            ]);
        }

        if ($isCopiedGallery) {
            $postData['meta'] = $post->meta;
            Utils::unset($postData['meta'], 'poster_image');
        }

        if ($post->update($postData)) {
            if (count($imageData)) {
                $maxImagesAllowed = (config('filesystems.images.max_count') ?: 5) ;
                $galleryImageCount = $post->gallery->images_count;
                $countNewImage = 0;
                $fileData = [];

                foreach ($imageData as $imageInfo) {
                    $image = null;
                    $isNewImage = false;
                    if (!empty($imageInfo['id']) && !!($img = $post->gallery->images()->where('id', $imageInfo['id'])->first())) {
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
                            $name = date('Ymd') . '-' . Auth::id() . '-' . Str::random(32) . '-' . $post->gallery->id;
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
                            $image->gallery_id = $post->gallery->id;
                        }

                        if ($hasChanges && $image->save()) {
                            if ($isNewImage) $countNewImage++;

                            if (count($data)) $fileData[] = $data;

                            if (empty($posterImageId) && !empty($imageInfo['poster_image'])) {
                                $posterImageId = $image->id;
                            }
                        }
                    }

                    if (($galleryImageCount + $countNewImage) == $maxImagesAllowed) {
                        break;
                    }
                }

                if (!$removePosterImage && !empty($posterImageId) && !!$post->gallery->images()->where('id', $posterImageId)->first()) {
                    $post->setMetaValue('poster_image', $posterImageId, true);
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

                $imagesToRemove = $post->gallery->images()->whereIn('id', $imageIdsToRemove)->get(['id', 'name', 'meta']);
                foreach ($imagesToRemove as $image) {
                    if ($image->delete()) {
                        Storage::disk('images')->delete($image->name);
                        if (!!$image->getMetaValue('thumb')) {
                            Storage::disk('images')->delete($image->getMetaValue('thumb'));
                        }
                    }
                }
            }
        }

        $post->load('gallery');

        return response()->json($post->refresh());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Post $post)
    {
        $isDeleted = $post->delete();

        return response()->json(['deleted' => (bool)$isDeleted], $isDeleted ? 200 : 500);
    }

    public function copyGalleryFromRequest(array &$galleryCopyResult = null)
    {
        if (request()->has('gallery_id')) {
            $selectedGallery = Gallery::where('id', request()->input('gallery_id'))
                ->where('user_id', Auth::id())
                ->first();

            if (!$selectedGallery) {
                throw ValidationException::withMessages([
                    'gallery_id' => 'The gallery does not exist or does not belong to the user.',
                ]);
            }

            $imageExclude = is_array(request()->input('image_remove')) ? request()->input('image_remove') : [];
            $imageData = [];

            if (is_array(request()->input('image_data'))) {
                foreach (request()->input('image_data') as $d) {
                    if (!empty($d['id'])) {
                        if (array_key_exists('caption', $d)) $imageData[$d['id']] = $d['caption'];
                    }
                }
            }

            $gallery = $selectedGallery->copy([
                'name' => request()->input('title'),
                'description' => null,
                'image_exclude' => $imageExclude,
                'image_data' => $imageData,
            ], $galleryCopyResult);

            abort_if(!$gallery, 500, "Could copy gallery {$selectedGallery->id}");

            return $gallery;
        }

        throw new HttpException(500, 'Could not copy gallery: gallery_id was not provided');
    }
}
