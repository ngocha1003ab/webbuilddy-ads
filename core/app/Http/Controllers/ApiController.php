<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Gateway;
use App\Models\GatewayCurrency;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;


class ApiController extends Controller
{
    const DEFAULT_PASSWORD = "Ksebpe!vZyd&bE+VqQ3)3qtKU!8T(PGZxj!DE@x9mKx@Z7D2yYb%k^hCYxR#";

    public function signgleSignIn($user)
    {
        try {
            DB::beginTransaction();
            $name = $user->name;
            $email = $user->email;
            $username = $user->username;

            $exists = User::where('email', $email)->first();
            if ($exists) {
                if (!Auth::check()) {
                    Auth::attempt(["email" => $email, 'password' => ApiController::DEFAULT_PASSWORD]);
                }
            } else {
                $newUser = new User();
                $newUser->email = $email;
                $newUser->username = $username;
                $newUser->lastname = $name;
                $newUser->password = Hash::make(ApiController::DEFAULT_PASSWORD);
                $newUser->save();

                Auth::attempt(["email" => $email, 'password' => ApiController::DEFAULT_PASSWORD]);
            }
            DB::commit();
            return "DONE";
        } catch (\Exception $e) {
            DB::rollBack();
            return "ERROR";
        }
    }

    public function generateToken(Request $request)
    {
        $token = Crypt::encryptString(json_encode($request->all()));
        return response()->json([
            "status" => 200,
            "data" => $token
        ]);
    }

    public function returnConfirmPage(Request $request){
        $token = $request->token;
        $originRequest = json_decode(Crypt::decryptString($token));
        $this->signgleSignIn($originRequest);
        return view('webbuilddy.confirm-payment');
    }

    public function getConfirmPage(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $token = $request->token;
        $originRequest = json_decode(Crypt::decryptString($token));

        if (!isset($originRequest->amount)) {
            return response()->json([
                "status" => 403,
                "message" => "Parameter is incorrectly!"
            ]);
        }


        if (!isset($originRequest->method_code)) {
            return response()->json([
                "status" => 403,
                "message" => "Parameter is incorrectly!"
            ]);
        }


        if (!isset($originRequest->currency)) {
            return response()->json([
                "status" => 403,
                "message" => "Parameter is incorrectly!"
            ]);
        }


        if (!isset($originRequest->name)) {
            return response()->json([
                "status" => 403,
                "message" => "Parameter is incorrectly!"
            ]);
        }


        if (!isset($originRequest->username)) {
            return response()->json([
                "status" => 403,
                "message" => "Parameter is incorrectly!"
            ]);
        }

        if (!isset($originRequest->email)) {
            return response()->json([
                "status" => 403,
                "message" => "Parameter is incorrectly!"
            ]);
        }

        return response()->json([
            "status" => 200,
            "data" => url('/payment/payment-confirm/' . $token)
        ]);
    }

    public function getListGateway()
    {
        $gateways = Gateway::select('id', 'name', 'image')->where('status', Gateway::GATEWAY_ACTIVE)->get();
        foreach ($gateways as $item) {
            $item->image = url("/") . "/assets/images/gateway/" . $item->image;
            $item->gatewayCurrency = GatewayCurrency::where('gateway_alias', $item->alias)->get();
        }

        return response()->json([
            "status" => 200,
            "data" => $gateways
        ]);
    }
}
