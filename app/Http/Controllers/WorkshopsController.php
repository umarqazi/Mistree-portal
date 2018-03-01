<?php

namespace App\Http\Controllers;

use JWTAuth;
use Session;
use Hash, DB, Config, Mail, View;
use Illuminate\Support\Facades\Redirect;
use App\Workshop;
use App\WorkshopImages;
use App\Service;
use App\WorkshopAddress;
use App\WorkshopLedger;
use App\WorkshopBalance;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Password;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;

class WorkshopsController extends Controller
{
    /**
     * Fetching Guard.
     *
     * @return Auth::guard()
     */
    protected function guard()
    {
        return Auth::guard('workshop');
    }

    /**
     * Constructor.
     *
     * @param  \Illuminate\Contracts\Auth\Guard  $auth
     *
     * @return void
     */
    public function __construct()
    {
        $this->auth = app('auth')->guard('workshop');
    }

    /**
     * Display a listing of the workshop.
     *
     * @return \Illuminate\Http\Response
     */

    public function index()
    {
        // get all the workshops
        $workshops = Workshop::all();
        // load the view and pass the nerds
        return View::make('workshop.index')->with('workshops', $workshops);
    }

    /**
     * Show the form for creating a new workshop.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {   
        $services = Service::all();        
        return View::make('workshop.create', ['services' => $services]);
    }

    /**
     * Store a newly created workshop in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {                
        $rules = [
            'name'                           => 'required|regex:/^[\pL\s\-]+$/u',
            'owner_name'                     => 'required|regex:/^[\pL\s\-]+$/u',
            'email'                          => 'required|email|unique:workshops',
            'password'                       => 'required|confirmed|min:8',
            'password_confirmation'          => 'required',
            'cnic'                           => 'required|digits:13',
            'mobile'                         => 'required|digits:11',
            'landline'                       => 'digits:11',
            'open_time'                      => 'required',
            'close_time'                     => 'required',
            'type'                           => 'required',
            'profile_pic'                    => 'image|mimes:jpg,png',  
            'cnic_image'                     => 'image|mimes:jpg,png',  

            'shop'                           => 'required|numeric',
            'building'                       => 'regex:/^[\pL\s\-]+$/u',
            'block'                          => 'regex:/^[\pL\s\-]+$/u',
            'street'                         => 'required|string',
            'town'                           => 'required|regex:/^[\pL\s\-]+$/u',
            'city'                           => 'required|regex:/^[\pL\s\-]+$/u',
            'service_id.*'                   => 'required|integer:unique',
            'service_rate.*'                 => 'required|integer',
            'service_time.*'                 => 'required|alpha_dash' 
        ];        

        $input = $request->only('name', 'owner_name', 'email', 'password', 'password_confirmation', 'cnic', 'mobile', 'landline','open_time', 'close_time', 'type', 'shop', 'building', 'block', 'street', 'town', 'city');
        $validator = Validator::make($input, $rules);
        if($validator->fails()) {
            $request->offsetUnset('password');
            return Redirect::back()
                ->withErrors($validator)
                ->withInput(Input::except('password'));
        }

        //Insert Workshop data from request 
        $workshop = Workshop::create([
                                'name' => $request->name, 
                                'owner_name' => $request->owner_name ,
                                'email' => $request->email, 
                                'password' => Hash::make($request->password), 
                                'cnic' => $request->cnic, 
                                'mobile' => $request->mobile, 
                                'type' => $request->type,                                
                                'open_time' => $request->open_time, 
                                'close_time' => $request->close_time, 
                                'is_approved' => 1
                            ]); 

        //Insert Address data from request
        $address = WorkshopAddress::create([
                                        'shop' => $request->shop, 
                                        'building' => $request->building, 
                                        'street' => $request->street, 
                                        'block' => $request->block,
                                        'town' => $request->town, 
                                        'city' => $request->city, 
                                        'workshop_id' => $workshop->id, 
                                        'coordinates' => NULL
                                    ]);

        //Insert Services data from request        
        $service_ids = $request->service_id;
        $service_rates = $request->service_rate;
        $service_times = $request->service_time;    
        if(!empty($service_ids)){
            for($i = 0; $i<count($service_ids); $i++){            
                $workshop->services()->attach($service_ids[$i], ['service_rate' => $service_rates[$i] , 'service_time' => $service_times[$i] ]);
            }
        }

        $workshop_balance = new WorkshopBalance;        

        $workshop_balance->balance              = 2000;
        $workshop_balance->workshop_id          = $workshop->id;
        $workshop_balance->save();

        if ($request->hasFile('profile_pic')) 
        {
            // $ws_name = str_replace(' ', '_', $request->name);
            $s3_path =  Storage::disk('s3')->putFile('workshops/'. $workshop->id .'/logo', new File($request->profile_pic), 'public');
           
            $profile_pic_path = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
            $profile_pic = $profile_pic_path;
            $workshop->profile_pic   = $profile_pic;
            $workshop->save();            
        }
        else
        {
          $profile_pic         =  url('img/thumbnail.png');
          $workshop->profile_pic   = $profile_pic;
          $workshop->save();
        }

        if ($request->hasFile('cnic_image')) 
        {
            $ws_name = str_replace(' ', '_', $request->name);
            $s3_path =  Storage::disk('s3')->putFile('workshops/'. $workshop->id .'/cnic', new File($request->cnic_image), 'public');
            $cnic_pic_path = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
            $cnic_image = $cnic_pic_path;
            $workshop->cnic_image   = $cnic_image;            
            $workshop->save();
        }
        else
        { 
          $cnic_image         =  url('img/thumbnail.png');
          $workshop->cnic_image   = $cnic_image;       
          $workshop->save();
        }

        if ($request->hasFile('ws_images')) 
        {
             $files = $request->file('ws_images');

            foreach($files as $file)
            {
                $images = new WorkshopImages;                
                $s3_path =  Storage::disk('s3')->putFile('workshops/'. $workshop->id .'/ws_images', new File($file), 'public');
                $ws_pic_path = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
                $images->url = $ws_pic_path;                
                $images->workshop()->associate($workshop);
                $images->save();
            }
        }       

        $name = $request->name;        
        $email = $request->email;        
        $subject = "Please verify your email address.";
        $verification_code = str_random(30); //Generate verification code         
        DB::table('workshop_verifications')->insert(['ws_id'=>$workshop->id,'token'=>$verification_code]);
        Mail::send('workshop.verify', ['name' => $name, 'verification_code' => $verification_code],
            function($mail) use ($email, $name, $subject){
                $mail->from(getenv('MAIL_USERNAME'), "jazib.javed@gems.techverx.com");
                $mail->to($email, $name);
                $mail->subject($subject);
            });

        return Redirect::to('admin/workshops');       
    }

    /**
     * Display the specified workshop.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {        
        // get the workshop
        $workshop = Workshop::find($id);        
        // show the view and pass the workshop to it
        return View::make('workshop.show', ['workshop' => $workshop]);
    }

    /**
     * Show the form for editing the specified workshop.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // get the workshop
        $workshop = Workshop::find($id);
        $services = Service::all();
        // show the edit form and pass the workshop        
        return View::make('workshop.edit')->with('workshop', $workshop)->with('services',$services);            
    }

    /**
     * Update the specified workshop in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {             
        $rules = [

            'name'                           => 'required|regex:/^[\pL\s\-]+$/u',
            'owner_name'                     => 'required|regex:/^[\pL\s\-]+$/u',            
            'cnic'                           => 'required|digits:13',
            'mobile'                         => 'required|digits:11',
            'landline'                       => 'digits:11',
            'open_time'                      => 'required',
            'close_time'                     => 'required',
            'type'                           => 'required',
            'profile_pic'                    => 'image|mimes:jpg,png',  
            'cnic_image'                     => 'image|mimes:jpg,png',  

            'shop'                           => 'required|numeric',
            'building'                       => 'regex:/^[\pL\s\-]+$/u',
            'block'                          => 'regex:/^[\pL\s\-]+$/u',
            'street'                         => 'required|string',
            'town'                           => 'required|regex:/^[\pL\s\-]+$/u',
            'city'                           => 'required|regex:/^[\pL\s\-]+$/u',
            'service_id.*'                   => 'required|integer:unique',
            'service_rate.*'                 => 'required|integer',
            'service_time.*'                 => 'required|alpha_dash' 
        ];  

        $input = $request->only('name', 'owner_name', 'cnic', 'mobile', 'landline','open_time', 'close_time', 'type', 'shop', 'building', 'street', 'town', 'city');
        $validator = Validator::make($input, $rules);
        if($validator->fails()) {
            return Redirect::to('admin/workshops/' . $id . '/edit')
                ->withErrors($validator);
                // ->withInput(Input::except('password'));
        } 

         // Update workshop
        $workshop = Workshop::find($id);

        if ($request->hasFile('profile_pic')) 
        {            
            $s3_path =  Storage::disk('s3')->putFile('workshops/'. $workshop->id .'/logo', new File($request->profile_pic), 'public');
            $profile_pic_path = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
            $profile_pic = $profile_pic_path;    
        }
        else
        {
            $profile_pic         =  $workshop->profile_pic;
        }


        if ($request->hasFile('cnic_image')) 
        {
            // $ws_name = str_replace(' ', '_', $request->name);
            $s3_path =  Storage::disk('s3')->putFile('workshops/'. $workshop->id .'/cnic', new File($request->cnic_image), 'public');
            $cnic_pic_path = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
            $cnic_image = $cnic_pic_path;
        }
        else
        {
            $cnic_image         =  $workshop->cnic_image;
        }

        $workshop->name             = Input::get('name');        
        $workshop->owner_name       = Input::get('owner_name');  
        $workshop->cnic             = Input::get('cnic');
        $workshop->mobile           = Input::get('mobile');
        $workshop->landline         = Input::get('landline');
        $workshop->type             = Input::get('type');
        $workshop->profile_pic      = $profile_pic;
        $workshop->cnic_image      =  $cnic_image;        
        $workshop->open_time        = Input::get('open_time');
        $workshop->close_time       = Input::get('close_time');        
        $workshop->save();   

        // Update Workshop Address
        $address = WorkshopAddress::find($workshop->address->id);        
        $address->shop              = Input::get('shop');
        $address->building          = Input::get('building');
        $address->street            = Input::get('street');
        $address->block             = Input::get('block');
        $address->town              = Input::get('town');
        $address->city              = Input::get('city');
                        
        $address->update();

        if($request->hasFile('ws_images')) 
        {
            $files = $request->file('ws_images');
            foreach($files as $file)
            {
                $images = new WorkshopImages;                
                $s3_path =  Storage::disk('s3')->putFile('workshops/'. $workshop->id .'/ws_images', new File($file), 'public');
                $ws_pic_path = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
                $images->url = $ws_pic_path;
                $images->save();
                $workshop->images()->associate($images);
                $workshop->save();
            }
        }
        
        // Session::flash('message', 'Successfully updated Workshop!');
        return Redirect::to('admin/workshops');
    }

    /**
     * Remove the specified workshop from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // delete
        $workshop = Workshop::find($id);
        $workshop->delete();

        // redirect
        Session::flash('message', 'Successfully deleted the Workshop!');
        return Redirect::to('admin/workshops');      
    }

    public function inactive_workshops()
    {
        $workshops = Workshop::onlyTrashed()->get();  
        return View::make('workshop.inactive')
        ->with('workshops', $workshops); 
    }

    public function restore($id) 
    {
        $workshop = Workshop::withTrashed()->find($id)->restore();
        return redirect ('admin/workshops');
    }
    
    /**
     * API Register for new workshop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Post(
     *   path="/api/workshop/register",
     *   summary="Register Workshop",
     *   operationId="register",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="name",
     *     in="formData",
     *     description="Name of Workshop",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="owner_name",
     *     in="formData",
     *     description="Owner Name",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     description="Workshop Email Address",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="password",
     *     in="formData",
     *     description="Workshop Login Password",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="password_confirmation",
     *     in="formData",
     *     description="Workshop Confirm Password",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="cnic",
     *     in="formData",
     *     description="Workshop CNIC card Number",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="mobile",
     *     in="formData",
     *     description="Workshop Mobile Number",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="landline",
     *     in="formData",
     *     description="Workshop Landline Number",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="type",
     *     in="formData",
     *     description="Workshop Type",
     *     required=true,
     *     type="string",
     *     enum={"Authorized", "Unauthorized"}
     *   ),
     *   @SWG\Parameter(
     *     name="open_time",
     *     in="formData",
     *     description="Workshop Opening Time",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="close_time",
     *     in="formData",
     *     description="Workshop Closing Time",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="profile_pic",
     *     in="formData",
     *     description="Workshop Logo/Profile Picture",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="cnic_image",
     *     in="formData",
     *     description="CNIC Image",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="ws_images",
     *     in="formData",
     *     description="ws_images[base64,base64,base64]",
     *     required=false,
     *     type="array",
     *     items="[base64strings]"
     *   ),
     *   @SWG\Parameter(
     *     name="shop",
     *     in="formData",
     *     description="Workshop Shop No",
     *     required=false,
     *     type="number"
     *   ),
     *   @SWG\Parameter(
     *     name="building",
     *     in="formData",
     *     description="Workshop Building",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="street",
     *     in="formData",
     *     description="Workshop Street",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="block",
     *     in="formData",
     *     description="Workshop Block",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="town",
     *     in="formData",
     *     description="Workshop Town",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="city",
     *     in="formData",
     *     description="Workshop City",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="services",
     *     in="formData",
     *     description="services[{service_id,service_rate,service_time}]",
     *     required=false,
     *     type="array",
     *     items="[service_id,service_rate,service_time]"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function register(Request $request)
    {
        $rules = [
            'name'                           => 'required|regex:/^[\pL\s\-]+$/u',
            'owner_name'                     => 'required|regex:/^[\pL\s\-]+$/u',
            'email'                          => 'required|email|unique:workshops',
            'password'                       => 'required|confirmed|min:8',
            'password_confirmation'          => 'required',
            'cnic'                           => 'required|digits:13',
            'mobile'                         => 'required|digits:11',
            'landline'                       => 'digits:11',
            'open_time'                      => 'required',
            'close_time'                     => 'required',
            'type'                           => 'required',
            'profile_pic'                    => 'image|mimes:jpg,png',  
            'cnic_image'                     => 'image|mimes:jpg,png',  

            'shop'                           => 'numeric',
            'building'                       => 'regex:/^[\pL\s\-]+$/u',
            'block'                          => 'regex:/^[\pL\s\-]+$/u',
            'street'                         => 'string',
            'town'                           => 'regex:/^[\pL\s\-]+$/u',
            'city'                           => 'regex:/^[\pL\s\-]+$/u',
            // 'service_id.*'                   => 'required|integer',
            // 'service_rate.*'                 => 'required|integer',
            // 'service_time.*'                 => 'required|alpha_dash' 
        ];        

        $input = $request->only('name', 'owner_name', 'email', 'password', 'password_confirmation', 'cnic', 'mobile', 'landline','open_time', 'close_time', 'type', 'shop', 'building', 'block', 'street', 'town', 'city');        
        
        $validator = Validator::make($input, $rules);
        if($validator->fails()) {
            $request->offsetUnset('password');
            return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => false,
                    'message' => $validator->messages(),
                    'body' => $request->all()
                ],Response::HTTP_OK);
        }      
        //Insert Workshop data from request 
        $workshop = Workshop::create([
                                'name' => $request->name, 
                                'owner_name' => $request->owner_name ,
                                'email' => $request->email, 
                                'password' => Hash::make($request->password), 
                                'cnic' => $request->cnic, 
                                'mobile' => $request->mobile, 
                                'type' => $request->type,                                 
                                'open_time' => $request->open_time, 
                                'close_time' => $request->close_time, 
                                'is_approved' => 0
                            ]);

        //Insert Address data from request
        $address = WorkshopAddress::create([
                                        'shop' => $request->shop, 
                                        'building' => $request->building, 
                                        'street' => $request->street, 
                                        'block' => $request->block,
                                        'town' => $request->town, 
                                        'city' => $request->city, 
                                        'workshop_id' => $workshop->id, 
                                        'coordinates' => NULL
                                    ]);

        //Insert Services data from request        
        $services = $request->services;
        if(!empty($service_ids)){
            foreach($services as $service){
                $workshop->services()->attach($service->service_id,['service_rate' => $service->service_rate, 'service_time' => $service->service_time]);
            }
        }

        //By Default Inserting Workshop Balance 2000
        $workshop_balance = new WorkshopBalance;        
        $workshop_balance->balance              = 2000;
        $workshop_balance->workshop_id          = $workshop->id;
        $workshop_balance->save();

        if (!empty($request->profile_pic))
        {            
            $file_data = $request->profile_pic;             
            @list($type, $file_data) = explode(';', $file_data);
            @list(, $file_data) = explode(',', $file_data);             
            $s3_path =  Storage::disk('s3')->putFile('workshops/'. $workshop->id .'/logo', base64_decode($file_data), 'public');              
           
            $profile_pic_path = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
            $workshop->profile_pic   = $profile_pic_path;
            $workshop->save();            
        }
        else
        {
          $profile_pic         =  url('img/thumbnail.png');
          $workshop->profile_pic   = $profile_pic;
          $workshop->save();
        }

        if (!empty($request->cnic_image)) 
        {
            $file_data = $request->cnic_image;             
            @list($type, $file_data) = explode(';', $file_data);
            @list(, $file_data) = explode(',', $file_data);             
            $s3_path =  Storage::disk('s3')->putFile('workshops/'. $workshop->id .'/cnic', base64_decode($file_data), 'public');              
            $cnic_image_path = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
            $workshop->cnic_image   = $cnic_image_path;
            $workshop->save();             
        }
        else
        { 
          $cnic_image         =  url('img/thumbnail.png');
          $workshop->cnic_image   = $cnic_image;       
          $workshop->save();
        }

        $name = $request->name;        
        $email = $request->email;        
        $subject = "Please verify your email address.";
        $verification_code = str_random(30); //Generate verification code

        DB::table('workshop_verifications')->insert(['ws_id'=>$workshop->id,'token'=>$verification_code]);
        Mail::send('workshop.verify', ['name' => $name, 'verification_code' => $verification_code],
            function($mail) use ($email, $name, $subject){
                $mail->from(getenv('MAIL_USERNAME'), "jazib.javed@gems.techverx.com");
                $mail->to($email, $name);
                $mail->subject($subject);
            });
        return response()->json([
            'http-status' => Response::HTTP_OK,
            'status' => true,
            'message' => 'Thanks for signing up! Please check your email to complete your registration.',
            'body' => $request->all()
        ],Response::HTTP_OK);
    }

    /**
     * API Login for workshop, on success return JWT Auth token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    
    /**
     * @SWG\Post(
     *   path="/api/workshop/login",
     *   summary="Workshop Login",
     *   operationId="login",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     description="Email of Workshop",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="password",
     *     in="formData",
     *     description="Workshop Password",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function login(Request $request)
    {           
        $check = Workshop::select('is_approved')->where('email', $request->email)->first();        
        if((!empty($check)) && ($check->is_approved == 0)){  
            return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => false,
                    'message' => 'Workshop is not approved by the admin',
                    'body' => $request->all()
                ],Response::HTTP_OK);
        }
        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];
        try {
            Config::set('auth.providers.users.model', \App\Workshop::class);
            if (! $token = JWTAuth::attempt($credentials)) {
                $request->offsetUnset('password');
                $request->offsetUnset('password_confirmation');
                return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => false,
                    'message' => 'We cant find an account with this credentials.',
                    'body' => $request->all()
                ],Response::HTTP_OK);
            }
        } catch (JWTException $e) {
            // something went wrong whilst attempting to encode the token
            $request->offsetUnset('password');
            $request->offsetUnset('password_confirmation');
            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => 'Failed to login, please try again.',
                'body' => $request->all()
            ],Response::HTTP_OK);
        }
        // all good so return the token
        $workshop = Auth::user();
        // Config::set('jwt.user' , "App\User");
        // Config::set('auth.providers.users.model', \App\User::class);
        return response()->json([
            'http-status' => Response::HTTP_OK,
            'status' => true,
            'message' => 'success',
            'body' => [ 'token' => $token , 'workshop' => $workshop ],
        ],Response::HTTP_OK);
    }

    /**
     * Log out
     * Invalidate the token, so workshop cannot use it anymore
     * They have to relogin to get a new token
     *
     * @param Request $request
     */
    /**
     * @SWG\Get(
     *   path="/api/workshop/logout",
     *   summary="Workshop Logout",
     *   operationId="logout",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Auth Logout",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function logout(Request $request) {
        $this->validate($request, ['token' => 'required']);
        try {
            Config::set('auth.providers.users.model', \App\Workshop::class);
            JWTAuth::invalidate($request->input('token'));
            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => true,
                'message' => 'success',
                'body' => ''
            ],Response::HTTP_OK);
        } catch (JWTException $e) {
            // something went wrong whilst attempting to encode the token
            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => 'Failed to logout, please try again.',
                'body' => $request->all()
            ],Response::HTTP_OK);
        }
    }

    /**
     * API Recover Password for new workshop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Post(
     *   path="/api/workshop/recover",
     *   summary="Recover Workshop Password",
     *   operationId="recover",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     description="Workshop Email",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function recover(Request $request)
    {
        $workshop = Workshop::where('email', $request->email)->first();
        if (!$workshop) {
            $error_message = "Your email address was not found.";
            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => $error_message,
                'body' => ''
            ],Response::HTTP_OK);
        }
        try {
            Config::set('auth.providers.users.model', \App\Workshop::class);

            $response = $this->broker()->sendResetLink(
                $request->only('email')
            );            
        } catch (\Exception $e) {
            //Return with error
            $error_message = $e->getMessage();
            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => $error_message,
                'body' => null
            ],Response::HTTP_OK);
        }
        return response()->json([
            'http-status' => Response::HTTP_OK,
            'status' => true,
            'message' => 'A reset email has been sent! Please check your email.',
            'body' => ''
        ],Response::HTTP_OK);
    }

    protected function broker()
    {
        return Password::broker('workshops');
    }


    /**
     * API Verify Email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
     /**
     * @SWG\Get(
     *   path="/api/workshop/verifyEmail",
     *   summary="Verify Workshop Email",
     *   operationId="verifyEmail",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   consumes={"application/xml", "application/json"},
     *   produces={"application/xml", "application/json"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="verification_code",
     *     in="query",
     *     description="Verification Code",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function verifyEmail($verification_code)
    {
        $check = DB::table('workshop_verifications')->where('token',$verification_code)->first();
        if(!is_null($check)){
            $workshop = Workshop::find($check->ws_id);
            if($workshop->is_verified == 1){
                return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => false,
                    'message' => 'Account already verified.',
                    'body' => ''
                ],Response::HTTP_OK);
            }
            $workshop->update(['is_verified' => 1]);
            DB::table('workshop_verifications')->where('token',$verification_code)->delete();
            // return response()->json([
            //     'http-status' => Response::HTTP_OK,
            //     'status' => true,
            //     'message' => 'You have successfully verified your email address.',
            //     'body' => ''
            // ],Response::HTTP_OK);
            return View::make('workshop.thankyou');
        }
        return response()->json([
            'http-status' => Response::HTTP_OK,
            'status' => false,
            'message' => 'Verification code is invalid.',
            'body' => ''
        ],Response::HTTP_OK);
    }

    /**
     * API Password Reset for Workshop, on success return Success Message
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Post(
     *   path="/api/workshop/password-reset",
     *   summary="Workshop Password Reset",
     *   operationId="password Reset",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="prev_password",
     *     in="formData",
     *     description="Workshop's Previous Password",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="password",
     *     in="formData",
     *     description="Workshop's New Password",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="password_confirmation",
     *     in="formData",
     *     description="Workshop's Confirm Password",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */

    public function passwordReset(Request $request)
    {
        $rules = [
            'prev_password'  => 'required',
            'password'  => 'required|confirmed|min:6',
        ];

        $input = $request->only('prev_password','password', 'password_confirmation');
        $validator = Validator::make($input, $rules);

        if($validator->fails()) {
            $request->offsetUnset('prev_password');
            $request->offsetUnset('password');
            $request->offsetUnset('password_confirmation');
            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => $validator->messages(),
                'body' => $request->all()
            ],Response::HTTP_OK);
        }
        else{
            $workshop   = JWTAuth::authenticate();

            try {
                // Config::set('jwt.user' , "App\Customer");
                Config::set('auth.providers.users.model', \App\Customer::class);
                if (!Hash::check($request->prev_password, $workshop->password)) {
                    $request->offsetUnset('prev_password');
                    $request->offsetUnset('password');
                    $request->offsetUnset('password_confirmation');
                    return response()->json([
                        'http-status' => Response::HTTP_OK,
                        'status' => false,
                        'message' => 'We cant find an account with this credentials.',
                        'body' => $request->all()
                    ],Response::HTTP_OK);
                }
            } catch (JWTException $e) {
                // something went wrong whilst attempting to encode the token
                $request->offsetUnset('prev_password');
                $request->offsetUnset('password');
                $request->offsetUnset('password_confirmation');
                return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => false,
                    'message' => 'Failed to Reset Password, please try again.',
                    'body' => $request->all()
                ],Response::HTTP_OK);
            }
            // all good so Reset Customer's Password
            $workshop->password = Hash::make($request->password);
            $workshop->save();

            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => true,
                'message' => 'success',
                'body' => [ 'workshop' => $workshop ],
            ],Response::HTTP_OK);
        }
    }

    /**
     *  Approve Workshop
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveWorkshop($id){
        //Approve Workshop
        $workshop = Workshop::find($id);
        $workshop->is_approved       = 1;
        $workshop->save();
        $subject = "Conragulations! Your workshop has been approved by Admin.";
           Mail::send('workshop.confirmationEmail', ['name' => $workshop->name],
            function($mail) use ($email, $name, $subject){
                $mail->from(getenv('MAIL_USERNAME'), "jazib.javed@gems.techverx.com");
                $mail->to($workshop->email, $workshop->name);
                $mail->subject($subject);
            });        
        return Redirect::to('admin/workshops');
    }

    /**
     *  Unapprove Workshop
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function undoWorkshopApproval($id){
        //Approve Workshop
        $workshop = Workshop::find($id);
        $workshop->is_approved       = 0;
        $workshop->save();                
        return Redirect::to('admin/workshops');
    }

    /**
     * @SWG\Get(
     *   path="/api/workshop/getWorkshop/{id}",
     *   summary="Get Workshop Details",
     *   operationId="fetch",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="id",
     *     in="path",
     *     description="Workshop ID",
     *     required=true,
     *     type="integer"
     *   ),    
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
    */     
    public function getWorkshop($id){
        $workshop = Workshop::find($id);
        $address = $workshop->address;
        $service = $workshop->services;
        $balance = $workshop->balance;
        return response([
            'http-status' => Response::HTTP_OK,
            'status' => true,
            'message' => 'Workshop Details!',
            'body' => ['workshop' => $workshop ],
        ],Response::HTTP_OK);
    }
    /**
     * @SWG\Patch(
     *   path="/api/workshop/updateProfile/{workshop_id}",
     *   summary="Update Workshop Details",
     *   operationId="update",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="workshop_id",
     *     in="path",
     *     description="Workshop ID",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Parameter(
     *     name="name",
     *     in="formData",
     *     description="Name of Workshop",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="owner_name",
     *     in="formData",
     *     description="Owner Name",
     *     required=true,
     *     type="string"
     *   ),     
     *   @SWG\Parameter(
     *     name="cnic",
     *     in="formData",
     *     description="Workshop CNIC Number",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="mobile",
     *     in="formData",
     *     description="Workshop Mobile Number",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="landline",
     *     in="formData",
     *     description="Workshop Landline Number",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="type",
     *     in="formData",
     *     description="Workshop Type",
     *     required=true,
     *     type="string",
     *     enum={"Authorized", "Unauthorized"}
     *   ),
     *   @SWG\Parameter(
     *     name="open_time",
     *     in="formData",
     *     description="Workshop Opening Time",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="close_time",
     *     in="formData",
     *     description="Workshop Closing Time",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="profile_pic",
     *     in="formData",
     *     description="Workshop Profile Image",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="cnic_image",
     *     in="formData",
     *     description="Workshop CNIC Image",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="_method",
     *     in="formData",
     *     description="Always give PATCH",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    /**
     * API Register store data of new customer.
     *
     * @param Request $request
     * @param $id
     * @param $address_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function profileUpdate(Request $request, $id)
    {                
        $rules = [                        
            'name'                           => 'required|regex:/^[\pL\s\-]+$/u',
            'owner_name'                     => 'required|regex:/^[\pL\s\-]+$/u',            
            'cnic'                           => 'required|digits:13',
            'mobile'                         => 'required|digits:11',
            'landline'                       => 'digits:11',
            'open_time'                      => 'required',
            'close_time'                     => 'required',
            'type'                           => 'required'            
        ];          

        $input = $request->only('name', 'owner_name', 'cnic', 'mobile', 'landline','open_time', 'close_time', 'type');

        $validator = Validator::make($input, $rules);
        if($validator->fails()) {
            $request->offsetUnset('password');
            return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => false,
                    'message' => $validator->messages(),
                    'body' => $request->all()
                ],Response::HTTP_OK);
        }
        $workshop = Workshop::find($id);
        $workshop->name             = $request->name;        
        $workshop->owner_name       = $request->owner_name;  
        $workshop->cnic             = $request->cnic;
        $workshop->mobile           = $request->mobile;
        $workshop->landline         = $request->landline;
        $workshop->open_time        = $request->open_time;
        $workshop->close_time       = $request->close_time;        
        $workshop->type             = $request->type;        
        $workshop->save();      
        
        return response([
            'http-status' => Response::HTTP_OK,
            'status' => true,
            'message' => 'Details Updated!',
            'body' => $request->all()
        ],Response::HTTP_OK);
    }

    /**
     *  Edit Workshop Service View
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function editWorkshopService($id){
        $services = Service::all();
        $workshop_service = DB::table('workshop_service')->where('id', $id)->first();
        return View::make('workshop.services.edit')->with('workshop_service', $workshop_service)->with('services',$services);            

    }

    /**
     *  Update Workshop Service
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateWorkshopService(Request $request){
        $rules = [            
            'service_rate'    => 'required|numeric',            
            'service_time'    => 'required'                        
            ];        
        $input = $request->only('service_rate', 'service_time' );
        $validator = Validator::make($input, $rules);
        if($validator->fails()) {
            $workshop_service_id = $request->workshop_service_id ;            
            return Redirect::to('admin/edit-workshop-service/'.$workshop_service_id)
                ->withErrors($validator);
        }                 
        $workshop = Workshop::find($request->workshop_id);
        $workshop->services()->updateExistingPivot($request->service_id, ['service_rate' => $request->service_rate, 'service_time' => $request->service_time ]);
        return Redirect::to('admin/workshops/'.$request->workshop_id);               

    }

    /**
     *  Add Workshop Service
     *
     * @param $workshop
     * @return \Illuminate\Http\JsonResponse
     */
    public function addWorkshopService($workshop){
        $workshop = Workshop::find($workshop);
        $services = Service::all();        
        return View::make('workshop.services.add')->with('workshop', $workshop)->with('services',$services);            
    }
    
    /**
     *  Store Workshop Service
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeWorkshopService(Request $request){
        $rules = [
            // 'service_id'      => 'required|unique_with:workshop_service,workshop_id',
            'service_rate'    => 'required',            
            'service_time'    => 'required'                        
            ];
        $input = $request->only('service_rate', 'service_time' );

        $validator = Validator::make($input, $rules);
        if($validator->fails()) {            
            return Redirect::to('admin/add-workshop-service/'.$request->workshop_id)
                ->withErrors($validator);                
        }        
        $workshop = Workshop::find($request->workshop_id);
        $service = $request->service_id; 
        $rate = $request->service_rate;
        $time = $request->service_time;       
        
        $workshop->services()->attach($service, ['service_rate' => $rate , 'service_time' => $time]);
        
        return Redirect::to('admin/add-workshop-service/'.$workshop->id);               
    }

    /**
     *  Delete Workshop Service
     *
     * @param $workshop_id
     * @param $service_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteWorkshopService($workshop_id, $service_id){
        $workshop = Workshop::find($workshop_id);
        $workshop->services()->detach($service_id);        
        // show the view and pass the workshop to it
        return Redirect::to('admin/workshops/'.$workshop->id);               
    }

    /**
     *  Show History Workshop 
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show_history()
    {
        return View::make('workshop.history');
    }

    /**
     *  Show Customers Workshop 
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show_customers()
    {
        return View::make('workshop.customers');
    }

    /**
     *  Show Requests 
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show_requests()
    {
        return View::make('workshop.requests');
    }


     /**
     * Searching a workshop.
     *
     */
     /**
     * @SWG\Post(
     *   path="/api/customer/search-workshop",
     *   summary="Search Workshop",
     *   operationId="searchByWorkshop",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="token",
     *     in="formData",
     *     description="Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="name",
     *     in="formData",
     *     description="Workshop Name",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="type",
     *     in="formData",
     *     description="Workshop Type",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="service_name",
     *     in="formData",
     *     description="Sercice Name",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="address_block",
     *     in="formData",
     *     description="Workshop Address Block",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="address_area",
     *     in="formData",
     *     description="Workshop Address Area",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="address_town",
     *     in="formData",
     *     description="Workshop Address Town",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="address_city",
     *     in="formData",
     *     description="Workshop Address City",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function searchWorkshop(Request $request)
    {   
        // $workshops = Workshop::leftJoin('workshop_addresses', 'workshops.id', '=','workshop_addresses.workshop_id')->where('workshops.status', 1)->get();
        /*$workshops = Workshop::join('workshop_service', 'workshops.id', '=','workshop_service.workshop_id')->leftJoin('services', 'workshop_service.service_id', '=','services.id')->where('workshops.status', 1)->with('address')->get();*/
        $workshops = Workshop::where('workshops.status', 1)->with('address');
        $workshop_ids = [];
        if ($request->has('name')) {
            $workshops = $workshops->where('name', 'LIKE', '%'.$request->name.'%');
        }
        if ($request->has('type')) {
            $workshops = $workshops->where('type', $request->type);
        }
        // if ($request->has('geo_cord')) {
        //     $workshops->where('geo_cord', $request->geo_cord);
        // }
        if ($request->has('service_name')) {
            // $workshops = $workshops->where('services.name', $request->service_name);
            $service_names = explode(", ",$request->service_name);
            foreach($service_names as $service_name){
                 $workshop_ids = Db::table('workshop_service')->join('services', 'workshop_service.service_id', '=', 'services.id')->select('workshop_service.workshop_id')->where('services.name', 'LIKE', '%'.$service_name.'%')->get()->pluck('workshop_id')->toArray();
                $workshops = $workshops->whereIn('id', $workshop_ids);
            }

            $workshops=$workshops->with(['services' => function($query) use ($service_names) {
                $query->whereIn('name', $service_names);
            }]);
        }
        if ($request->has('address_block')) {
            $workshops = $workshops->where('address.block', 'LIKE', '%'.$request->address_block .'%');
        }
        if ($request->has('address_area')) {
            $workshops = $workshops->where('address.area', 'LIKE', '%'.$request->address_area.'%');
        }
        if ($request->has('address_town')) {
            $workshops = $workshops->where('address.town', 'LIKE', '%'.$request->address_town.'%');
        }
        if ($request->has('address_city')) {
            $workshops = $workshops->where('address.city', 'LIKE', '%'.$request->address_city.'%');
        }

        $workshops = $workshops->with('services.pivot');
        $workshops = $workshops->get();
        $eachWorkShop = new Workshop();

        foreach ($workshops as $key =>$workshop) {
            $workshops[$key]->est_rates = $workshop->sumOfServiceRates($workshop);
        }
        return response()->json([
            'http-status' => Response::HTTP_OK,
            'status' => true,
            'message' => '',
            'body' => $workshops
        ],Response::HTTP_OK);
    }

    /**
     * @SWG\Post(
     *   path="/api/workshop/add-new-workshop-services/{workshop_id}",
     *   summary="Add New Workshop Services",
     *   operationId="insert",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="workshop_id",
     *     in="path",
     *     description="workshop id",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Parameter(
     *     name="service_id",
     *     in="formData",
     *     description="service id",
     *     required=true,
     *     type="integer"
     *   ), 
     *   @SWG\Parameter(
     *     name="service_rate",
     *     in="formData",
     *     description="service rate",
     *     required=true,
     *     type="number"          
     *   ), 
     *   @SWG\Parameter(
     *     name="service_time",
     *     in="formData",
     *     description="service time",
     *     required=true,
     *     type="string"     
     *   ), 
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )    
     *  Store Workshop Service
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNewWorkshopServices(Request $request, $workshop_id){
        $rules = [
            'service_id'      => 'required|integer',
            'service_rate'    => 'required|numeric',            
            'service_time'    => 'required|numeric'                        
            ];
        $input = $request->only('service_id', 'service_rate', 'service_time' );

        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => $validator->messages()->first(),
                'body' => $request->all()
            ],Response::HTTP_OK);
        }
        $workshop = Workshop::find($workshop_id);
        $service = $request->service_id; 
        $rate = $request->service_rate;
        $time = $request->service_time;       
        
        $workshop->services()->attach($service, ['service_rate' => $rate , 'service_time' => $time]);
        return response()->json([
            'http-status'   => Response::HTTP_OK,
            'status'        => true,
            'message'       => 'Workshop Service Added!!',
            'body'          => ''
        ],Response::HTTP_OK);                  
    }
     /**
     * @SWG\Post(
     *   path="/api/workshop/update-workshop-service/{workshop_id}",
     *   summary="Add New Workshop Services",
     *   operationId="insert",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="workshop_id",
     *     in="path",
     *     description="workshop id",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Parameter(
     *     name="service_id",
     *     in="formData",
     *     description="service id",
     *     required=true,
     *     type="integer"
     *   ), 
     *   @SWG\Parameter(
     *     name="service_rate",
     *     in="formData",
     *     description="service rate",
     *     required=true,
     *     type="number"          
     *   ), 
     *   @SWG\Parameter(
     *     name="service_time",
     *     in="formData",
     *     description="service time",
     *     required=true,
     *     type="number"     
     *   ), 
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * ) 
     *  Update Workshop Service
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function workshopServiceUpdate(Request $request, $workshop_id){
        $rules = [
            'service_id'      => 'required|integer',           
            'service_rate'    => 'required|numeric',            
            'service_time'    => 'required'                        
            ];        
        $input = $request->only('service_id', 'service_rate', 'service_time' );
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => $validator->messages()->first(),
                'body' => $request->all()
            ],Response::HTTP_OK);
        }

        $workshop = Workshop::find($workshop_id);
        $workshop->services()->updateExistingPivot($request->service_id, ['service_rate' => $request->service_rate, 'service_time' => $request->service_time ]);

        return response()->json([
            'http-status'   => Response::HTTP_OK,
            'status'        => true,
            'message'       => 'Workshop Service Updated!!',
            'body'          => ''
        ],Response::HTTP_OK);             

    }
    /**
     * @SWG\Post(
     *   path="/api/workshop/deleteWorkshopService/{workshop_id}/{service_id}",
     *   summary="Delete Workshop Service",
     *   operationId="delete",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *   @SWG\Parameter(
     *     name="token",
     *     in="query",
     *     description="token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="workshop_id",
     *     in="path",
     *     description="workshop id",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Parameter(
     *     name="service_id",
     *     in="path",
     *     description="service id",
     *     required=true,
     *     type="integer"
     *   ), 
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     */
    public function unassignService($workshop_id, $service_id){
        // delete
        $workshop = Workshop::find($workshop_id);        
        $workshop->services()->detach($service_id);

        return response()->json([
            'http-status'   => Response::HTTP_OK,
            'status'        => true,
            'message'       => 'Workshop Service deleted!!',
            'body'          => ''
        ],Response::HTTP_OK);
    }
    /**
    * @SWG\Get(
    *   path="/api/workshop/workshopServices/{workshop_id}",
    *   summary="All services of Workshop",
    *   operationId="fetch",
    *   produces={"application/json"},
    *   tags={"Workshops"},
    *   @SWG\Parameter(
    *     name="token",
    *     in="query",
    *     description="token",
    *     required=true,
    *     type="string"
    *   ),    
    *   @SWG\Parameter(
    *     name="workshop_id",
    *     in="path",
    *     description="workshop id",
    *     required=true,
    *     type="integer"
    *   ),    

    *   @SWG\Response(response=200, description="successful operation"),
    *   @SWG\Response(response=406, description="not acceptable"),
    *   @SWG\Response(response=500, description="internal server error")
    * )
    */
    public function allWorkshopServices($workshop_id){
        $workshop_services = Workshop::find($workshop_id)->services;        
        return response()->json([
            'http-status'   => Response::HTTP_OK,
            'status'        => true,
            'message'       => 'Workshop Services!!',
            'body'          => $workshop_services 
        ],Response::HTTP_OK);   
    }

    public function workshop_profile(){
        $workshop = Auth::guard('workshop')->user();
        return View::make('workshop_profile.index')->with('workshop', $workshop);                       
    }

    public function edit_profile($id){
     // get the workshop
     $workshop = Workshop::find($id);
     $services = Service::all();
     // show the edit form and pass the workshop   
     return View::make('workshop_profile.edit')->with('workshop', $workshop)->with('services',$services);                         
    }

    public function update_profile(Request $request, $id)
    {
        $rules = [           
                        'name'                           => 'required|regex:/^[\pL\s\-]+$/u',
                        'owner_name'                     => 'required|regex:/^[\pL\s\-]+$/u',
                        'cnic'                           => 'required|digits:13',
                        'mobile'                         => 'required|digits:11',
                        'landline'                       => 'digits:11',
                        'open_time'                      => 'required',
                        'close_time'                     => 'required',
                        'type'                           => 'required',
                        'profile_pic'                    => 'image|mimes:jpg,png',  
                        'cnic_image'                     => 'image|mimes:jpg,png',  
            
                        'shop'                           => 'required|numeric',
                        'building'                       => 'regex:/^[\pL\s\-]+$/u',
                        'block'                          => 'regex:/^[\pL\s\-]+$/u',
                        'street'                         => 'required|string',
                        'town'                           => 'required|regex:/^[\pL\s\-]+$/u',
                        'city'                           => 'required|regex:/^[\pL\s\-]+$/u'
                    ];  
                                
                    $input = $request->only('name', 'owner_name', 'cnic', 'mobile', 'landline','open_time', 'close_time', 'type', 'shop', 'building', 'street', 'town', 'city');
                    $validator = Validator::make($input, $rules);
                    if($validator->fails()) {
                        return Redirect::back()
                            ->withErrors($validator);
                    } 
            
                     // Update workshop
                    $workshop = Workshop::find($id);
            
                    if ($request->hasFile('profile_pic')) 
                    {
                        $ws_name = str_replace(' ', '_', $request->name);
                        $s3_path =  Storage::disk('s3')->putFile('workshops/'.$ws_name.'/logo', new File($request->profile_pic), 'public');
                       
                        $profile_pic_path = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
                        $profile_pic = $profile_pic_path;
                        
                    }
                    else
                    {
                      $profile_pic         =  $workshop->profile_pic;
                    }
            
            
                    if ($request->hasFile('cnic_image')) 
                    {
                        $ws_name = str_replace(' ', '_', $request->name);
                        $s3_path =  Storage::disk('s3')->putFile('workshops/'.$ws_name.'/cnic', new File($request->cnic_image), 'public');
                        $cnic_pic_path = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
                        $cnic_image = $cnic_pic_path;
                    }
                    else
                    {
                      $cnic_image         =  $workshop->cnic_image;
                    }
            
                    $workshop->name             = Input::get('name');        
                    $workshop->owner_name       = Input::get('owner_name');  
                    $workshop->cnic             = Input::get('cnic');
                    $workshop->mobile           = Input::get('mobile');
                    $workshop->landline         = Input::get('landline');
                    $workshop->type             = Input::get('type');
                    $workshop->profile_pic      = $profile_pic;
                    $workshop->cnic_image      =  $cnic_image;
                    $workshop->open_time        = Input::get('open_time');
                    $workshop->close_time       = Input::get('close_time');
                    // $workshop->status           = 1;
                    $workshop->save();   
            
                    // Update Workshop Address
                    $address = WorkshopAddress::find($workshop->address->id);
            
                    // $address->type              = Input::get('address_type');
                    $address->shop              = Input::get('shop');
                    $address->building          = Input::get('building');
                    $address->street         = Input::get('street');
                    $address->block             = Input::get('block');
                    $address->town              = Input::get('town');
                    $address->city              = Input::get('city');
                    $address->town              = Input::get('town');                
                    $address->update();
                    
                    // Session::flash('message', 'Successfully updated Workshop!');
                    return Redirect::to('/profile');
    }

    public function addProfileService($workshop){
       // dd('here');
        $workshop = Workshop::find($workshop);
        $services = Service::all();        
        return View::make('workshop_profile.services.add')->with('workshop', $workshop)->with('services',$services);            
    }

    public function storeProfileService(Request $request){
        // dd($request);
        $rules = [
            // 'service_id'      => 'required|unique_with:workshop_service,workshop_id',
            'service_rate'    => 'required',            
            'service_time'    => 'required'                        
            ];
        $input = $request->only('service_id', 'service_rate', 'service_time' );

        $validator = Validator::make($input, $rules);
        if($validator->fails()) {
            // $request->offsetUnset('password');
            return Redirect::back()
                ->withErrors($validator);                
        }        
        $workshop = Workshop::find($request->workshop_id);
        $service = $request->service_id; 
        $rate = $request->service_rate;
        $time = $request->service_time;       
        
        $workshop->services()->attach($service, ['service_rate' => $rate , 'service_time' => $time]);
        
        return Redirect::to('profile');               
    }

    public function editProfileService($id){
        // dd('edit profile service');
        $services = Service::all();
        $workshop_service = DB::table('workshop_service')->where('id', $id)->first();
        return View::make('workshop_profile.services.edit')->with('workshop_service', $workshop_service)->with('services',$services);            

    }
    
    public function updateProfileService(Request $request){
        // dd('here');
        $rules = [            
            'service_rate'    => 'required|numeric',            
            'service_time'    => 'required'                        
            ];        
        $input = $request->only('service_rate', 'service_time' );
        $validator = Validator::make($input, $rules);
        if($validator->fails()) {
            $workshop_service_id = $request->workshop_service_id ;            
            return Redirect::back()
                ->withErrors($validator);
        }                 
        $workshop = Workshop::find($request->workshop_id);
        $workshop->services()->updateExistingPivot($request->service_id, ['service_rate' => $request->service_rate, 'service_time' => $request->service_time ]);
        return Redirect::to('profile');               

    }

    public function deleteProfileService($workshop_id, $service_id){
        $workshop = Workshop::find($workshop_id);
        $workshop->services()->detach($service_id);        
        // show the view and pass the workshop to it
        return Redirect::to('profile/');               
    }


    public function topup(){
        $workshops = Workshop::all();
        return View::make('admin.topup.topup')->with('workshops', $workshops);
    }

    public function topupBalance(Request $request){
        $rules = [
            'amount'                          => 'required|numeric',
            'workshop_id'                     => 'required|integer'
        ];        

        $input = $request->only('amount', 'workshop_id');
        $validator = Validator::make($input, $rules);
        if($validator->fails()) {            
            return Redirect::to('admin/top-up')
                ->withErrors($validator);                
        }

        $workshop = Workshop::find($request->workshop_id);
        $balance = $workshop->balance->balance;
        $new_balance = $request->amount + $balance;
        $workshop->balance->update(['balance' => $new_balance]); 

        $transaction = new WorkshopLedger;

        $transaction->amount                        = $request->amount;
        $transaction->workshop_id                   = $request->workshop_id;
        $transaction->transaction_type              = 'Top-Up';        
        $transaction->unadjusted_balance            = $balance;
        $transaction->adjusted_balance              = $new_balance;
        
        $transaction->save();

        return Redirect::to('admin/top-up');       
    }

    /**
     * Show Home
     *
     * @return \Illuminate\Http\Response
     */
    public function showHome() {
        return view('workshop.home');
    }

    /**
    * @SWG\Get(
    *   path="/api/workshop/address/{workshop_id}",
    *   summary="Workshop Address Details",
    *   operationId="fetch",
    *   produces={"application/json"},
    *   tags={"Workshops"},
    *    @SWG\Parameter(
    *     name="token",
    *     in="query",
    *     description="Token",
    *     required=true,
    *     type="string"
    *   ),
    *   @SWG\Parameter(
    *     name="workshop_id",
    *     in="path",
    *     description="workshop id",
    *     required=true,
    *     type="integer"
    *   ), 
    *   @SWG\Response(response=200, description="successful operation"),
    *   @SWG\Response(response=406, description="not acceptable"),
    *   @SWG\Response(response=500, description="internal server error")
    * )    
    * Display the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function workshopaddress($workshop_id)
    {           
        $workshop = Workshop::find($workshop_id);
        $address = $workshop->address; 
        
        return response()->json([
            'http-status' => Response::HTTP_OK,
            'status' => true,
            'message' => 'Workshop Details!',
            'body' => $address
        ],Response::HTTP_OK);
    }

    /**
     * @SWG\Patch(
     *   path="/api/workshop/update-address/{workshop_id}",
     *   summary="Update Workshop Address",
     *   operationId="update",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *    @SWG\Parameter(
     *     name="token",
     *     in="query",
     *     description="Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="workshop_id",
     *     in="path",
     *     description="Workshop ID",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="shop",
     *     in="formData",
     *     description="Workshop Shop No",
     *     required=true,
     *     type="number"
     *   ),
     *   @SWG\Parameter(
     *     name="building",
     *     in="formData",
     *     description="Workshop Building",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="street",
     *     in="formData",
     *     description="Workshop Street",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="block",
     *     in="formData",
     *     description="Workshop Block",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="town",
     *     in="formData",
     *     description="Workshop Town",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="city",
     *     in="formData",
     *     description="Workshop City",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="_method",
     *     in="formData",
     *     description="Required to update form",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *    
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateAddress(Request $request, $workshop_id)
    {
        $workshop = Workshop::find($workshop_id);
        $address = $workshop->address;                
        $rules = [            
            'shop'                           => 'required|numeric',
            'building'                       => 'regex:/^[\pL\s\-]+$/u',
            'block'                          => 'regex:/^[\pL\s\-]+$/u',
            'street'                         => 'required|string',
            'town'                           => 'required|regex:/^[\pL\s\-]+$/u',
            'city'                           => 'required|regex:/^[\pL\s\-]+$/u',
        ];

        $input = $request->only('shop', 'building', 'block', 'street', 'town', 'city');

        $validator = Validator::make($input , $rules);

        // process the login
        if ($validator->fails()) {
            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => $validator->messages(),
                'body' => $request->all()
            ],Response::HTTP_OK);
        }

        if (!count($address)) {
            $address = new WorkshopAddress;
            $address->workshop_id = $workshop->id;
        }
         
        $address->shop          =  $request->shop;
        $address->building      =  $request->building;        
        $address->block         =  $request->block;
        $address->street        =  $request->street;
        $address->town          =  $request->town;
        $address->city          =  $request->city;        
        $address->save(); 
        
        return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => true,
                    'message' => 'Address updated Successfully!',
                    'body' => $request->all()
                ],Response::HTTP_OK);               
    }

    /**
     * @SWG\Post(
     *   path="/api/workshop/update-workshop-images/{workshop_id}",
     *   summary="Update Workshop Images",
     *   operationId="update",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *    @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="workshop_id",
     *     in="path",
     *     description="Workshop ID",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="images",
     *     in="formData",
     *     description="[{old_url,new_image}]",
     *     required=true,
     *     type="array",
     *     items="[old_url,new_image]"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *    
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateWorkshopImages(Request $request, $workshop_id)
    {            
        $images = $request->images;
        $url_array = [];
        foreach($images as $image){
            $file_data = $image->new_image;                                 
            if( (empty($image->old_url)) && (!empty($image->new_image)) ){

                $url = $this->upload_image($file_data,$workshop_id);
                array_push($url_array, $url);
                $workshop_image = new WorkshopImages;
                $workshop_image->url            = $url;
                $workshop_image->workshop_id    = $workshop_id;
                $workshop->save();
                       
            }elseif( (!empty($image->old_url)) && (!empty($image->new_image)) ){

                $url = $this->upload_image($file_data,$workshop_id);                                
                array_push($url_array, $url);
                WorkshopImages::where('url', $image->old_url)
                                ->where('workshop_id',$workshop_id)
                                ->update(['url' => $url]);                
            }            
        }        
        
        return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => true,
                    'message' => 'Images Uploaded Successfully!',
                    'body' => $url_array
                ],Response::HTTP_OK);               
    }

    /**
     * @SWG\Patch(
     *   path="/api/workshop/update-workshop-profile-image/{workshop_id}",
     *   summary="Update Workshop Images",
     *   operationId="update",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *    @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="workshop_id",
     *     in="path",
     *     description="Workshop ID",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="profile_pic",
     *     in="formData",
     *     description="Base64 String",
     *     required=true,
     *     type="string"     
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *    
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateProfileImage(Request $request, $workshop_id)
    {            
        $file_data = $request->profile_pic;                       
        $url = $this->upload_image($file_data,$workshop_id);                                        
        $workshop_image = Workshop::where('workshop_id', $workshop_id)                            
                                    ->update(['profile_pic' => $url]);                
        return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => true,
                    'message' => 'Images Uploaded Successfully!',
                    'body' => $request
                ],Response::HTTP_OK);               
    }

    public function upload_image($file_data , $workshop_id){        
        @list($type, $file_data) = explode(';', $file_data);
        @list(, $file_data) = explode(',', $file_data);             
        $s3_path =  Storage::disk('s3')->putFile('workshops/'. $workshop_id . '/ws_images', base64_decode($file_data), 'public');               
        $ws_img = 'https://s3-us-west-2.amazonaws.com/mymystri-staging/'.$s3_path;
        return $ws_img;
    }

    /**
     * @SWG\Get(
     *   path="/api/workshop/get-workshop-ledger/{workshop_id}",
     *   summary="Update Workshop Images",
     *   operationId="update",
     *   produces={"application/json"},
     *   tags={"Workshops"},
     *    @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="workshop_id",
     *     in="path",
     *     description="Workshop ID",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *    
     * Getting Workshop Ledger.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getWorkshopLedger($workshop_id){
        $ledger = Workshop::find($workshop_id)->transactions;
        return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => true,
                    'message' => 'Workshop Ledger',
                    'body' => $ledger
                ],Response::HTTP_OK);
    }


}


