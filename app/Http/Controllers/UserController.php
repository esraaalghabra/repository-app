<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:sanctum', ['except' => ['login', 'register']]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request):JsonResponse
    {
        //validation data
        try {
            $validator = Validator::make($request->all(),
                [
                    'name' => 'required|string|max:255',
                    'email' => 'required|string|email|max:255|unique:users',
                    'password' => 'required|confirmed|string|min:6',
                ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            //create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'photo' => 'default_user.webp',
            ]);
            //generate token
            $token = $user->createToken(User::USER_TOKEN);
            $user->setRememberToken($token->plainTextToken);
            $user->save();
            //response success
            $data['id'] = $user->id;
            $data['remember_token'] = $token->plainTextToken;
            return $this->success($data, 'User has been register successfully');

        } catch (\Exception $e) {
            //response failure
            return $this->error('Server failure : ' . $e, 500);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request):JsonResponse
    {
        //validation data
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users',
            'password' => 'required|string|min:6|max:255',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        try {
            //get user by email
            $user = User::where('email', $request->email)->first();
            //check password
            if (Hash::check($request->password, $user->password)) {
                //generate token
                $token = $user->createToken(User::USER_TOKEN);
                $user->setRememberToken($token->plainTextToken);
                $user->save();
                //response success
                return $this->success($user, 'Login successfully');
            } else
                //response failure because password uncorrected
                return $this->error('password is not matched');
        } catch (\Exception $e) {
            //response failure because Server failure
            return $this->error('Server failure : ' . $e, 500);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function logout():JsonResponse
    {
        //expire token
        auth()->user()->currentAccessToken()->delete();
        //response success
        return $this->success(null, 'Logout successfully');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function addInfo(Request $request):JsonResponse
    {
        //validation data
        $validator = Validator::make($request->all(), [
            'photo' => 'mimes:jpg,jpeg,png,jfif',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        //get user by token
       $user = User::find($request->user()->id);
        try{
            //store photo in project and database
            if ($request->has('photo')) {
                $name = explode(' ', $user->name);
                $path = $request->file('photo')->storeAs('users', $name[0] . '.' . $request->file('photo')->extension(), 'images');
                $path = explode('/', $path);
                $user->update(['photo' => $path[1]]);
                $user->save();
            }
        }catch (\Exception $e){
            //response failure
            return $this->error('The photo unusable');
        }
        //response success
        return $this->success();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function addAdmin(Request $request):JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_admin' => 'required',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
       $user = User::find($request->user()->id);
        $user->update(['is_admin' => $request->is_admin]);
        $user->save();
        return $this->success();
    }


}
