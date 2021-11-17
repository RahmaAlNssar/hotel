<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ResponseTrait;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Mail\SendMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ResponseTrait;
    public function __construct() {
        $this->middleware('token.auth', ['except' => ['login', 'register','sendPasswordResetEmail','passwordResetProcess']]);
    }
    /**
     * Get a JWT via given credentials.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required',
        ],[
            'email.required'=>'الايميل مطلوب',
            'password.required'     =>'كلمة المرور مطلوبه',
        ]);

        if ($validator->fails()) {
            return $this->returnError($validator->errors()->toJson(), 400);
        }

        if (! $token = JWTAuth::attempt($validator->validated())) {
            return $this->returnError(['error' => __('auth.failed')], 400);
        }
       
        return $this->createNewToken($token);
    }

    /**
     * Register a User.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        
        $validator = Validator::make($request->all(), [
            'name'          => ['required', 'string', 'max:255'],
            'password'      => [
                'required',
                'string',
                'min:6',
                'confirmed',
                'regex:/[0-9]/',      // must contain at least one digit
            ],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,id'],
        ],
            [
                'name.required'     =>'الاسم مطلوب',
                'name.max' => 'يجب الا يزيد الاسم عن 255 حرف',
                'email.required'=>'الايميل مطلوب',
                'email.string' =>'يجب ان يكون الايميل عبرة عن أحرف',
                'email.max'   =>'أكبر عدد محارف يجب ألا يتجاوز ال 255 محرف',
                'email.unique' =>'هذا الايميل موجود بالفعل',
                'password.min' => 'يجب الا تقل كلمة المرور عن ثمانية حروف',
                'password.required'     =>'كلمة المرور مطلوبه',
                'password.confirmed' => 'كلمة المرور غير متطابقة',
            ]);
            // dd($validator->errors()->getMessages() );
        if($validator->fails()){
            $errors = [];
            foreach ($validator->errors()->getMessages() as $message) {
                $error = implode($message);
                $errors[] = $error;
            }
            return $this->returnError(implode(' , ', $errors), 400);
        }
        
    
     //  $token_api= Str::random(80);
    //  $token = JWTAuth::attempt($validator->validated());
        $user = User::create([
                  
            'role'              =>'api_user',
            'name'              => $request['name'],
            'password'          => Hash::make($request['password']),
            'email'             =>$request['email']
        ]);
      
        $token =  JWTAuth::attempt(['email' => $request['email'], 'password' => $request['password']]);

        $user->update([
            'token'             =>$token
        ]);
               // return  response()->json($token, 200);
        // return $this->returnData("Here is a valid token",
        // [
        //     'token' => $token,
        //     'token_type' => 'bearer',
    
        // ],
        // 200);
        return $this->createNewToken($token);

    }


    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        auth()->logout();
        return $this->returnSuccess('تم تسجيل الخروج بنجاح', 200);
    }

   
   

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return JsonResponse
     */
    protected function createNewToken(string $token): JsonResponse
    {
        $expire = auth('api')->factory()->getTTL();
        $expires_in = Carbon::now()->addSeconds($expire);
        return $this->returnData("Here is a valid token",
            [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $expires_in,
            ],
            200);
    }

    public function sendPasswordResetEmail(Request $request){
        // If email does not exist
        if(!$this->validEmail($request->email)) {
            return $this->returnError('الايميل غير موجود', 400);
        } else {
            // If email exists
           $this->sendMail($request->email);
            return $this->returnError(' تم ارسال الكود إلى بريدك الالكتروني لاعادة تعيين كلمة المرور ', 200);
           
        }
    }


    public function sendMail($email){
        $token = $this->generateToken($email);
        Mail::to($email)->send(new SendMail($token));
    }

    public function validEmail($email) {
       return User::where('email', $email)->first();
    }

    public function generateToken($email){
      $isOtherToken = DB::table('recover_password')->where('email', $email)->first();

      if($isOtherToken) {
        return $isOtherToken->token;
      }

      $token = Str::random(80);
      $this->storeToken($token, $email);
      return $token;
    }

    public function storeToken($token, $email){
        DB::table('recover_password')->insert([
            'email' => $email,
            'token' => $token,
            'created' => Carbon::now()            
        ]);
    }
    
    public function passwordResetProcess(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required',
        ],[
            'email.required'=>'الايميل مطلوب',
            'password.required'     =>'كلمة المرور مطلوبه',
        ]);

        if ($validator->fails()) {
            return $this->returnError($validator->errors()->toJson(), 400);
        }
        return $this->updatePasswordRow($request)->count() > 0 ? $this->resetPassword($request) : $this->tokenNotFoundError();
      }
  
      // Verify if token is valid
      private function updatePasswordRow($request){
         return DB::table('recover_password')->where([
             'email' => $request->email,
             'token' => $request->passwordToken
         ]);
      }
  
      // Token not found response
      private function tokenNotFoundError() {
      
          return $this->returnError(400,'يوجد خطأ بالايميل أو التوكين');
      }
  
      // Reset password
      private function resetPassword($request) {
          // find email
          $userData = User::whereEmail($request->email)->first();
          // update password
          $userData->update([
            'password'=>bcrypt($request->password)
          ]);
          // remove verification data from db
          $this->updatePasswordRow($request)->delete();
  
          // reset password response
          return $this->returnError(' تم اعادة تعيين كلمة المرور ', 200);
          
      }    
  
}
