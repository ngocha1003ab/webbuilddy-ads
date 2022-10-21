<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Gateway;

class ApiController extends Controller
{
    public function getListGateway(){
        $gateways = Gateway::select('id','name', 'image')->where('status', Gateway::GATEWAY_ACTIVE)->get();
        foreach($gateways as $item){
            $item->image = url("/") . "/assets/images/gateway/" . $item->image;
        }

        return response()->json([
            "status" => 200,
            "data" => $gateways
        ]);
    }
}
