<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Utils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\CreatePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\Gallery;
use App\Models\Post;
use App\Traits\HandlesBulkImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PostController extends Controller
{
    use HandlesBulkImages;

    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if ($request->has('search_query')) {
            $searchQuery = trim(preg_replace('/%+/', '', $request->input('search_query')));
            $appends = $request->only(['search_query']);
            $posts = (new Post)->newCollection();

            if (!!$searchQuery) {
                $posts = Post::ofActiveUsers()
                    ->where('title', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('content', 'LIKE', "%{$searchQuery}%")
                    ->latest();

            }

            return response()->json(Utils::paginate($posts, null, $appends));
        }

        return response()->json(Utils::paginate(Post::ofActiveUsers()->latest()));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreatePostRequest $request)
    {
        $post = new Post();
        $post->title = $request->input('title');
        $post->content = $request->input('content');
        $post->user_id = Auth::id();

        if ($post->save()) {
            $posterImageId = null;
            $galleryId = intval($request->input('gallery_id'));

            if (!!$galleryId) {
                $copyGalleryResult = null;
                $gallery = $this->copyGalleryFromRequest($post->title, $copyGalleryResult);

                if (!!$gallery && $post->update(['gallery_id' => $gallery->id])) {
                    $copiedGalleryPosterImage = data_get($copyGalleryResult, 'images.' . $request->input('poster_image'));
                    if (!!$copiedGalleryPosterImage) {
                        $posterImageId = $copiedGalleryPosterImage->id;
                    }
                }
            } else if (is_array($request->input('image_data')) && is_array($request->file('image_files'))) {
                $gallery = Gallery::create([
                    'name' => $post->title,
                    'user_id' => Auth::id()
                ]);

                if ($gallery && $post->update(['gallery_id' => $gallery->id])) {
                    $result = $this->handleImageUploads($gallery);

                    if (!!count($result['images']) && is_array(data_get($result, 'meta.index_mapping')) && !!count(data_get($result, 'meta.index_mapping'))) {
                        if ($request->input('poster_image') || is_numeric($request->input('poster_image'))) {
                            $posterImageId = array_search($request->input('poster_image'), data_get($result, 'meta.index_mapping'));
                        }
                    }
                }
            }

            if (!!$post->gallery_id && !!$posterImageId) {
                $posterImageId = $post->gallery->images
                    ->where('id', $posterImageId)
                    ->first()
                    ?->id;

                if (!!$posterImageId) {
                    $post->setMeta('poster_image', $posterImageId);
                }
            }

            $post->refresh();
            $post->load(['user', 'gallery']);

            return response()->json($post);
        }

        return response()->json(['message' => 'Could not create post.'], 500);
    }

    /**
     * Display the specified resource.
     *
     * @param Post $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Post $post)
    {
        $post->load(['gallery', 'gallery.images']);

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
        $galleryId = intval($request->input('gallery_id'));
        $removePosterImage = !!$request->input('remove_poster_image');

        if (!count($postData) || $post->update($postData)) {
            $posterImageId = null;

            if (!!$galleryId && $galleryId != $post->gallery_id) {
                $copyGalleryResult = null;
                $gallery = $this->copyGalleryFromRequest($post->title, $copyGalleryResult);

                if (!!$gallery) {
                    $post->gallery_id = $gallery->id;

                    if ($post->save()) {
                        if (!$removePosterImage) {
                            $copiedGalleryPosterImage = data_get($copyGalleryResult, 'images.' . $request->input('poster_image'));

                            if (!!$copiedGalleryPosterImage) {
                                $posterImageId = $copiedGalleryPosterImage->id;
                            }
                        }
                    }
                }
            } else if (is_array($request->input('image_data')) || is_array($request->input('remove_images'))) {
                $hasValidGallery = !!$post->gallery_id;

                if (!$hasValidGallery) {
                    $gallery = Gallery::create([
                        'name' => $post->title,
                        'user_id' => Auth::id()
                    ]);

                    if (!!$gallery && $post->update(['gallery_id' => $gallery->id])) {
                        $hasValidGallery = true;
                    }
                }

                if ($hasValidGallery) {
                    $result = $this->handleImageUploads($post->gallery);

                    if (!!count(data_get($result, 'meta.index_mapping'))) {
                        if (!$removePosterImage && $request->input('poster_image') || is_numeric($request->input('poster_image'))) {
                            $posterImageId = array_search($request->input('poster_image'), data_get($result, 'meta.index_mapping'));
                        }
                    }
                }
            } else if (!!$post->gallery_id) {
                $posterImageId = intval($request->input('poster_image'));
            }

            if (!!$post->gallery_id) {
                $posterImageId = !$posterImageId
                    ? null
                    : $post->gallery->images
                        ->where('id', $posterImageId)
                        ->first()
                        ?->id;
            } else {
                $posterImageId = null;
            }


            if (!!$posterImageId && !$removePosterImage) {
                $post->setMeta('poster_image', $posterImageId);
            } else if ($removePosterImage) {
                $post->removeMeta('poster_image');
            }
        }

        return response()->json($post->refresh());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Post  $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Post $post)
    {
        $success = !!$post->delete();
        $status = $success ? 200 : 500;

        return response()->json(['success' => $success], $status);
    }

    public function copyGalleryFromRequest($newName = '', ?array &$galleryCopyResult = null)
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

            $newName = trim($newName) ?: $selectedGallery->name;
            $imageExclude = is_array(request()->input('remove_images')) ? request()->input('remove_images') : [];
            $imageCaptions = [];

            if (is_array(request()->input('image_data'))) {
                foreach (request()->input('image_data') as $d) {
                    if (intval(data_get($d, 'id')) && ctype_digit(data_get($d, 'id'))) {
                        if (data_get($d, 'remove') && !in_array($d['id'], $imageExclude)) {
                            $imageExclude[] = $d['id'];
                            continue;
                        }

                        if (array_key_exists('caption', $d)) {
                            $imageCaptions[$d['id']] = $d['caption'];
                        }
                    }
                }
            }

            $gallery = $selectedGallery->copy([
                'name' => $newName,
                'description' => null,
                'image_exclude' => $imageExclude,
                'image_captions' => $imageCaptions,
            ], $galleryCopyResult);

            abort_if(!$gallery, 500, "Could copy gallery {$selectedGallery->id}");

            return $gallery;
        }

        throw new HttpException(500, 'Could not copy gallery: gallery_id was not provided');
    }
}
