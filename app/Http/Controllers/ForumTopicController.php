<?php

namespace App\Http\Controllers;

use App\File;
use App\ForumSection;
use App\ForumTopic;
use App\Http\Requests\ForumTopicRebaseRequest;
use App\Http\Requests\ForumTopicStoreRequest;
use App\Http\Requests\ForumTopicUpdteRequest;
use App\User;
use App\UserReputation;
use Carbon\Carbon;
use foo\bar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumTopicController extends Controller
{
    /**
     * Display topic page
     *
     * @link /forum/topic/{id}
     * @method GET
     *
     * @param $id
     * @return \Illuminate\Contracts\View\Factory
     */
    public function index($id)
    {
        $topic = ForumTopic::where('id', $id)
            ->where(function ($q){
                $q->whereNull('start_on')
                    ->orWhere('start_on', '<=', Carbon::now()->format('Y-M-d'));
            })
            ->with(User::getUserWithReputationQuery())
            ->withCount( 'positive', 'negative', 'comments')
            ->with('icon')->first();

        if(!$topic){
            return abort(404);
        }

        $comments = $topic->comments()->with(User::getUserWithReputationQuery())->withCount('positive', 'negative')
            ->orderBy('created_at')->paginate(20);

        ForumTopic::where('id', $id)->update(['reviews' => $topic->reviews+1]);

        return view('forum.topic')->with([
                'topic' => $topic->load('section'),
                'comments' => $comments
            ]);
    }

    /**
     * Display form for create new topic
     *
     * @link /forum/topic/create
     * @method GET
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function create()
    {
        return view('forum.create_topic')->with('sections', ForumSection::all());

    }

    /**
     * Save new topic and redirect to it
     *
     * @link /forum/topic/store
     * @method POST
     *
     * @param ForumTopicStoreRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(ForumTopicStoreRequest $request)
    {
        $topic_data = $request->validated();

        $topic_data['user_id'] = Auth::id();
        $topic_data['commented_at'] = Carbon::now();

        if ($request->file('preview_img')){
            $title = 'Превью '.$request->has('title')?$request->get('title'):'';
            $file = File::storeFile($request->file('preview_img'), 'preview_img', $title);

            unset($topic_data['preview_img']);

            $topic_data['preview_file_id'] = $file->id;
        }

        $topic = ForumTopic::create($topic_data);

        return redirect()->route('forum.topic.index', ['id' => $topic->id]);
    }

    /**
     * Rebase topic to other section
     *
     * @link /forum/topic/{id}/rebase
     * @method POST
     *
     * @param ForumTopicRebaseRequest $request
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function rebase(ForumTopicRebaseRequest $request, $id)
    {
        $topic = ForumTopic::find($id);

        if (!$topic){
            return abort(404);
        }

        if ($topic->user_id != Auth::id()){
            return abort(403);
        }

        ForumTopic::where('id', $id)->update(['section_id' => $request->get('section_id')]);

        return redirect()->route('forum.topic.index', ['id' => $id]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @link /forum/topic/{id}/edit
     * @method GET
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $topic = ForumTopic::where('id', $id)->with('icon')->first();

        if(!$topic){
            return abort(404);
        }

        return view('forum.edit_topic', ['topic' => $topic->load('section'), 'sections' => ForumSection::where('is_active', 1)->get(['id', 'title','name'])]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @link /forum/topic/{id}/update
     * @method POST
     *
     * @param ForumTopicUpdteRequest $request
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(ForumTopicUpdteRequest $request, $id)
    {
        $topic = ForumTopic::find($id);

        if (!$topic){
            return abort(404);
        }

        $topic_data = [
            'title'=> $request->get('title'),
            'content'=> $request->get('content')
        ];

        if ($request->has('preview_content') && $request->get('preview_content') != ''){
            $topic_data['preview_content'] = $request->get('preview_content');
        } else {
            $topic_data['preview_content'] = null;
        }

        if ($request->has('start_on') && $request->get('start_on') != ''){
            $topic_data['start_on'] = $request->get('start_on');
        } else {
            $topic_data['start_on'] = null;
        }

        if ($request->file('preview_img')){
            if ($topic->preview_file_id){
                File::removeFile($topic->preview_file_id);
            }

            $title = 'Превью '.$request->has('title')?$request->get('title'):'';
            $file = File::storeFile($request->file('preview_img'), 'preview_img', $title);

            unset($topic_data['preview_img']);

            $topic_data['preview_file_id'] = $file->id;
        }

        ForumTopic::where('id', $id)->update($topic_data);

        return redirect()->route('forum.topic.index', ['id' => $id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @link /forum/topic/{id}/delete
     * @method GET
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $topic = ForumTopic::find($id);

        if (!$topic){
            return abort(404);
        }

        if ($topic->user_id != Auth::id()){
            return abort(403);
        }

        if ($topic->preview_file_id){
            File::removeFile($topic->preview_file_id);
        }

        $topic->comments()->delete();
        $topic->positive()->delete();
        $topic->negative()->delete();
        $topic->delete();

        return redirect()->route('forum.index');
    }

    /**
     * Get page with user topics
     *
     * @link /user/{id}/topic/
     * @method GET
     *
     * @link /forum/topic/my
     * @method GET
     *
     * @param int $user_id
     * @return mixed
     */
    public function getUserTopic($user_id = 0)
    {
        if ($user_id == 0){
            $user_id = Auth::id();
        }

        $data = ForumTopic::where('user_id',$user_id)
            ->withCount( 'positive', 'negative', 'comments')
            ->with('icon')
            ->has('sectionActive')
            ->with(['user'=> function($q){
            $q->withTrashed();
        }])
            ->where(function ($q){
                $q->whereNull('start_on')
                    ->orWhere('start_on','<=', Carbon::now()->format('Y-M-d'));
            })
            ->with(['comments' => function($query){
                $query->withCount('positive', 'negative')->orderBy('created_at', 'desc')->first();
            }])
            ->orderBy('created_at', 'desc')->paginate(20);

        return view('forum.my_topics')->with('topics', $data);
    }
}
