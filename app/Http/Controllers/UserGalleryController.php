<?php

namespace App\Http\Controllers;


use App\Comment;
use App\File;
use App\Http\Requests\UserGalleryStoreRequest;
use App\Http\Requests\UserGalleryUpdateRequest;
use App\IgnoreUser;
use App\UserGallery;
use App\UserReputation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class UserGalleryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @link /gallery/
     * @method GET
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('gallery.list')->with('photos', self::getList(new UserGallery()));
    }

    /**
     * Display a listing of the resource.
     *
     * @link /gallery/my
     * @method GET
     *
     * @link /gallery/user/{id}
     * @method GET
     *
     * @param int $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function indexUser($id = 0)
    {
        if ($id == 0){
            $id = Auth::id();
        }

        if (IgnoreUser::me_ignore($id)){
            return abort(403);
        }

        return view('gallery.list')->with('photos', self::getList(UserGallery::where('user_id',$id)));
    }

    /**
     * Get query result for user gallery
     *
     * @param $gallery
     * @return mixed
     */
    private static function getList($gallery)
    {
        return $gallery->with('file', 'user')->withCount( 'positive', 'negative', 'comments')->orderBy('created_at')->paginate(50);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @link /gallery/photo/create
     * @method GET
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('gallery.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @link /gallery/photo/store
     * @method POST
     *
     * @param UserGalleryStoreRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(UserGalleryStoreRequest $request)
    {
        $data = $request->validated();
        $data = UserGallery::saveImage($data);
        $data['user_id'] = Auth::id();

        $gallery = UserGallery::create($data);

        return redirect()->route('gallery.view', ['id' => $gallery->id]);
    }

    /**
     * Display the specified resource.
     *
     * @link /gallery/photo/{id}
     * @method GET
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $photo = UserGallery::find($id);

        if (IgnoreUser::me_ignore($photo->user_id)){
            return abort(403);
        }

        $photo = $photo->load('file', 'user');
        $photo->comments = Comment::where('relation', Comment::RELATION_USER_GALLERY)->where('object_id',$id)->withCount('positive', 'negative')->paginate(20);
        $photo->photo_next = UserGallery::where('user_id', $photo->user_id)->where('id', '>', $id)->orderBy('id', 'asc')->first();
        $photo->photo_before = UserGallery::where('user_id', $photo->user_id)->where('id', '<', $id)->orderBy('id', 'desc')->first();

        return view('gallery.photo')->with('photo', $photo);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @link /gallery/photo/{id}/edit
     * @method GET
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $photo = UserGallery::where('id', $id)->with('file')->first();

        if ($photo->user_id != Auth::id()){
            return abort(403);
        }

        return view('gallery.edit')->with('photo', $photo);
    }

    /**
     * Update the specified resource in storage.
     *
     * @link /gallery/photo/{id}/update
     * @method POST
     *
     * @param  UserGalleryUpdateRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UserGalleryUpdateRequest $request, $id)
    {
        $gallery = UserGallery::find($id);

        if($gallery){
            $gallery_data = $request->validated();

            if($request->has('image')){
                File::removeFile($gallery->file_id);
                $gallery_data = self::saveImage($gallery_data);
            }

            if ($request->has('content') && $request->get('content') === null){
                $gallery_data['content'] = '';
            }

            $gallery = UserGallery::where('id', $id)->update($gallery_data);

            return redirect()->route('gallery.view', ['id' => $id]);
        }

        return abort(404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @link /gallery/photo/{id}/delete
     * @method GET
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $gallery = UserGallery::find($id);

        if (!$gallery){
            return abort(404);
        }

        if ($gallery->user_id != Auth::id()){
            return abort(403);
        }

        $file = $gallery->file()->first();
        File::removeFile($file->id);

        $gallery->comments()->delete();
        $gallery->positive()->delete();
        $gallery->negative()->delete();
        $gallery->delete();

        return redirect()->route('gallery.list_my');
    }
}