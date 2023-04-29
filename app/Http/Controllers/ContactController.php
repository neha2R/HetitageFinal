<?php

namespace App\Http\Controllers;

use App\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\User;
use Carbon\Carbon;
use App\Challange;
use App\AgeGroup;
use App\BlockUser;
use App\FireBaseNotification;

use Illuminate\Support\Facades\Crypt;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Contact  $contact
     * @return \Illuminate\Http\Response
     */
    public function show(Contact $contact)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Contact  $contact
     * @return \Illuminate\Http\Response
     */
    public function edit(Contact $contact)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Contact  $contact
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Contact $contact)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Contact  $contact
     * @return \Illuminate\Http\Response
     */
    public function destroy(Contact $contact)
    {
        //
    }


    // ======= API function start here

    /** 
     * Add to contact of user using mobile contact
     *
     * @param  \App\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import_contact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'mobiles' => 'required|json',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }

        $mobiles = json_decode($request->mobiles);

        $userDatas = User::whereIn('mobile', $mobiles)->where('type', '2')->get();

        $data = [];

        if ($userDatas->count() > 0) {
            foreach ($userDatas as $userData) {

                $data[] = $userData->mobile;
            }
            if (empty($data)) {
                return response()->json(['status' => 201, 'data' => $data, 'message' => 'No new user found']);
            } else {
                return response()->json(['status' => 200, 'data' => $data, 'message' => 'User found']);
            }
        } else {
            return response()->json(['status' => 201, 'data' => $data, 'message' => 'User not found']);
        }
    }



    /** 
     * Get all contact of user
     *
     * @param  \App\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function fetchContacts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }
        $id = $request->user_id;
        $totalfiends = Contact::where('friend_one', $id)->where('status', '1')->pluck('friend_two')->toArray();
       
        $whoinvited = Contact::where('friend_two', $id)->where('status', '1')->pluck('friend_one')->toArray();
        $toaluser = array_unique(array_merge($totalfiends, $whoinvited));
    
        $blockuser = BlockUser::where('blocked_by', $id)->pluck('blocked_to')->toArray();
        $onlyfriends = array_diff($toaluser, $blockuser);
        $users = User::whereIn('id', $onlyfriends)->get();
        $data = [];
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
        if (empty($data)) {
            return response()->json(['status' => 201, 'data' => $data, 'message' => 'No  user found']);
        } else {
            return response()->json(['status' => 200, 'data' => $data, 'message' => 'All your contact list']);
        }
    }


    /** 
     * Get all Block User
     *
     * @param  \App\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function blockUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }
        $id = $request->user_id;
        $blockuser = BlockUser::where('blocked_by', $id)->pluck('blocked_to')->toArray();
        $users = User::whereIn('id', $blockuser)->get();
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
        if (empty($data)) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'No  user found']);
        } else {
            return response()->json(['status' => 200, 'data' => $data, 'message' => 'All your contact list']);
        }
    }

    /** 
     * Block user 
     *
     * @param  \App\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function blockAUser(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'block_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }
        $savedata = new BlockUser;
        $savedata->blocked_by = $request->user_id;
        $savedata->blocked_to = $request->block_id;
        $savedata->save();
        return response()->json(['status' => 200, 'data' => [], 'message' => 'User blocked succesfully']);
    }

    /** 
     * Block user 
     *
     * @param  \App\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function unblockUser(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'block_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }
        $savedata =  BlockUser::where('blocked_by', $request->user_id)->where('blocked_to', $request->block_id)->first();
        if ($savedata) {
            $savedata->delete();
            return response()->json(['status' => 200, 'data' => [], 'message' => 'User un blocked succesfully']);
        } else {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'User not found']);
        }
    }


    /** 
     * Delete a user from friend List
     *
     * @param  \App\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function deleteUser(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'delete_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }

        $deleteUser = Contact::where('friend_one', $request->user_id)
            ->where('friend_two', $request->delete_id)->first();
            if(!isset($deleteUser)){
            $deleteUser = Contact::where('friend_two', $request->user_id)
                ->where('friend_one', $request->delete_id)->first();
            }
        
        if (empty($deleteUser)) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'No user found']);
        }
        $deleteUser->deleted_at = date('Y-m-d H:i:s');
        $deleteUser->save();

        return response()->json(['status' => 200, 'data' => [], 'message' => 'User Deleted succesfully']);
    }


    public function invite_contact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }
        $user_id = $request->user_id;
        // $user_id= Crypt::encryptString($user_id);

        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'User not found']);
        }
        if (isset($user->refrence_code)) {
            $code = $user->refrence_code;
        } else {
            $code = mt_rand(111, 9999);

            $user->refrence_code = $code;
            $user->save();
        }
        $link = "cul.tre/invite#" . $code;
        return response()->json(['status' => 200, 'data' => $link, 'message' => 'Link generated']);
    }

    public function accept_link_invitation(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'link' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }
        $send_user = explode("#", $request->link);
        $user = User::where('refrence_code', $send_user[1])->first();
        if (!$user) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'Link is not valid']);
        }

        $oldFriend = Contact::where('friend_one', $user->id)->where('friend_two', $request->user_id)->first();
        if (!isset($oldFriend)) {
            $oldFriend = Contact::where('friend_one', $request->user_id)->where('friend_two', $user->id)->first();
        }
        if (!isset($oldFriend)) {
            $savedata = new Contact;
            $savedata->friend_one = $user->id;
            $savedata->friend_two = $request->user_id;
            $savedata->invited_via = 'link';
            $savedata->status = '1';
            $savedata->save();

            $data = [
                'title' => 'Request Accept.',
                'token' => $user->token,
                'message' => User::where('id', $request->user_id)->first()->name . " has accept your request",
            ];
            notify($data);

            $savenoti = new FireBaseNotification;
            $savenoti->user_id = $user->id;
            $savenoti->type = 'contact';
            $savenoti->message = User::where('id', $request->user_id)->first()->name . " has accept your request";
            $savenoti->title = 'Request Accept.';
            $savenoti->status = '0';
            $savenoti->save();
        } else {
            if ($oldFriend->status == '1') 
            {
                return response()->json(['status' => 201, 'data' => [], 'message' => 'Friend already added']);
            } 
            else 
            {

                $oldFriend->status = '1';
                $oldFriend->save();
                $data = [
                    'title' => 'Request Accept.',
                    'token' => $user->token,
                    'message' => User::where('id', $request->user_id)->first()->name . " has accept your request",
                ];
                notify($data);
                $savenoti = new FireBaseNotification;
                $savenoti->user_id = $user->id;
                $savenoti->type = 'contact';
                $savenoti->message = User::where('id', $request->user_id)->first()->name . " has accept your request";
                $savenoti->title = 'Request Accept.';
                $savenoti->status = '0';
                $savenoti->save();
                return response()->json(['status' => 200, 'data' => [], 'message' => 'User added to friend list']);
            }
        }

        // if (!$savedata) {
        //     return response()->json(['status' => 201, 'data' => [], 'message' => 'No new user found']);
        // } else {
        //     return response()->json(['status' => 200, 'data' => $savedata->id, 'message' => 'New user added to your friend list']);
        // }
    }

    public function add_friend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'mobile' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }
        $userData = User::where('mobile', $request->mobile)->where('type', '2')->first();
        if (!isset($userData)) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'User not found']);
        }
        $oldFriend = Contact::where('friend_one', $request->user_id)->where('friend_two', $userData->id)->first();
        if (!isset($oldFriend)) {
            $oldFriend = Contact::where('friend_one', $userData->id)->where('friend_two', $request->user_id)->first();
        }
        //    dd($oldFriend);
        if (!isset($oldFriend)) {
            $savedata = new Contact;
            $savedata->friend_one = $request->user_id;
            $savedata->friend_two = $userData->id;
            $savedata->invited_via = 'mobile';
            $savedata->status = '0';
            $savedata->count = 1;
            $savedata->save();

            $data = [
                'title' => 'Friend Request.',
                'token' => $userData->token,
                'type' => 'contact',
                'link' => $userData->refrence_code,
                'message' => User::where('id', $request->user_id)->first()->name . " has send you a friend request",
            ];
            sendNotification($data);
            // save notification 

            $savenoti = new FireBaseNotification;
            $savenoti->user_id = $userData->id;
            $savenoti->link = $userData->link;
            $savenoti->type = 'contact';
            $savenoti->message = User::where('id', $request->user_id)->first()->name . " has send you a friend request";
            $savenoti->title = 'Friend Request.';
            $savenoti->status = '0';
            $savenoti->save();

            return response()->json(['status' => 200, 'data' => [], 'message' => 'Request sent succesfully']);
        } else {
            $oldFriend->count = $oldFriend->count + 1;
            $oldFriend->save();
            return response()->json(['status' => 200, 'data' => [], 'message' => 'Already sent request']);
        }
    }

    public function check_friend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }
        $userData = User::find($request->user_id);
        if (!isset($userData)) {
            return response()->json(['status' => 201, 'data' => [], 'message' => 'User not found']);
        }
        $id = $request->user_id;
        $totalfiends = Contact::where('friend_one', $id)->where('status', '1')->pluck('friend_two')->toArray();
        $whoinvited = Contact::where('friend_two', $id)->where('status', '1')->pluck('friend_one')->toArray();
        $toaluser = array_unique(array_merge($totalfiends, $whoinvited));
   
        $blockuser = BlockUser::where('blocked_by', $id)->pluck('blocked_to')->toArray();
        $onlyfriends = array_diff($toaluser, $blockuser);
        $users = User::whereIn('id', $toaluser)->get();
        $data = [];
        foreach ($users as $user) {
            if (isset($user->mobile)) {
                $response = $user->mobile;
                $data[] = $response;
            }
        }
        return response()->json(['status' => 200, 'data' => $data, 'message' => 'Data']);
    }


    // Reject invitation link

    public function reject_link_invitation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',

        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
        }
        if ($request->link) {
            $send_user = explode("#", $request->link);
            $user = User::where('refrence_code', $send_user[1])->first();
            if (!$user) {
                return response()->json(['status' => 201, 'data' => [], 'message' => 'Link is not valid']);
            }
            $oldFriend = Contact::where('friend_one', $user->id)->where('friend_two', $request->user_id)->where('status', '0')->first();
            if (!isset($oldFriend)) {
                $oldFriend = Contact::where('friend_one', $request->user_id)->where('friend_two', $user->id)->where('status', '0')->first();
            }
        } else {

            $validator = Validator::make($request->all(), [
                'id' => 'required',

            ]);
            if ($validator->fails()) {
                return response()->json(['status' => 422, 'data' => [], 'message' => $validator->errors()]);
            }
            $oldFriend = Contact::where('id', $request->id)->first();
        }


        $oldFriend->deleted_at = date('Y-m-d h:i:s');
        $oldFriend->status = '2'; // for reject a request
        $oldFriend->save();
        return response()->json(['status' => 200, 'data' => [], 'message' => 'Request rejected']);
    }
}
