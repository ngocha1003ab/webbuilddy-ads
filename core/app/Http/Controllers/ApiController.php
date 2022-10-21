<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Gateway;
use App\Models\GatewayCurrency;
use App\Models\User;
use App\Models\GeneralSetting;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\AdminNotification;

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

    // SIGN-ON AND CONFIRM PAYMENT (WEB BROWSER)
    public function returnConfirmPage(Request $request)
    {
        try {
            $token = $request->token;

            // Single sign-on
            $originRequest = json_decode(Crypt::decryptString($token));
            $this->signgleSignIn($originRequest);

            DB::beginTransaction();
            $amount = $originRequest->amount;
            $methodCode = $originRequest->method_code;
            $currency = $originRequest->currency;

            $user = auth()->user();
            $gate = GatewayCurrency::whereHas('method', function ($gate) {
                $gate->where('status', 1);
            })->where('method_code', $methodCode)->where('currency', $currency)->first();

            if (!$gate) {
                return view('errors.419');
            }

            if ($gate->min_amount > $amount || $gate->max_amount < $amount) {
                return view('errors.419');
            }

            $charge = $gate->fixed_charge + ($amount * $gate->percent_charge / 100);
            $payable = $amount + $charge;
            $final_amo = $payable * $gate->rate;

            $data = new Deposit();
            $data->user_id = $user->id;
            $data->method_code = $gate->method_code;
            $data->method_currency = strtoupper($gate->currency);
            $data->amount = $amount;
            $data->charge = $charge;
            $data->rate = $gate->rate;
            $data->final_amo = $final_amo;
            $data->btc_amo = 0;
            $data->btc_wallet = "";
            $data->trx = getTrx();
            $data->try = 0;
            $data->status = 0;
            $data->save();
            session()->put('Track', $data->trx);

            DB::commit();
            return view('webbuilddy.confirm-payment', [
                'amount' => $amount,
                'final_amo' => $final_amo,
                'charge' => $charge,
                'gate' => $gate
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return view('errors.419');
        }
    }


    public function depositConfirm()
    {
        $track = session()->get('Track');
        $deposit = Deposit::where('trx', $track)->where('status', 0)->orderBy('id', 'DESC')->with('gateway')->firstOrFail();

        if ($deposit->method_code >= 1000) {
            $this->userDataUpdate($deposit);
            $notify[] = ['success', 'Your deposit request is queued for approval.'];
            return back()->withNotify($notify);
        }


        $dirName = $deposit->gateway->alias;
        $new = __NAMESPACE__ . '\\' . $dirName . '\\ProcessController';

        $data = $new::process($deposit);
        $data = json_decode($data);


        if (isset($data->error)) {
            $notify[] = ['error', $data->message];
            return redirect()->route(gatewayRedirectUrl())->withNotify($notify);
        }
        if (isset($data->redirect)) {
            return redirect($data->redirect_url);
        }

        // for Stripe V3
        if (@$data->session) {
            $deposit->btc_wallet = $data->session->id;
            $deposit->save();
        }

        $pageTitle = 'Payment Confirm';
        return view($this->activeTemplate . $data->view, compact('data', 'pageTitle', 'deposit'));
    }

    public static function userDataUpdate($trx)
    {
        $general = GeneralSetting::first();
        $data = Deposit::where('trx', $trx)->first();
        if ($data->status == 0) {
            $data->status = 1;
            $data->save();

            $user = User::find($data->user_id);
            $user->balance += $data->amount;
            $user->save();

            $transaction = new Transaction();
            $transaction->user_id = $data->user_id;
            $transaction->amount = $data->amount;
            $transaction->post_balance = $user->balance;
            $transaction->charge = $data->charge;
            $transaction->trx_type = '+';
            $transaction->details = 'Deposit Via ' . $data->gatewayCurrency()->name;
            $transaction->trx = $data->trx;
            $transaction->save();

            $adminNotification = new AdminNotification();
            $adminNotification->user_id = $user->id;
            $adminNotification->title = 'Deposit successful via ' . $data->gatewayCurrency()->name;
            $adminNotification->click_url = urlPath('admin.deposit.successful');
            $adminNotification->save();

            notify($user, 'DEPOSIT_COMPLETE', [
                'method_name' => $data->gatewayCurrency()->name,
                'method_currency' => $data->method_currency,
                'method_amount' => showAmount($data->final_amo),
                'amount' => showAmount($data->amount),
                'charge' => showAmount($data->charge),
                'currency' => $general->cur_text,
                'rate' => showAmount($data->rate),
                'trx' => $data->trx,
                'post_balance' => showAmount($user->balance)
            ]);
        }
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
