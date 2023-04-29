<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Attempt;
use App\Challange;
use App\QuizDomain;
use App\AgeGroup;
use App\User;
use App\Contact;
use App\QuizSpeed;
use App\QuizType;
use App\Domain;
use App\FireBaseNotification;
use App\QuizRule;
use Carbon\Carbon;
use App\Jobs\SaveQuizRoomResult;
use App\Performance;
use App\Traits\NotificationToUser;
use App\BlockUser;
use App\CheckUserState;

class QuizRoomController extends Controller
{
    use NotificationToUser;

    //

    public function create_quiz_room(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'difficulty_level_id' => 'required',
            'quiz_speed_id' => 'required',
            'domains' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        if (!age_group_by_user($request->user_id)) {
            return response()->json(['status' => 201, 'data' => '', 'message' => 'Age group not found..']);
        }
        $quiz_type = QuizType::where('name', 'like', '%Quiz Room%')->latest()->first();

        if (empty($quiz_type)) {
            return response()->json(['status' => 204, 'message' => 'Quiz Room type quiz not found', 'data' => array()]);
        }
        $oldquizcheck = Attempt::where('user_id', $request->user_id)->where('quiz_type_id', 3)->where('end_at', null)->latest()->first();

        if ($oldquizcheck) {
            if (Carbon::now()->parse($oldquizcheck->created_at)->diffInSeconds() < 600) {

                return response()->json(['status' => 201, 'message' => 'Wait for 600 sec', 'data' => array()]);
            }
        }
        $oldquizs = Attempt::where('user_id', $request->user_id)->where('quiz_type_id', 3)->where('end_at', null)->get();
        if ($oldquizs) {
            foreach ($oldquizs as $oldquiz) {
                if (Carbon::now()->parse($oldquiz->created_at)->diffInSeconds() > 600) {
                    $oldquiz->deleted_at = date('Y-m-d h:i:s');
                    $oldquiz->save();
                }
            }
        }
        $data = new Attempt;
        $data->user_id = $request->user_id;
        $data->quiz_type_id = $quiz_type->id;
        $data->difficulty_level_id = $request->difficulty_level_id;
        $data->quiz_speed_id = $request->quiz_speed_id;
        $data->save();

        // Create dual link
        $quiz_room = Attempt::where('id', $data->id)->first();
        $quiz_room->link = "cul.tre/quizroom#" . $data->id;
        $quiz_room->save();

        $domain = new QuizDomain;
        $domain->attempts_id = $data->id;
        $domain->domain_id = $request->domains;
        $domain->save();

        $room = [];
        $room['quiz_room'] = $data->id;
        $room['user'] = ucwords(strtolower($data->user->name));
        $domains = explode(',', $request->domains);
        $room['domain'] = Domain::select('id', 'name')->whereIn('id', $domains)->get()->toArray();
        $room['quiz_speed'] = ucwords(strtolower($data->quiz_speed->name));
        $room['difficulty'] = ucwords(strtolower($data->difficulty->name));
        $room['quiz_type'] = ucwords(strtolower($data->quiz_type->name));
        $room['created_date'] = date('d-M-Y', strtotime($data->created_at));
        $speed = QuizSpeed::find($request->quiz_speed_id);
        if ($speed->quiz_speed_type == 'single') {
            $room['time'] = $speed->no_of_question * $speed->duration;
        } else {
            $room['time'] = $speed->duration;
        }
        return response()->json(['status' => 200, 'message' => 'Quiz Romm quiz Created', 'data' => $room]);
    }

    public function quiz_rules(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quiz_room_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $data = Attempt::where('id', $request->quiz_room_id)->where('quiz_type_id', 3)->first();
        if (!$data) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'Quiz not found']);
        }


        $quiz_rules = QuizRule::select('scoring', 'negative_marking', 'time_limit', 'no_of_players', 'hint_guide', 'que_navigation', 'more')->where('quiz_type_id', 3)->where('quiz_speed_id', $data->quiz_speed_id)->where('status','1')->first();

        if (empty($quiz_rules)) {
            return response()->json(['status' => 204, 'message' => 'No rules found for the quiz', 'data' => []]);
        } else {
            // $data = json_decode($quiz_rules->more);
            $quiz_rules->more = json_decode($quiz_rules->more);
            $data = $quiz_rules->more;
            return response()->json(['status' => 200, 'message' => 'Data found succesfully', 'data' => $data]);
        }
    }

    public function disband_quiz(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quiz_room_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }

        $data = Attempt::where('id', $request->quiz_room_id)->where('quiz_type_id', 3)->first();
        if (!$data) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'Quiz not found']);
        }
        $data->deleted_at = date('Y-m-d h:i:s');
        $data->save();
        $userids = Challange::where('attempt_id', $request->quiz_room_id)->where('status', '1')->pluck('to_user_id')->toArray();
        $users = User::whereIn('id', $userids)->get();
        Challange::where('attempt_id', $request->quiz_room_id)->update(['deleted_at' => date('Y-m-d h:i:s')]);
        $this->disbandroom($users);
        $del = CheckUserState::whereIn('user_id', $userids)->get();
        if ($del->count() > 0) {
            $del->each->delete();
        }
        return response()->json(['status' => 200, 'message' => 'Quiz disbanded succesfully', 'data' => $data]);
    }

    public function send_invitation(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'from_id' => 'required',
            'quiz_room_id' => 'required',
            'to_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $challenge =   Challange::where('attempt_id', $req->quiz_room_id)
            ->where('from_user_id', $req->from_id)->where('to_user_id', $req->to_id)->first();
        $blockuser = BlockUser::where('blocked_by', $req->to_id)->where('blocked_to', $req->from_id)->first();
        if ($blockuser) {
            return response()->json(['status' => 201, 'data' => [], 'message' => "You can not send request to this user"]);
        }

        if ($challenge) {
            if (carbon::now()->parse($challenge->created_at)->diffInSeconds() < 60) {
                return response()->json(['status' => 201, 'data' => [], 'message' => 'Sorry! Wait for 60 sec or till accept the request.']);
            }
            // return response()->json(['status' => 422, 'data' => '', 'message' => "Sorry You have already sent this user request for the quiz room quiz."]);
        }
        else
        {
        $challange = Challange::where('attempt_id', $req->dual_id)->where('from_user_id', $req->from_id)
            ->where('to_user_id', $req->to_id)
            ->whereDate('created_at', carbon::now())->get()->count();
        if ($challange >= 3) {
            return response()->json(['status' => 422, 'data' => '', 'message' => "Sorry You can not send invitations to a single user more then 3 times in a day."]);
        } 
       }
        // else {
        $challange = new Challange;
        $challange->to_user_id = $req->to_id;
        $challange->from_user_id = $req->from_id;
        $challange->attempt_id = $req->quiz_room_id;
        $challange->status = '0';
        $challange->save();

        //notification

        $attempt = Attempt::where('id', $challange->attempt_id)->first();
        $data = [
            'title' => 'Invitation recived.',
            'token' => $challange->to_user->token,
            'link' => $attempt->link,
            'type' => 'quizroom',
            //   'from'=>$challange->from_user->name,
            'message' => 'You have a new request from' . ' ' . $challange->from_user->name,
        ];
        sendNotification($data);

        $savenoti = new FireBaseNotification;
        $savenoti->user_id = $challange->to_user->id;
        $savenoti->link = $attempt->link;
        $savenoti->type = 'quizroom';
        $savenoti->message = 'You have a new request from' . $challange->from_user->name;
        $savenoti->title = 'Quiz room Invitation send.';
        $savenoti->status = '0';
        $savenoti->save();

        return response()->json(['status' => 200, 'message' => 'Invitation Sent Successfully.']);
        // }
        // }

    }

    public function accept_invitation(Request $req)
    {

        $validator = Validator::make($req->all(), [
            'user_id' => 'required',
            'room_link' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $attempt = Attempt::where('link', $req->room_link)->first();

        if (empty($attempt)) {
            return response()->json(['status' => 204, 'message' => 'Sorry! Link has been expired. or not found']);
        }
        if (Challange::where('attempt_id', $attempt->id)->where('status', '1')->count() >= 10) {
            return response()->json(['status' => 422, 'data' => '', 'message' => 'Sorry Max limit of Quiz player exceed']);
        }
        $challenge = Challange::where('attempt_id', $attempt->id)->where('to_user_id', $req->user_id)->latest()->first();

        // If user come via link
        if (empty($challenge)) {
            $challenge = new Challange;
            $challenge->to_user_id = $req->user_id;
            $challenge->from_user_id = $attempt->user_id;
            $challenge->attempt_id = $attempt->id;
            $challenge->status = '1';
            $challenge->save();
            // return response()->json(['status' => 204, 'message' => 'Invitation not send yet to user']);
        }

        // if (carbon::now()->parse($challenge->created_at)->diffInSeconds() > 180) {
        //     return response()->json(['status' => 200, 'message' => 'Sorry! Invitation has been expired.']);
        // } else {
        $data = [
            'title' => 'Quiz Room Invitation accepted.',
            'token' => $challenge->from_user->token,
            'link' => $attempt->link,
            'type' => 'quizroom',
            'message' => User::where('id', $req->user_id)->first()->name . " has been accepted the request",
        ];
        // Create new data for user who accepts the request

        // $acceptuser = new Attempt;
        // $acceptuser->user_id = $req->user_id;
        // $acceptuser->parent_id = $attempt->id;
        // $acceptuser->difficulty_level_id = $attempt->difficulty_level_id;
        // $acceptuser->quiz_type_id = $attempt->quiz_type_id;
        // $acceptuser->quiz_speed_id = $attempt->quiz_speed_id;
        // $acceptuser->save();

        // Update challange table status to accepted
        $challenge->status = '1';
        $challenge->save();

        sendNotification($data);
        // Save notification
        $savenoti = new FireBaseNotification;
        $savenoti->user_id = $challenge->from_user->id;
        $savenoti->link = $attempt->link;
        $savenoti->type = 'quizroom';
        $savenoti->message = User::where('id', $req->user_id)->first()->name . " has been accepted the request. you can start quiz now";
        $savenoti->title = 'Quiz room Invitation accepted.';
        $savenoti->status = '0';
        $savenoti->save();
        $data = [
            ['user_id' => $req->user_id,],
            ['user_id' => $attempt->user_id,]
        ];
        CheckUserState::insert($data); 
        // $response['quiz_id'] = $acceptuser->id;

        return response()->json(['status' => 200, 'data' => $attempt->id, 'message' => 'Invitation Successfully accepted.']);
        // }
    }

    public function generate_link(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'user_id' => 'required',
            'room_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }


        if ($attempt = Attempt::where('user_id', $req->user_id)->where('id', $req->room_id)->first()) {

            $data = [];
            $data['link'] = $attempt->link;
            return response()->json(['status' => 200, 'message' => 'Generated Link', 'data' => $data]);
        } else {
            return response()->json(['status' => 200, 'message' => 'Sorry! No dual quiz found.']);
        }
    }

    public function room_user(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }

        $attempt = Attempt::where('id', $request->room_id)->first();
        if (!isset($attempt)) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'Quiz not found.']);
        }
        $userids = Challange::where('attempt_id', $attempt->id)->where('status', '1')->pluck('to_user_id')->toArray();
        $users = User::whereIn('id', $userids)->get();
        $data = [];


        // All user who accept invitation
        foreach ($users as $user) {
            $age = Carbon::parse($user->dob)->age;
            $allUsers['id'] = $user->id;
            $allUsers['name'] = ucwords(strtolower($user->name));

            if ($ageGroup = AgeGroup::where('from', '<=', $age)->where('to', '>=', $age)->first()) {
                $allUsers['age_group'] = ucwords(strtolower($ageGroup->name));
            } else {
                $allUsers['age_group'] = "";
            }
            if ($user->country) {
                $allUsers['country'] = $user->country->country_name->name;
                $allUsers['flag_icon'] = url('/flags') . '/' . strtolower($user->country->country_name->sortname) . ".png";
            } else {
                $allUsers['flag_icon'] = url('/flags/') . strtolower('in') . ".png";
            }
            $allUsers['status'] = "Online";
            if (isset($user->profile_image)) {
                $allUsers['image'] = url('/storage') . '/' . $user->profile_image;
            } else {
                $allUsers['image'] = '';
            }
            $data[] = $allUsers;
        }

        // user who creates quizroom
        $creator = User::find($attempt->user_id);
        $age = Carbon::parse($creator->dob)->age;
        $allUsers['id'] = $creator->id;
        $allUsers['name'] = ucwords(strtolower($creator->name));

        if ($ageGroup = AgeGroup::where('from', '<=', $age)->where('to', '>=', $age)->first()) {
            $allUsers['age_group'] = ucwords(strtolower($ageGroup->name));
        } else {
            $allUsers['age_group'] = "";
        }
        if ($creator->country) {
            $allUsers['country'] = $creator->country->country_name->name;
            $allUsers['flag_icon'] = url('/flags') . '/' . strtolower($creator->country->country_name->sortname) . ".png";
        } else {
            $allUsers['flag_icon'] = url('/flags/') . strtolower('in') . ".png";
        }
        $allUsers['status'] = "Online";
        if (isset($creator->profile_image)) {
            $allUsers['image'] = url('/storage') . '/' . $creator->profile_image;
        } else {
            $allUsers['image'] = '';
        }

        $data[] = $allUsers;
        return response()->json(['status' => 200, 'creator_id' => $creator->id, 'data' => $data, 'message' => 'Quiz room users list']);
    }


    public function delete_user_room(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'room_id' => 'required',
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {

            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }

        $attempt = Attempt::where('id', $request->room_id)->first();

        if (!isset($attempt)) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'Quiz not found.']);
        }
        $user = Challange::where('attempt_id', $attempt->id)->where('to_user_id', $request->user_id)->first();
        if (!isset($user)) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'User not in the quiz']);
        }
        $data = [
            'title' => 'User deleted.',
            'token' => $user->from_user->token,
            'link' => $attempt->link,
            'type' => 'quizroom',
            'message' => User::where('id', $request->user_id)->first()->name . " is no longer access the quiz",
        ];
        $user->delete();

        sendNotification($data);
        $relaseuser = CheckUserState::where('user_id', $request->user_id);
        ($relaseuser) ? $relaseuser->delete() : '';
        return response()->json(['status' => 200, 'data' => [], 'message' => 'User removed from room']);
    }

    public function leaveroom(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'room_id' => 'required',
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {

            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }

        $attempt = Attempt::where('id', $request->room_id)->first();

        if (!isset($attempt)) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'Quiz not found.']);
        }
        $user = Challange::where('attempt_id', $attempt->id)->where('to_user_id', $request->user_id)->first();
        if (!isset($user)) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'User not in the quiz']);
        }
        $user->delete();
        $relaseuser = CheckUserState::where('user_id', $request->user_id);
        ($relaseuser) ? $relaseuser->delete() : '';
        return response()->json(['status' => 200, 'data' => [], 'message' => 'User removed from room']);
    }


    public function save_room_result(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quiz_id' => 'required',
            'user_id' => 'required',
            'quiz_answer' => 'required',
        ]);

        if ($validator->fails()) {

            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }

        $quiz = Attempt::find($request->quiz_id);
        if (!empty($quiz)) {

            if ($quiz->user_id != $request->user_id) {
                // Check if user register with room
                $user = Challange::where('attempt_id', $quiz->id)->where('to_user_id', $request->user_id)->where('status', '1')->first();
                if (empty($user)) {
                    return response()->json(['status' => 201, 'data' => [], 'message' => 'Not a valid user']);
                }
                $userquiz = Attempt::where('parent_id', $request->quiz_id)->where('user_id', $request->user_id)->first();
                if (empty($userquiz)) {
                    $userquiz = new Attempt;
                    $userquiz->user_id = $request->user_id;
                    $userquiz->parent_id = $quiz->id;
                    $userquiz->difficulty_level_id = $quiz->difficulty_level_id;
                    $userquiz->quiz_type_id = $quiz->quiz_type_id;
                    $userquiz->quiz_speed_id = $quiz->quiz_speed_id;
                    $userquiz->save();
                }
                $quiz = $userquiz;
            }

            $alreadysave = Performance::where('attempt_id', $quiz->id)->get('question_id');
            $res = [];

            if ($alreadysave->isEmpty()) {

                $data = SaveQuizRoomResult::dispatchNow($request->all());
            } else {
                $data = 'success';
            }

            if ($data == 'error') {
                return response()->json(['status' => 202, 'message' => 'Quiz not found', 'data' => '']);
            }
            if ($data == 'success') {
                $quiz = Attempt::find($request->quiz_id);
                if ($quiz->user_id != $request->user_id) {
                    $quiz = Attempt::where('parent_id', $request->quiz_id)->where('user_id', $request->user_id)->first();
                }
                $res['quiz_id'] = $request->quiz_id;
                $res['xp'] = $quiz->xp;
                $res['per'] = $quiz->result;
                if (isset($quiz->user->profile_image)) {
                    $user['image']  = url('/storage') . '/' . $quiz->user->profile_image;
                } else {
                    $user['image']  = '';
                }
                $relaseuser = CheckUserState::where('user_id', $request->user_id);
                ($relaseuser) ? $relaseuser->delete() : '';
                return response()->json(['status' => 200, 'message' => 'Result saved succesfully', 'data' => $res]);
            }
        } else {
            return response()->json(['status' => 202, 'message' => 'Quiz not found', 'data' => '']);
        }
    }

    public function reject_invitation(Request $req)
    {

        $validator = Validator::make($req->all(), [
            'user_id' => 'required',
            'room_link' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }


        $attempt = Attempt::where('link', $req->room_link)->first();
        if (empty($attempt)) {
            return response()->json(['status' => 204, 'data' => [], 'message' => 'Sorry! Link has been expired. or not found']);
        }
        $challenge = Challange::where('attempt_id', $attempt->id)->where('to_user_id', $req->user_id)->latest()->first();

        if (empty($challenge)) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'Sorry! No invitation']);
        } else {
            // $challenge->deleted_at = date('Y-m-d h:i:s');
            $challenge->status = '2';
            $challenge->save();


            $data = [
                'title' => 'Quiz room Invitation rejected.',
                'token' => $challenge->from_user->token,
                'link' => $attempt->link,
                'type' => 'quizroom',
                'message' => User::where('id', $req->user_id)->first()->name . " has been rejected the request",
            ];
            sendNotification($data);

            return response()->json(['status' => 200, 'data' => [], 'message' => 'Rejected succesfully']);
        }
    }

    public function get_room_result(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required',
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422,  'message' => $validator->errors()]);
        }
        $user_data = Attempt::where('id', $request->room_id)->first();
        if (isset($user_data)) {
            if ($user_data->user_id == $request->user_id) {
                
            } else {
                $user_data =
                    Attempt::where('parent_id', $request->room_id)->where('user_id', $request->user_id)->first();
            }
            $user = [];
            $user['user_id'] = $user_data->user_id;
            $user['name'] = $user_data->user->name;
            $user['xp'] = $user_data->xp;
            $user['percentage'] = $user_data->result;
            if (isset($user_data->user->profile_image)) {
                $user['image']  = url('/storage') . '/' . $user_data->user->profile_image;
            } else {
                $user['image']  = '';
            }
            $totalusers = Attempt::where('id', $request->room_id)->orWhere('parent_id', $request->room_id)->orderBy('marks', 'DESC')->get();
            // if ($totalusers->count() < 3) {
            //     return response()->json(['status' => 201,  'message' => 'waiting...']);
            // }
            $res = [];
            $i = 1;

            foreach ($totalusers as $totaluser) {
                $other['user_id'] = $totaluser->user_id;
                $other['name'] = $totaluser->user->name;
                $other['marks'] = $totaluser->marks;
                $other['rank'] = $i;
                $other['xp'] = $totaluser->xp;
                $other['percentage'] = $totaluser->result;
                if (isset($totaluser->user->profile_image)) {
                    $other['image']  = url('/storage') . '/' . $totaluser->user->profile_image;
                } else {
                    $other['image']  = '';
                }
                $res[] = $other;
                $i++;
            }
            return response()->json(['status' => 200, 'user_data' => $user, 'result' => $res, 'message' => 'Quiz room data']);
        } else {
            return response()->json(['status' => 201,  'message' => 'Quiz not find']);
        }
    }

    public function room_status(Request $request)
    {
        // 0=> created
        // 1=> Started
        // 2=> Deleted
        // user id for check if he has access to quiz
        $validator = Validator::make($request->all(), [
            'room_id' => 'required',
        ]);
        $data1 = 0;
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => $data1, 'message' => $validator->errors()]);
        }
        $user = Challange::where('attempt_id', $request->room_id)->where('to_user_id', $request->user_id)->first();
        if (!isset($user)) {
            return response()->json(['status' => 200, 'data' => $data1, 'message' => 'User not in the quiz']);
        }
        $data = Attempt::where('id', $request->room_id)->first();
        if ($data) {
            if ($data->started_at) {
                $data1 = 1;
                return response()->json(['status' => 200, 'data' => $data1, 'message' => 'Quiz started..']);
            } else {
                return response()->json(['status' => 200, 'data' => $data1, 'message' => 'Quiz not started yet']);
            }
        } else {
            $data1 = 2;
            return response()->json(['status' => 201, 'data' => $data1, 'message' => 'Quiz room not find']);
        }
    }

    public function start_room(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422,  'message' => $validator->errors()]);
        }
        $data = Attempt::where('id', $request->room_id)->first();
        if ($data) {
            $userids = Challange::where('attempt_id', $data->id)->where('status', '1')->pluck('to_user_id')->toArray();
            $users = User::whereIn('id', $userids)->get();
            $this->startroom($users, $request->room_id);
            $data->started_at = date('Y-m-d H:i:s');
            $data->save();
            return response()->json(['status' => 200,  'message' => 'Quiz started succesfully']);
        } else {
            return response()->json(['status' => 201,  'message' => 'Quiz room not find']);
        }
    }

    public function roomrank(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'room_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }

        $data = Attempt::where('id', $request->room_id)->where('quiz_type_id', '3')->first();
        if (!$data) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'Quiz not found']);
        }

        $totalusers = Attempt::where('id', $request->room_id)->orWhere('parent_id', $request->room_id)->orderBy('marks', 'ASC')->get();
        $challenegaccept = Challange::where('attempt_id',$request->room_id)->where('status', '1')->get();
        // dd($totalusers);
        $count = 0;
        foreach ($totalusers as $checsubmit) {
            if ($checsubmit->end_at != null) {
                $count++;
            }
        }
        $rankdata = [];
        $quizspeed = QuizSpeed::find($data->quiz_speed_id);
        if ($quizspeed->quiz_speed_type == 'single') {
            $totaltime = $quizspeed->no_of_question * $quizspeed->duration;
        } else {
            $totaltime = $quizspeed->duration;
        }

        $totaltime = $totaltime + 45; // Delay of 45 seconds
        //    dd($totaltime);
        if ($data->started_at) {
            $endtime =  Carbon::parse($data->started_at)
                ->addSeconds($totaltime)
                ->format('Y-m-d H:i:s');
        } else {

            $endtime =  Carbon::parse($data->created_at)
                ->addSeconds($totaltime)
                ->format('Y-m-d H:i:s');
        }
      
          // count+1 for add user who create the quiz
        if ($count == $challenegaccept->count()+1) {
        
            foreach ($totalusers as $user) {
                $rankdata[$user->user_id] = $user->marks;
                arsort($rankdata);
            }
        } else {

            $nowDate = Carbon::now();
            $result = $nowDate->gt($endtime);
            if ($result) {
                foreach ($totalusers as $user) {
                    $rankdata[$user->user_id] = $user->marks;
                    arsort($rankdata);
                }
            }
        }

        if ($rankdata) {
            $rank = 1;
            $myrank = 0;
            $message = '';
            $olddata = '';
           
            foreach ($rankdata as $key => $rankdat) {

                if ($key == $request->user_id) {
                    $myrank = $rank;
                    if ($myrank == 1) {
                        $message = 'You won the group quiz!';
                    }
                    if ($myrank == 2) {
                        $message = 'You are the first runner up!.';
                    }
                    if ($myrank == 3) {
                        $message = 'You are the second runner up!.';
                    }
                }
                if ($olddata != $rankdat) {
                    $rank++;
                }

                $olddata = $rankdata;
            }
            $userdata = User::find($request->user_id);
            $res = [];
            if ($myrank <=3) {
                $res['image']  = url('/storage') . '/' . $userdata->profile_image;
                $res['name']  = $userdata->name;
                $res['rank']  = $myrank;
                $res['message']  = $message;
            }
            // $endtime =
            //     Carbon::parse($endtime)
            //     ->format('H:i:s');

            if ($res) {
                return response()->json(['status' => 200, 'time' => $endtime, 'completed' => '1', 'data' => $res, 'message' => 'Rank Data']);
            } else {
                return response()->json(['status' => 200, 'time' => $endtime, 'completed' => '1',  'message' => 'Rank Data']);
            } 
                } else {
            return response()->json(['status' => 201, 'time' => $endtime, 'completed' => '0','message' => 'Result not calculated yet']);
        }
    }
}
