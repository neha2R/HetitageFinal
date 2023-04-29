<?php

namespace App\Http\Controllers;

use App\Mail\Setotp;
use App\Unverified;
use App\User;
use App\ForgetPasswords;
use App\Mail\ForgetPassword;
use App\AgeGroup;
use App\CheckUserState;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class UserController extends Controller
{
    public $successStatus = 200;
    // api authentication things

    /**
     * User login for android.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function generateRandomString($length = 10)
    {
        return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
    }
    public function login(Request $request)
    {
        if ($request->is_social) {
            
            //for 3 --> twitter, 2--> facebook, 1-->google

            if ($request->is_social == 1) {

                $user = User::where('email', '=', request('email'))->first();
                $data = [];
                $data = json_encode($data, JSON_FORCE_OBJECT);
                if (empty($user)) {
                    $user = new User;
                    $user->name = '';
                    $user->email = $request->email; 
                    // $userdata ->password = $user->password;
                    // $userdata->username = $user->username;
                    $user->is_social = '1';
                    $user->email_verified_at = date('Y-m-d H:i:s');
                    $user->save();
                }
            }
            // Check for Facebook login 
            if ($request->is_social == 2) {
                $validator = Validator::make($request->all(), [
                    'social_id' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
                }
                $user = User::where('app_id', '=', request('social_id'))->first();
               
                $data = [];
                $data = json_encode($data, JSON_FORCE_OBJECT);
                if (empty($user)) {
                    $user = new User;
                    $user->name = '';
                     $user->email = $request->email;
                    // $userdata->password = $user->password;
                    $user->username = $request->username;
                    $user->app_id = $request->social_id;
                    $user->is_social = '2';
                    // $user->email_verified_at = date('Y-m-d H:i:s');
                    $user->save();
                }
            }
            // Check for Twitter login 
            if ($request->is_social == 3) {
                $validator = Validator::make($request->all(), [
                    'social_id' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
                }
                $user = User::where('app_id', '=', request('social_id'))->first();
                $data = [];
                $data = json_encode($data, JSON_FORCE_OBJECT);
                if (empty($user)) {
                    $user = new User;
                    $user->name = '';
                     $user->email = $request->email;
                    // $userdata->password = $user->password;
                    $user->username = $request->username;
                    $user->app_id = $request->social_id;
                    $user->is_social = '3';
                    // $user->email_verified_at = date('Y-m-d H:i:s');
                    $user->save();
                }
            }
            // if ($user->password != null) {
            //     return response()->json(['status' => 202, 'message' => "Email is invalid.", 'data' => '']);
            // }

            if (Auth::loginUsingId($user->id)) {
                $user = Auth::user();



                if ($user->profile_complete == 0) {
                    return response()->json(['status' => 200, 'user_id' => $user->id, 'profile_complete' => '0', 'message' => "Your profile is not completed", 'data' => $data]);
                }

                $age = carbon::now()->parse($user->dob)->age;

                if ($group = AgeGroup::where('from', '<=', $age)->where('to', '>=', $age)->first()) {
                    $group = $group->name;
                } else {
                    $group = "N/A";
                }

                if ($user->state_id != "") {
                    $country_name = $user->country->country_name->name;
                    $country_flag = url('/flags/' . strtolower($user->country->country_name->sortname) . ".png");
                }

                if($user->email){
                $token = $user->createToken($user->email)->plainTextToken;
                } else{
                    $token = $user->createToken($user->app_id)->plainTextToken; 
                }
                // $token = $this->generateRandomString();
                // $user->app_id = $request->app_id;
                // $user->save();

                if ($user->avatar) {
                    $user_avatar = Storage::url($user->avatar);
                } else {
                    $user_avatar = "http://via.placeholder.com/50X50";
                }

                return response()->json([
                    'status' => 200,
                    'message' => "Authenticated Successfully.",
                    'token' => $token,
                    'data' => $user,
                    'user_id' => $user->id,
                    'profile_complete' => $user->profile_complete,
                    'age_group' => ucwords(strtolower($group)),
                    'country' => $country_name,
                    'flag' => $country_flag,
                    'avatar' => $user_avatar
                ], 200);
            } else {
                $data = [];
                $data = json_encode($data, JSON_FORCE_OBJECT);
                return response()->json(['status' => 202, 'message' => "User not found.", 'data' => $data]);
            }
        } else {
            if (Auth::attempt(['email' => request('email'), 'password' => request('password')])) {
                $user = Auth::user();
                $token = $user->createToken($user->email)->plainTextToken;



                if ($user->profile_complete == 0) {
                    return response()->json([
                        'status' => 202, 'message' => "Your profile is not completed", 'profile_complete' => '0', 'token' => $token,
                        'data' => $user
                    ], 200);
                }

                $age = carbon::now()->parse($user->dob)->age;

                if ($group = AgeGroup::where('from', '<=', $age)->where('to', '>=', $age)->first()) {
                    $group = $group->name;
                } else {
                    $group = "N/A";
                }

                if ($user->state_id != "") {
                    $country_name = $user->country->country_name->name;
                    $country_flag = url('/flags/' . strtolower($user->country->country_name->sortname) . ".png");
                }
                return response()->json([
                    'status' => 200,
                    'message' => "Authenticated Successfully.",
                    'token' => $token,
                    'profile_complete' => $user->profile_complete,
                    'age_group' => $group,
                    'user_id' => $user->id,
                    'country' => $country_name,
                    'flag' => $country_flag,
                    'data' => $user
                ], 200);
            } else {
                return response()->json(['status' => 202, 'message' => "Email or password is invalid.", 'data' => ''], 200);
            }
        }
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'email' => 'required|email|unique:users',
            // 'username' => 'required|unique:users',
            'first_name' => 'required',
            'dob' => 'required',
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }

        $age = date_diff(date_create($request->dob), date_create('today'))->y;

        $user = User::find($request->user_id);
        $user->name = $request->first_name;
        // $user->email = $request->email;
        $user->last_name = $request->last_name;
        $user->age = $age;
        $user->dob = date('Y-m-d', strtotime($request->dob));
        // $user->password = bcrypt($request->password);
        $user->mobile = $request->mobile;
        $user->type = '2';
        $user->state_id = $request->state_id;
        $user->city_id = $request->city_id;
        $user->profile_complete = '1';
        $user->subscribe_newslater = $request->newsletter;

        $user->save();
        // if ($request->is_social == 1) {
        //     User::where('id', $user->id)->update(['is_social' => '1', 'email_verified_at' => date('Y-m-d H:i:s')]);
        //     // $user->otp = '';
        // }
        // else {
        //     $user->otp = '9876';
        // }

        // $user = $user->toArray();


        $age = carbon::now()->parse($user->dob)->age;

        if ($group = AgeGroup::where('from', '<=', $age)->where('to', '>=', $age)->first()) {
            $group = $group->name;
        } else {
            $group = "N/A";
        }

        if ($user->state_id != "") {
            $country_name = $user->country->country_name->name;
            $country_flag = url('/flags/' . strtolower($user->country->country_name->sortname) . ".png");
        }

        return response()->json([
            'status' => 200, 'profile_complete' => $user->profile_complete,
            'message' => 'User updated successfully', 'data' => $user, 'age_group' => $group, 'country' => $country_name, 'flag' => $country_flag
        ]);
    }

    public function stepone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'username' => 'unique:users',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $otp = rand(100000, 999999);
        $user = new Unverified;
        $user->email = $request->email;
        $user->username = $request->username;
        $user->password = bcrypt($request->password);
        $user->otp = $otp;
        $user->save();

        $user = $user->toArray();
        Mail::to($request->email)->send(new Setotp($otp));

        return response()->json(['status' => 200, 'message' => 'Please verify email', 'data' => $otp]);
    }

    public function email_verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            // 'otp' => 'required',
            'is_social' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        if ($request->is_social == 0) {
            $user = Unverified::where('email', $request->email)->where('otp', $request->otp)->first();
            if (empty($user)) {
                return response()->json(['status' => 204, 'message' => "Otp not verified.", 'data' => '']);
            } else {
                if ($user->otp != $request->otp) {
                    return response()->json(['status' => 200, 'message' => "Otp not verified.", 'data' => ''], 200);
                } else {
                    $userdata = new User;
                    $userdata->name = '';
                    $userdata->email = $user->email;
                    $userdata->password = $user->password;
                    $userdata->username = $user->username;
                    $userdata->dob = date('Y-m-d');
                    $userdata->email_verified_at = date('Y-m-d H:i:s');
                    $userdata->save();

                    $userdata = $userdata->toArray();
                }
            }
        } else {
            $userdata = new User;
            $userdata->name = '';
            $userdata->email = $request->email;
            $userdata->username = $request->email;
            $userdata->password = '';
            $userdata->dob = date('Y-m-d');
            $userdata->email_verified_at = date('Y-m-d H:i:s');
            $userdata->is_social = '1';
            $userdata->save();
            $userdata = $userdata->toArray();
        }
        return response()->json(['status' => 200, 'message' => 'Email verified succesfully', 'data' => $userdata]);
    }

    public function index()
    {
        $users = User::where('type', '2')->get();
        return view('users.list', compact('users'));
    }

    public function currentDateTime()
    {
        $currentDateTime = Carbon::now();
        $currentTime = $currentDateTime->toTimeString();
        $currentDate = $currentDateTime->toDateString();

        return response()->json(['status' => 200, 'message' => 'Domain data', 'currentTime' => $currentTime, 'currentDate' => $currentDate]);
    }


    public function profile(Request $request)
    {
        $user = User::find($request->user_id);

        $validator = Validator::make($request->all(), [
            // 'email' => 'required|email|unique:users',
            // 'mobile' => 'required|unique:users',
            // 'mobile' => 'required|unique:users,mobile,' . $user->id,
            'first_name' => 'required',
            'dob' => 'required',
            'user_id' => 'required',
            'state_id' => 'required',
            'city_id' => 'required',
            'gender' => 'required',
            'last_name' => 'required',


        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $image = null;
        if ($request->has('image')) {
            $file = $request->file('image');
            // $format = $file->extension();
            $patch = $file->store('images', 'public');
            $image = $patch;
        }


        $age = date_diff(date_create($request->dob), date_create('today'))->y;
        $user->name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->age = $age;
        $user->dob = date('Y-m-d', strtotime($request->dob));
        $user->mobile = $request->mobile;
        $user->state_id = $request->state_id;
        $user->city_id = $request->city_id;
        $user->profile_complete = '1';
        if ($image != null) {
            $user->profile_image = $image;
        }
        $user->gender = $request->gender;

        $user->save();


        $age = carbon::now()->parse($user->dob)->age;

        if ($group = AgeGroup::where('from', '<=', $age)->where('to', '>=', $age)->first()) {
            $group = $group->name;
        } else {
            $group = "N/A";
        }

        if ($user->state_id != "") {
            $country_name = $user->country->country_name->name;
            $country_flag = url('/flags/' . strtolower($user->country->country_name->sortname) . ".png");
        }

        return response()->json(['status' => 200, 'message' => 'Domain data', 'data' => $user, 'age_group' => $group, 'country' => $country_name, 'flag' => $country_flag]);
    }


    public function change_password(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'user_id' => 'required',
            'current_password' => 'required',
            'new_password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }

        $user = User::whereId($req->user_id)->first();
        if ($user) {
            if (Hash::check($req->current_password, $user->password)) {
                $user->password = bcrypt($req->new_password);
                $user->save();
                return response()->json(['status' => 200, 'data' => '', 'message' => "Your password has been updated successfully."]);
            } else {
                return response()->json(['status' => 422, 'data' => '', 'message' => "please check your current password."]);
            }
        } else {
            return response()->json(['status' => 422, 'data' => '', 'message' => "No user found."]);
        }
    }

    public function get_profile(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }

        $user = User::whereId($req->user_id)->first();
        $data = [];
        if ($user) {
            $data['first_name'] = $user->name;
            $data['last_name'] = $user->last_name;
            if (isset($user->profile_image)) {
                $data['image'] = url('/storage') . '/' . $user->profile_image;
            } else {
                $data['image'] = '';
            }
            $data['email'] = $user->email;
            $data['mobile'] = $user->mobile;
            $data['country'] = $user->state_id != "" ? \App\Country::whereId(\App\State::whereId($user->state_id)->first()->country_id)->first()->name : 'null';
            $data['country_id'] = $user->state_id != "" ? \App\State::whereId($user->state_id)->first()->country_id : 'N/A';
            $data['state'] = $user->state_id != "" ? \App\State::whereId($user->state_id)->first()->name : 'Null';
            $data['state_id'] = $user->state_id;
            $data['city'] = $user->city_id != "" ? \App\City::whereId($user->city_id)->first()->name : 'N/A';
            $data['city_id'] = $user->city_id;
            $data['gender'] = $user->gender;
            $data['dob'] = date('d-m-Y', strtotime($user->dob));

            return response()->json(['status' => 200, 'data' => $data, 'message' => "User profile."]);
        } else {
            return response()->json(['status' => 422, 'data' => '', 'message' => "No user found."]);
        }
    }

    public function forgetPassword(Request $req)
    {

        $validator = Validator::make($req->all(), [
            'email' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $users = User::where('email', $req->email)->first();

        if (!empty($users)) {
            // if($user=ForgetPasswords::where('user_id',$req->id)->whereDate('created_at',carbon::now())->first())
            // {
            //     $user->changed="0";
            //     $user->user_id=$users->id;
            //     $user->save();
            // }
            // else
            // {
            $data = new ForgetPasswords;
            $data->user_id = $users->id;
            $data->save();
            // }

            // Mail::to($request->email)->send(new Setotp($otp));

            Mail::to($users->email)->send(new ForgetPassword($users));
            return response()->json(['status' => 200, 'data' => '', 'message' => "Change password link has been sent to your email."]);
        } else {
            return response()->json(['status' => 422, 'data' => '', 'message' => "No User Found."]);
        }
    }
    public function change_passwords($id)
    {
        // $id=\Crypt::Decrypt($id);
        $token = $id;
        $user = ForgetPasswords::where('user_id', \Crypt::Decrypt($id))->first();
        if (empty($user)) {
            return view('auth.passwords.not_found')->with('error', 'Your email link has been expired. please apply for change password again through application.');
        } else {
            return view('auth.passwords.reset1', compact('token'));
        }
    }
    public function password_update(Request $req)
    {

        if ($req->password_confirmation != $req->password) {
            return redirect()->back()->with('error', 'Your password doesnot match. Try again!');
        } else {
            $user = ForgetPasswords::where('user_id', \Crypt::Decrypt($req->token))->whereDate('created_at', carbon::now())->first();
            if ($user) {
                //    if($user->changed=='1')
                //    {
                //      return redirect()->back()->with('error','Your email link has been expired. please apply for change password again through application.');

                //    }
                //    else{
                $user->changed = "1";
                $user->save();

                $data = User::whereId(\Crypt::Decrypt($req->token))->first();
                $data->password = bcrypt($req->password);
                $data->save();

                $user->delete();

                return redirect('success')->with('success', 'Your password has been changed successfully thanks!');
                // }

            } else {
                return redirect()->back()->with('error', 'Sorry change password link has been expired. Try again1!');
            }
        }
    }
    public function new_password(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'user_id' => 'required',
            'current_password' => 'required',
            'new_password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $user = User::whereId($req->user_id)->first();
        if (!$user) {
            return response()->json(['status' => 422, 'data' => '', 'message' => 'No user found']);
        }
        if (!Hash::check($req->current_password, $user->password)) {
            return response()->json(['status' => 422, 'data' => '', 'message' => 'Sorry your current password is incorrect.']);
        } else {
            $user->password = bcrypt($req->new_password);
            $user->save();
            return response()->json(['status' => 200, 'data' => '', 'message' => "Successfully password has been changed."]);
        }
    }

    /** 
     * Busy a user
     *
     * @param  \App\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function busyUser(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $savedata = new CheckUserState;
        $savedata->user_id = $request->user_id;
        $savedata->save();
        return response()->json(['status' => 200, 'data' => '', 'message' => 'User is busy']);
    }
    public function setpassword1(Request $req)
    {
        // $id=\Crypt::Decrypt($id);
        
$id=$req->id;
        $user = User::where('id',$req->id)->first();
       // dd($user);
        // $user = User::where('id','1');
   event(new \App\Events\RealTimeMessage('Hello World',$req->id));
    //  $user->notify(new \App\Notifications\RealTimeNotification('Hello World',$req->id));
     // return view('welcome', compact('id'));
    //  return redirect()->route('welcome', compact('id'));
    ////  return redirect()->back()->with(['welcome' => $id]);
    // Echo.private('events.1')
    // .listen('RealTimeMessage', (e) => console.log('RealTimeMessage: ' + e.message));    @endif 
    //   return ["echo-private:events.{$req->id}.listen('RealTimeMessage', (e) => console.log('RealTimeMessage: ' + e.message))"];


    }
    /** 
     * Delete a user from friend List // Free user
     *
     * @param  \App\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function freeUser(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $data = CheckUserState::where('user_id', $request->user_id)->get();

        if ($data->count() > 0) {
            $data->each->delete();
        }

        return response()->json(['status' => 200, 'data' => '', 'message' => 'User is free']);
    }

    public function updatetoken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $user = User::find($request->user_id);
        $user->token = $request->token;
        $user->device_id = $request->device_type;
        $user->save();
        return response()->json(['status' => 200, 'data' => [], 'message' => 'Token updated succesfully..']);
    }

    public function deleteaccount(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'data' => '', 'message' => $validator->errors()]);
        }
        $user = User::find($request->user_id);
        $user->name = 'Unknown'. $request->user_id;
        $user->last_name='Unknown';
        $user->email = 'unknown'. $request->user_id.'@unknown.com';
        $user->password = null;
        $user->dob = null;
        $user->mobile = null;
        $user->username = null;
        $user->profile_image = null;
        $user->age = null;
        $user->token = null;
        $user->app_id = null;
        // $user->is_social = null;
        $user->refrence_code = null;
        $user->is_deleted = date('Y-m-d H:i:s');
        $user->save();
        return response()->json(['status' => 200, 'data' => [], 'message' => 'Profile deleted succesfully..']);

    }

    public function logout(Request $request){
        $user = User::find($request->user_id);
        $user->token = null;
        $user->save();

        return response()->json(['status' => 200, 'data' => [], 'message' => 'Profile logout succesfully..']);

    }
}
