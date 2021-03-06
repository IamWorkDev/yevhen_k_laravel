<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 08.10.18
 * Time: 12:50
 */

namespace App\Services;

use App\Banner;
use App\Country;
use App\File;
use App\ForumSection;
use App\ForumTopic;
use App\GameVersion;
use App\InterviewQuestion;
use App\Replay;
use App\ReplayMap;
use App\ReplayType;
use App\User;
use App\UserGallery;
use App\UserMessage;
use App\UserRole;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class GeneralViewHelper
{
    protected $last_forum;
    protected $last_forum_home;
    protected $last_gosu_replay;
    protected $last_user_replay;
    protected $countries;
    protected $user_roles;
    protected $bd_users;
    protected $all_sections;
    protected $replay_type;
    protected $general_sections;
    protected $replay_maps;
    protected $banner;
    protected $question;
    protected $new_users;
    protected $game_version;
    protected $user_gallery;
    protected static $instance;

    public function __construct()
    {
        if (!self::$instance) {
            self::$instance = $this;
        }
    }

    /**
     * Get random user gallery images
     *
     * @return array
     */
    public function getRandomImg()
    {
        $data_img = UserGallery::orderBy('created_at', 'desc')->limit(5000)->get(['id'])->toArray();
        $random_img_ids = $data_img ? array_rand($data_img, (count($data_img) > 4 ? 4 : count($data_img))) : [];
        $random_img = [];
        foreach ($random_img_ids as $item) {
            $data = $data_img[$item]['id'];
            $random_img[] = $data;
        }
        $random_img = UserGallery::whereIn('id', $random_img)->with('file')->get()->toArray();

        return $random_img;
    }

    /**
     * Get pandom question for user
     *
     * @return mixed
     */
    public function getRandomQuestion()
    {
        self::$instance->question = self::$instance->question ?? InterviewQuestion::getRandomQuestion();
        return self::$instance->question;
    }

    /**
     * Get last 5 topics for general sections
     *
     * @return mixed
     */
    public function getLastForum()
    {
        if (!self::$instance->last_forum) {
            if (!self::$instance->all_sections) {
                self::$instance->getAllForumSections();
            }

            self::$instance->last_forum = self::$instance->all_sections->where('is_general', 1);
        }

        return self::$instance->last_forum;
    }

    /**
     * Get last 5 GOSU replays
     *
     * @return mixed
     */
    public function getLastGosuReplay()
    {
        if (!self::$instance->last_gosu_replay) {
            self::$instance->last_gosu_replay = Replay::gosuReplay()->where('approved', 1)->orderBy('created_at',
                'desc')->limit(5)->get();
            self::$instance->last_gosu_replay->load('map');
        }

        return self::$instance->last_gosu_replay;
    }

    /**
     * Get last 5 users replays
     *
     * @return mixed
     */
    public function getLastUserReplay()
    {
        self::$instance->last_user_replay = self::$instance->last_user_replay ?? Replay::userReplay()->where('approved',
                1)->orderBy('created_at', 'desc')->limit(5)->get();
        return self::$instance->last_user_replay;
    }

    /**
     * Get count of not reading user message
     *
     * @return int
     */
    public function getNewUserMessage()
    {
        $new_user_message = 0;

        if (Auth::user()) {
            $new_user_message = UserMessage::whereHas('dialogue.users', function ($query) {
                $query->where('users.id', Auth::id());
            })->where('user_id', '<>', Auth::id())->where('is_read', 0)->count();
        }

        return $new_user_message;
    }

    /**
     * Get last 10 registered users
     *
     * @return mixed
     */
    public function getNewUsers()
    {
        $new_users = $new_users ?? User::where('is_ban', 0)->orderBy('created_at', 'desc')->limit(10)->get();
        return $new_users;
    }

    /**
     * Get all countries
     *
     * @return mixed
     */
    public function getCountries()
    {
        if (!self::$instance->countries) {
            $countries = Country::all();

            foreach ($countries as $country) {
                self::$instance->countries[$country->id] = $country;
            }
        }

        return self::$instance->countries;
    }

    /**
     * Get all user roles
     *
     * @return UserRole[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getUsersRole()
    {
        self::$instance->user_roles = self::$instance->user_roles ?? UserRole::all();
        return self::$instance->user_roles;
    }

    /**
     * Get user who has birthday today
     *
     * @return mixed
     */
    public function getBirthdayUsers()
    {
        self::$instance->bd_users = self::$instance->bd_users ?? User::where('birthday', 'like',
                "%" . Carbon::now()->format('m-d'))->get();
        return self::$instance->bd_users;
    }

    /**
     * Get all forum sections
     *
     * @return ForumSection[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getAllForumSections()
    {
        if (!$this->all_sections) {
            $all_sections = ForumSection::active()->get();
            $time = Carbon::now()->format('Y-M-d');
            $sql = [];
            foreach ($all_sections as $section) {
                $sql[] = "(
        select * from `forum_topics` where `approved` = 1 and (`start_on` is null or `start_on` <= '$time') and `section_id` = $section->id ORDER BY `commented_at` DESC limit 5
        )";
            }

            $sql = implode(" UNION ALL ", $sql);
            $topics = collect(\DB::select($sql))->groupBy('section_id');

            foreach ($all_sections as $key => $section) {
                $all_sections[$key]->topics = $topics[$section->id];
            }

            $this->all_sections = $all_sections;
        }

        return self::$instance->all_sections;
    }

    /**
     * Get all replay types
     *
     * @return ReplayType[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getReplayTypes()
    {
        if (!self::$instance->replay_type) {
            $types = ReplayType::all();

            foreach ($types as $type) {
                self::$instance->replay_type[$type->id] = $type;
            }
        }

        return self::$instance->replay_type;
    }
}