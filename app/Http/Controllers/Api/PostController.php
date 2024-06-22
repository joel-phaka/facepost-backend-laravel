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
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $searchQuery = trim(str_replace('%', '', $request->input('search_query')));

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
        $post = new Post();
        $post->title = $request->input('title');
        $post->content = $request->input('content');
        $post->user_id = Auth::id();

        if ($post->save()) {
            $posterImageId = null;
            $gallery = null;

            if ($request->has('gallery_id') && $request->input('gallery_id') != $post->gallery_id) {
                $copyGalleryResult = null;
                $gallery = $this->copyGalleryFromRequest($copyGalleryResult);
                $selectedPosterImage = data_get($copyGalleryResult, 'images.' . $request->input('poster_image'));

                if (!!$selectedPosterImage) {
                    $posterImageId = $selectedPosterImage->id;
                }
            }
            else if (is_array($request->input('image_data')) && is_array($request->file('image_files'))) {
                $gallery = Gallery::create([
                    'name' => $post->title,
                    'user_id' => Auth::id()
                ]);

                $result = $this->handleImageUploads($gallery, true);

                if (!!count($result['images']) && is_array(data_get($result, 'meta.original_indexes')) && !!count(data_get($result, 'meta.original_indexes'))) {
                    if ($request->input('poster_image') || is_numeric($request->input('poster_image'))) {
                        $posterImageId = array_search($request->input('poster_image'), data_get($result, 'meta.original_indexes'));
                    }
                }
            }

            if (!!$gallery) {
                $post->gallery_id = $gallery->id;
                $post->save();

                if (!!$posterImageId) {
                    $post->setMeta('poster_image', $posterImageId);
                }
            }


            $post->load('gallery');

            return response()->json($post->refresh());
        }

        return response()->json([
            'success' => false,
            'message' => 'Could not create post.'
        ], 500);
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

        if (!count($postData) || $post->update($postData)) {
            $posterImageId = null;
            $gallery = null;

            if ($request->has('gallery_id')) {
                if ($request->input('gallery_id') != $post->gallery_id) {
                    $copyGalleryResult = null;
                    $gallery = $this->copyGalleryFromRequest($copyGalleryResult);
                    $selectedPosterImage = data_get($copyGalleryResult, 'images.' . $request->input('poster_image'));

                    if (!!$selectedPosterImage) {
                        $posterImageId = $selectedPosterImage->id;
                    }
                }
            }
            else if (is_array($request->input('image_data')) || is_array($request->input('remove_images'))) {
                $gallery = $post->gallery ?:  Gallery::create([
                    'name' => $post->title,
                    'user_id' => Auth::id()
                ]);

                $result = $this->handleImageUploads($gallery, true);

                if (!!count($result['images']) && is_array(data_get($result, 'meta.original_indexes')) && !!count(data_get($result, 'meta.original_indexes'))) {
                    if ($request->input('poster_image') || is_numeric($request->input('poster_image'))) {
                        $posterImageId = array_search($request->input('poster_image'), data_get($result, 'meta.original_indexes'));
                    }
                }
            }

            if (!!$gallery || !!$post->gallery_id && !$posterImageId && intval($request->input('poster_image'))) {
                if (!!$gallery) {
                    $post->gallery_id = $gallery->id;
                    $post->save();
                }
                else if (!!$post->gallery_id && !$posterImageId && intval($request->input('poster_image'))) {
                    $image = $post->gallery->images
                        ->where('id', $request->input('poster_image'))
                        ->first();

                    if (!!$image) {
                        $posterImageId = $image->id;
                    }
                }

                if (!!$posterImageId) {
                    $post->setMeta('poster_image', $posterImageId);
                }
            }

            $post->load('gallery');

            return response()->json($post->refresh());
        }
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
                'name' => request()->input('title'),
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
