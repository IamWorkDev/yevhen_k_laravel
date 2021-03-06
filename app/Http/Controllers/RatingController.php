<?php

namespace App\Http\Controllers;

use App\ForumTopic;
use App\Http\Requests\SetRatingRequest;
use App\IgnoreUser;
use App\User;
use App\UserReputation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Replay;


class RatingController extends Controller
{
    /**
     * Object relation
     *
     * @var string
     */
    protected $relation;

    /**
     * Model name
     *
     * @var string
     */
    protected $model;

    /**
     * Get rating
     *
     * @link /comment/{id}/get_rating/
     * @method GET
     *
     * @link /forum/topic/{id}/get_rating/
     * @method POST
     *
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getRating($id)
    {
        return $this->getRatingView(UserReputation::where('object_id', $id)->where('relation', $this->relation));
    }

    /**
     * Set rating
     *
     * @link /comment/{id}/set_rating/
     * @method GET
     *
     * @link /forum/topic/{id}/set_rating/
     * @method POST
     *
     * @param SetRatingRequest $request
     * @param $id
     * @return array
     */
    public function setRating(SetRatingRequest $request, $id)
    {
        $object = ($this->model)::find($id);

        if (IgnoreUser::me_ignore($object->user_id)){
            return abort(403);
        }

        $comment = self::getComment($request);

        if($object){
            UserReputation::updateOrCreate(
                ['sender_id' => Auth::id(), 'recipient_id' => $object->user_id, 'object_id' => $object->id, 'relation' => $this->relation],
                ['comment' => $comment, 'rating'=>  $request->get('rating')]
            );

            return ['rating' => self::getRatingValue($object)];
        }

        return abort(404);
    }

    /**
     * Get comment value
     *
     * @param Request $request
     * @return mixed|null
     */
    protected static function getComment(Request $request)
    {
        $comment = null;

        if($request->has('comment')){
            $comment = $request->get('comment');
        }
        return $comment;
    }

    /**
     * Get calculation of rating value
     *
     * @param $object
     * @return mixed
     */
    protected static function getRatingValue($object)
    {
        return $object->positive()->count()-$object->negative()->count();
    }
    /**
     * Get reputation of User
     *
     * @link /user/{id}/get_rating/
     * @method GET
     *
     * @param $id
     * @return mixed
     */
    public function getRatingUser($id)
    {
        return $this->getRatingView(UserReputation::where('recipient_id', $id), $id);
    }

    /**
     * Get view with rating list
     *
     * @param UserReputation $user_reputation
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    protected function getRatingView($user_reputation, $id)
    {
        $list = $user_reputation->with('sender')->paginate(20);
        $user = User::where('id',$id)->first();
        return view('reputation_list')->with(['list'=>$list, 'user' => $user] );
    }
}