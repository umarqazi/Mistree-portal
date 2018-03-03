<?php

namespace App\Http\Controllers;

use App\CustomerAddress;
use JWTAuth;

use Illuminate\Support\Facades\Redirect;
use Hash, DB, Config, Mail, View;
use App\Customer;
use App\Address;
use App\Booking;
use App\Billing;
use App\Workshop;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Password;
use Illuminate\Mail\Message;

class CustomersController extends Controller
{
    /**
     * Fetching Guard.
     *
     * @return Auth::guard()
     */
    protected function guard()
    {
        return Auth::guard('customer');
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
        $this->auth = app('auth')->guard('customer');
    }

    /**
     * Display a listing of the customer.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $customers = Customer::all();
        return View::make('customer.index')->with('customers', $customers);
        // return View::make('customer.index');
    }

    /**
     * Show the form for creating a new customer.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return View::make('customers.create');
    }

    /**
     * Store a newly created customer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'name'      => 'required',
            'email'     => 'required|email|unique:customers',
            'password'  => 'required|confirmed|min:6',
            'con_number'=> 'required',
        ];
        $input = $request->only('name', 'email', 'password', 'password_confirmation', 'con_number');
        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return Redirect::to('customer/create')
                ->withErrors($validator)
                ->withInput();
        } else {
            // store
            $customer = new Customers;
            $customer->name       = Input::get('name');
            $customer->email      = Input::get('email');
            $customer->password   = Input::get('password');
            $customer->con_number = Input::get('con_number');
            $customer->status     = 1;
            
            $customer->save();

            // redirect
            Session::flash('message', 'Successfully created customer!');
            return Redirect::to('customer');
        }
    }

    /**
     * Display the specified customer.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $customer = Customer::find($id);

        return View::make('customers.show')
            ->with('customer', $customer);
    }

    /**
     * Show the form for editing the specified customer.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $customer = Customer::find($id);

        return View::make('customers.edit')
            ->with('customer', $customer);
    }

    /**
     * Update the specified customer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $rules = [
            'name'      => 'required',
            'email'     => 'required|email|unique:customers',
            'password'  => 'required|confirmed|min:6',
            'con_number'=> 'required',
        ];
        $input = $request->only('name', 'email', 'password', 'password_confirmation', 'con_number');
        $validator = Validator::make($input, $rules);

        // process the login
        if ($validator->fails()) {
            return Redirect::to('customers/' . $id . '/edit')
                ->withErrors($validator)
                ->withInput();
        } else {
            // store
            $customer = Customer::find($id);
            $customer->name       = Input::get('name');
            $customer->email      = Input::get('email');
            $customer->password   = Input::get('password');
            $customer->con_number = Input::get('con_number');
            $customer->save();

            // redirect
            Session::flash('message', 'Successfully updated customer!');
            return Redirect::to('customers');
        }
    }

    /**
     * Remove the specified customer from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // soft delete
        $customer = Customer::find($id);
        $customer->delete();

        // redirect
        Session::flash('message', 'Successfully deleted the customer!');
        return Redirect::to('customers');
    }

    /**
     * Restore the specified customer from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function restore($id){
        $customer   = Customer::withTrashed()->where('id', $id)->get();
        $customer->restore();

        // redirect
        Session::flash('message', 'Successfully activated the customer!');
        return Redirect::to('customers');
    }

    /**
     * API Register for new customer.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Post(
     *   path="/api/customer/register",
     *   summary="Register customer",
     *   operationId="register",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="name",
     *     in="formData",
     *     description="Customer Name",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     description="Customer Email",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="password",
     *     in="formData",
     *     description="Customer Password",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="password_confirmation",
     *     in="formData",
     *     description="Customer Password Confirmation",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="con_number",
     *     in="formData",
     *     description="Customer Contact Number",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function register(Request $request)
    {
        $rules = [
            'name'      => 'required',
            'email'     => 'required|email|unique:customers',
            'password'  => 'required|confirmed|min:6',
            'con_number'=> 'required',
        ];
        $input = $request->only('name', 'email', 'password', 'password_confirmation', 'con_number');
        $validator = Validator::make($input, $rules);
        if($validator->fails()) {
            $request->offsetUnset('password');
            $request->offsetUnset('password_confirmation');
            return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => false,
                    'message' => $validator->messages(),
                    'body' => $request->all()
                ],Response::HTTP_OK);
        }
        $name = $request->name;
        $con_number = $request->con_number;
        $email = $request->email;
        $password = $request->password;
        $customer = Customer::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'status' => 1, 'con_number' => $con_number]);
        // return $this->login($request);
        $verification_code = str_random(30); //Generate verification code
        DB::table('customer_verifications')->insert(['cust_id'=>$customer->id,'token'=>$verification_code]);
        $subject = "Please verify your email address.";
        Mail::send('customer.verify', ['name' => $name, 'verification_code' => $verification_code],
            function($mail) use ($email, $name, $subject){
                $mail->from(getenv('MAIL_USERNAME'), "umar.farooq@gems.techverx.com");
                $mail->to($email, $name);
                $mail->subject($subject);
            });
        return response()->json([
            'http-status' => Response::HTTP_OK,
            'status' => true,
            'message' => 'Thanks for signing up! Please check your email to complete your registration.',
            'body' => null
        ],Response::HTTP_OK);
    }

    /**
     * API Login for customer, on success return JWT Auth token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Post(
     *   path="/api/customer/login",
     *   summary="Login customer",
     *   operationId="login",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     description="Customer Email",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="password",
     *     in="formData",
     *     description="Customer Password",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function login(Request $request)
    {
        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        try {
            // Config::set('jwt.user' , "App\Customer");
            Config::set('auth.providers.users.model', \App\Customer::class);
            if (! $token = JWTAuth::attempt($credentials)) {
                $request->offsetUnset('password');

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

            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => 'Failed to login, please try again.',
                'body' => $request->all()
            ],Response::HTTP_OK);
        }
        // all good so return the token
        $customer = Auth::user();

        return response()->json([
            'http-status' => Response::HTTP_OK,
            'status' => true,
            'message' => 'success',
            'body' => [ 'token' => $token , 'customer' => $customer ],
        ],Response::HTTP_OK);
    }

    /**
     * Log out
     * Invalidate the token, so customer cannot use it anymore
     * They have to relogin to get a new token
     *
     * @param Request $request
     */
    /**
     * @SWG\Post(
     *   path="/api/customer/logout",
     *   summary="Customer Logout",
     *   operationId="logout",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Auth Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function logout(Request $request) {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => true,
                'message' => 'success',
                'body' => null
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
     * API Recover Password for new customer.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Post(
     *   path="/api/customer/recover",
     *   summary="Recover Customer Password",
     *   operationId="recover",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     description="Customer Email",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function recover(Request $request)
    {
        $rules  = [
            'email' => 'exists:customers'
        ];

        $validation = Validator::make($request->only('email'), $rules);

        if($validation->fails()){

            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => $validation->messages()->first(),
                'body' => null
            ], Response::HTTP_OK);
        }

        try {
            Config::set('auth.providers.users.model', \App\Customer::class);

            $this->broker()->sendResetLink(
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
            'body' => null
        ],Response::HTTP_OK);
    }

    protected function broker()
    {
        return Password::broker('customers');
    }

    /**
     * API Verify Email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail($verification_code)
    {
        $check = DB::table('customer_verifications')->where('token',$verification_code)->first();
        if(!is_null($check)){
            $customer = Customer::find($check->cust_id);
            if( $customer->is_verified ){

                return View::make('customer.thankyou')->with('message', 'Account already verified.');
            }
            $customer->update(['is_verified' => 1]);
            DB::table('customer_verifications')->where('token',$verification_code)->delete();

            return View::make('customer.thankyou')->with('message', 'Thank You For Verifying Your Email.');
        }

        return View::make('workshop.thankyou')->with('message', 'Verification code is invalid.');
    }

    /**
     * API Register store data of new customer.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function regStoreData(Request $request)
    {
         // read more on validation at http://laravel.com/docs/validation
        $rules = array(
            'cust_id'               => 'required',
            'address_type'          => 'required',
            'address_house_no'      => 'required',
            'address_street_no'     => 'required',
            'address_block'         => 'required',
            'address_area'          => 'required',
            'address_town'          => 'required',
            'address_city'          => 'required'

        );
        $validator = Validator::make($request->all(), $rules);

        // process the login
        if ($validator->fails()) {
            return response([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => 'Invalid Details!',
                'body' => $request->all()
            ],Response::HTTP_OK);
        } else {
            //customer
            $customer = Customer::findOrFail($request->cust_id);
            //address
            $address = new Address;
            $address->cust_id       =  $request->cust_id;
            $address->ws_id         =  '';
            $address->admin_id      =  '';
            $address->type          =  $request->address_type;
            $address->house_no      =  $request->address_house_no;
            $address->street_no     =  $request->address_street_no;
            $address->block         =  $request->address_block;
            $address->area          =  $request->address_area;
            $address->town          =  $request->address_town;
            $address->city          =  $request->address_city;
            $address->status        = 1;
            $address->save();     
            return response([
                'http-status' => Response::HTTP_OK,
                'status' => true,
                'message' => 'Details Added!',
                'body' => null
            ],Response::HTTP_OK);
        }
    }

    /**
     * Profile Update customer.
     *
     * @param Request $request
     * @param $id
     * @param $address_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function profileUpdate(Request $request, $id, $address_id)
    {
        $request_customer = $request->customer;
        $customer = Customer::find($id);
        $customer->fill($request_customer);
        $update_address = $request->update_address;
        if($update_address == true){
            if($address_id != ''){
                $request_address = $request->address;
                $address = Address::find($id);
                $address->fill($request_address);
            }else{
                $rules = array(
                    'address_type'          => 'required',
                    'address_house_no'      => 'required',
                    'address_street_no'     => 'required',
                    'address_block'         => 'required',
                    'address_area'          => 'required',
                    'address_town'          => 'required',
                    'address_city'          => 'required'
                );
                $validator = Validator::make($request->all(), $rules);

                // process the login
                if ($validator->fails()) {
                    return response([
                        'http-status' => Response::HTTP_OK,
                        'status' => false,
                        'message' => 'Invalid Details!',
                        'body' => $request->all()
                    ],Response::HTTP_OK);
                } else {
                    $address = new Address;
                    $address->cust_id       =  $id;
                    $address->ws_id         =  '';
                    $address->admin_id      =  '';
                    $address->type          =  $request->address_type;
                    $address->house_no      =  $request->address_house_no;
                    $address->street_no     =  $request->address_street_no;
                    $address->block         =  $request->address_block;
                    $address->area          =  $request->address_area;
                    $address->town          =  $request->address_town;
                    $address->city          =  $request->address_city;
                    $address->status        = 1;
                    $address->save(); 
                }
            }
        }
        //address   
        return response([
            'http-status' => Response::HTTP_OK,
            'status' => true,
            'message' => 'Details Added!',
            'body' => null
        ],Response::HTTP_OK);
    }

    /**
     * API Password Reset for customer, on success return Success Message
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Post(
     *   path="/api/customer/password-reset",
     *   summary="Customer Password Reset",
     *   operationId="password Reset",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Customer's Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="prev_password",
     *     in="formData",
     *     description="Customer's Previous Password",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="password",
     *     in="formData",
     *     description="Customer's New Password",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="password_confirmation",
     *     in="formData",
     *     description="Customer's Confirm Password",
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
                'message' => $validator->messages()->first(),
                'body' => null
            ],Response::HTTP_OK);
        }
        else{
            $customer   = JWTAuth::authenticate();

            try {
                // Config::set('jwt.user' , "App\Customer");
                Config::set('auth.providers.users.model', \App\Customer::class);
                if (!Hash::check($request->prev_password, $customer->password)) {
                    $request->offsetUnset('prev_password');
                    $request->offsetUnset('password');
                    $request->offsetUnset('password_confirmation');
                    return response()->json([
                        'http-status' => Response::HTTP_OK,
                        'status' => false,
                        'message' => 'Your provided password didn\'t match.',
                        'body' => null
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
                    'message' => 'Failed to reset password, Please try again.',
                    'body' => null
                ],Response::HTTP_OK);
            }
            // all good so Reset Customer's Password
            $customer->password = Hash::make($request->password);
            $customer->save();

            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => true,
                'message' => 'success',
                'body' => [ 'customer' => $customer ],
            ],Response::HTTP_OK);
        }
    }

    /**
     * API for customer's Cars And Addresses, on success return Customer with Cars And Address
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Get(
     *   path="/api/customer/profile",
     *   summary="Customer Cars And Addresses",
     *   operationId="Customer Cars And Addresses",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Customer's Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */

    public function getCustomerAddressAndCars()
    {
        $customer   = JWTAuth::authenticate();

        if (!$customer) {
            return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => 'Invalid Customer!',
                'body' => ''
            ],Response::HTTP_OK);
        } else {
            return response()->json([
                'http-status'   => Response::HTTP_OK,
                'status'        => true,
                'message'       => '',
                'body'          => $customer->load(['cars' => function($query){
                    $query->withTrashed();
                }, 'addresses'])
            ],Response::HTTP_OK);
        }
    }

    /**
     * API for Add  customer's Address, on success return Message
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Post(
     *   path="/api/customer/add-customer-address",
     *   summary="Add Customer Address",
     *   operationId="Add Customer Address",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Customer's Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="address_type",
     *     in="formData",
     *     description="Customer's Address Type",
     *     required=true,
     *     type="string"
     *   ),@SWG\Parameter(
     *     name="address_house_no",
     *     in="formData",
     *     description="Customer's address house no",
     *     required=true,
     *     type="string"
     *   ),@SWG\Parameter(
     *     name="address_street_no",
     *     in="formData",
     *     description="Customer's address street no",
     *     required=true,
     *     type="string"
     *   ),@SWG\Parameter(
     *     name="address_block",
     *     in="formData",
     *     description="Customer's address block",
     *     required=true,
     *     type="string"
     *   ),@SWG\Parameter(
     *     name="address_town",
     *     in="formData",
     *     description="Customer's address town",
     *     required=true,
     *     type="string"
     *   ),@SWG\Parameter(
     *     name="address_city",
     *     in="formData",
     *     description="Customer's address city",
     *     required=true,
     *     type="string"
     *   ),
     *
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */

    public function addCustomerAddress(Request $request)
    {
        // read more on validation at http://laravel.com/docs/validation
        $rules = array(
            'address_type'          => 'required',
            'address_house_no'      => 'required',
            'address_street_no'     => 'required',
            'address_block'         => 'required',
            'address_town'          => 'required',
            'address_city'          => 'required'
        );
        $validator = Validator::make($request->all(), $rules);

        // process the Address
        if ($validator->fails()) {
            return response([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => $validator->messages()->first(),
                'body' => $request->all()
            ],Response::HTTP_OK);
        } else {
            //customer
            $customer = JWTAuth::authenticate();

            //address
            $address = new CustomerAddress;
            $address->customer_id       =  $customer->id;
            $address->type              =  $request->address_type;
            $address->house_no          =  $request->address_house_no;
            $address->street_no         =  $request->address_street_no;
            $address->block             =  $request->address_block;
            $address->town              =  $request->address_town;
            $address->city              =  $request->address_city;
            $address->save();

            return response([
                'http-status' => Response::HTTP_OK,
                'status' => true,
                'message' => 'Details Added!',
                'body' => null
            ],Response::HTTP_OK);
        }
    }

    /**
     * API for Edit  customer's Address, on success return Message
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Put(
     *   path="/api/customer/edit-customer-address",
     *   summary="Edit Customer Address",
     *   operationId="Edit Customer Address",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Customer's Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="id",
     *     in="formData",
     *     description="Customer's Address Id To Edit",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Parameter(
     *     name="type",
     *     in="formData",
     *     description="Customer's Address Type To Edit",
     *     required=true,
     *     type="integer"
     *   ),@SWG\Parameter(
     *     name="house_no",
     *     in="formData",
     *     description="Customer's Address House No To Edit",
     *     required=true,
     *     type="integer"
     *   ),@SWG\Parameter(
     *     name="street_no",
     *     in="formData",
     *     description="Customer's Address Street No  To Edit",
     *     required=true,
     *     type="integer"
     *   ),@SWG\Parameter(
     *     name="block",
     *     in="formData",
     *     description="Customer's Address Block To Edit",
     *     required=true,
     *     type="integer"
     *   ),@SWG\Parameter(
     *     name="town",
     *     in="formData",
     *     description="Customer's Address Town To Edit",
     *     required=true,
     *     type="integer"
     *   ),@SWG\Parameter(
     *     name="city",
     *     in="formData",
     *     description="Customer's Address City To Edit",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */

    public function editCustomerAddress(Request $request)
    {
        $rules = array(
            'id'            => 'required',
            'type'          => 'required',
            'house_no'      => 'required',
            'street_no'     => 'required',
            'block'         => 'required',
            'town'          => 'required',
            'city'          => 'required'
        );
        $validator = Validator::make($request->all(), $rules);

        // process the Address
        if ($validator->fails()) {
            return response([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => $validator->messages()->first(),
                'body' => $request->all()
            ],Response::HTTP_OK);
        }

        else{
            $address = CustomerAddress::find($request->id);

            if (!$address){
                return response([
                    'http-status' => Response::HTTP_OK,
                    'status' => false,
                    'message' => 'Invalid Address!',
                    'body' => null
                ],Response::HTTP_OK);
            }
            else{
                $address->type = $request->type;
                $address->house_no = $request->house_no;
                $address->street_no = $request->street_no;
                $address->block = $request->block;
                $address->town = $request->town;
                $address->city = $request->city;

                $address->update();

                return response([
                    'http-status' => Response::HTTP_OK,
                    'status' => true,
                    'message' => 'Address Updated!',
                    'body' => null
                ],Response::HTTP_OK);
            }
        }
    }

    /**
     * API for Delete  customer's Address, on success return Message
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @SWG\Delete(
     *   path="/api/customer/delete-customer-address",
     *   summary="Delete Customer Address",
     *   operationId=" Delete Customer Address",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Customer's Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="id",
     *     in="formData",
     *     description="Customer's Address Id To Delete",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */

    public function deleteCustomerAddress(Request $request){
        $address = CustomerAddress::find($request->id);
        if (!$address)
        {
            return response([
                'http-status' => Response::HTTP_OK,
                'status' => false,
                'message' => 'Invalid Address!',
                'body' => null
            ],Response::HTTP_OK);
        }
        else{
            $address->delete();

            return response([
                'http-status' => Response::HTTP_OK,
                'status' => true,
                'message' => 'Address Deleted!',
                'body' => null
            ],Response::HTTP_OK);
        }
    }

    public function activateCustomer($id){        
        $customer = Customer::where('id', '=', $id)->first();
        $customer->update(['status' => 1]);        
        return Redirect::to('/admin/customers');
    }

    public function deactivateCustomer($id){        
        $customer = Customer::where('id', '=', $id)->first();
        $customer->update(['status' => 0]);        
        return Redirect::to('/admin/customers');
    }

    /**
     * API Vehicle History for customer
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse

     * @SWG\Get(
     *   path="/api/customer/vehicle-history",
     *   summary="Customer Vehile History",
     *   operationId="history",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Customer's Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="vehicle_no",
     *     in="query",
     *     description="Customer's Vehicle No",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function getVehicleHistory(){
        $vehicle_no = Input::get('vehicle_no');
        $bookings = Booking::where('vehicle_no', $vehicle_no)->where('job_status','completed')->with('billing')->with('services')->get();
        if(count($bookings) == 0){
            return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => false,
                    'message' => 'No History Found',
                    'body' => ''
                ],Response::HTTP_OK);        
        }else{
            return response()->json([
                    'http-status' => Response::HTTP_OK,
                    'status' => true,
                    'message' => 'Vehicle History',
                    'body' => $bookings
                ],Response::HTTP_OK);        
        }        
    }

    /**
     * API Lead Rating from Customer
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse

     * @SWG\Patch(
     *   path="/api/customer/leave-rating/{billing_id}",
     *   summary="Lead Review and Rating",
     *   operationId="insert",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Customer's Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="billing_id",
     *     in="path",
     *     description="Billing Id",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Parameter(
     *     name="review",
     *     in="formData",
     *     description="Lead Review",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="ratings",
     *     in="formData",
     *     description="Lead Ratings",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function insertRatings(Request $request, $billing_id){        
        
        $billing = Billing::find($billing_id);
        $billing->review        = $request->review;
        $billing->ratings       = $request->ratings;
        $billing->save();

        return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => true,
                'message' => 'Ratings Given',
                'body' => $request->all()
            ],Response::HTTP_OK);                
    }

    /**
     * API Workshop Details for customer to create Bookings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse

     * @SWG\Get(
     *   path="/api/customer/get-workshop/{workshop_id}",
     *   summary="Get Workshop Details",
     *   operationId="get",
     *   produces={"application/json"},
     *   tags={"Customers"},
     *   @SWG\Parameter(
     *     name="Authorization",
     *     in="header",
     *     description="Customer's Token",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="workshop_id",
     *     in="path",
     *     description="Workshop Id",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     */
    public function getWorkshopDetails($workshop_id){        
        
        $workshop = Workshop::find($workshop_id);                
        return response()->json([
                'http-status' => Response::HTTP_OK,
                'status' => true,
                'message' => 'Ratings Given',
                'body' => $workshop->load('services')
            ],Response::HTTP_OK);                
    }

}