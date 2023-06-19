<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Dz2;
use App\Utils\AG\agUtils;
use App\Models\Web\SysConfig;
use App\Models\User;
use App\Models\WebReportZr;
use Carbon\Carbon;

function GetUrl($url, $ip=null, $timeout=20) {
    $ch = curl_init();

    //需要获取的URL地址，也可以在PHP的curl_init()函数中设置
    curl_setopt($ch, CURLOPT_URL,$url);

    //启用时会设置HTTP的method为GET，因为GET是默认是，所以只在被修改的情况下使用s
    curl_setopt($ch, CURLOPT_HTTPGET,true);

    //在启用CURLOPT_RETURNTRANSFER时候将获取数据返回
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);

    //bind to specific ip address if it is sent trough arguments
    if($ip)
    {
        //在外部网络接口中使用的名称，可以是一个接口名，IP或者主机名
        curl_setopt($ch,CURLOPT_INTERFACE,$ip);
    }

    //设置curl允许执行的最长秒数  $timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    //执行一个curl会话
    $result = curl_exec($ch);

    curl_close($ch);

    if(curl_errno($ch)) {
        return false;
    } else {
        return $result;
    }
}

class AGController extends Controller
{

    public function getAGGameAll(Request $request) {

        $response = [];
        $response['success'] = FALSE;
        $response['status'] = STATUS_BAD_REQUEST;

        try {

            $rules = [
                // "g_type" => "required|string",
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                $errorResponse = validation_error_response($validator->errors()->toArray());
                return response()->json($errorResponse, $response['status']);
            }

            $request_data = $request->all();

            $result = Dz2::where("Open", 1)
                ->where(function($query) {
                    $query->where("PlatformType", "AG")
                        ->orWhere("PlatformType", "YOPLAY")
                        ->orWhere("PlatformType", "XIN");
                })
                ->get();

            foreach($result as $item) {
                $item["ZH_Logo_File"] = "http://pic.pj6678.com/".$item["ZH_Logo_File"];
            }

            $response["data"] = $result;
            $response['message'] = "AG Game Data fetched successfully!";
            $response['success'] = TRUE;
            $response['status'] = STATUS_OK;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage() . ' Line No ' . $e->getLine() . ' in File' . $e->getFile();
            Log::error($e->getTraceAsString());
            $response['status'] = STATUS_GENERAL_ERROR;
        }

        return response()->json($response, $response['status']);
    }

    public function getAGUrl(Request $request) {

        $response = [];
        $response['success'] = FALSE;
        $response['status'] = STATUS_BAD_REQUEST;

        try {

            $rules = [
                "game_type" => "required",
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                $errorResponse = validation_error_response($validator->errors()->toArray());
                return response()->json($errorResponse, $response['status']);
            }

            $request_data = $request->all();
            $game_type = $request_data["game_type"];

            $user = $request->user();

            $ag_username=$user["AG_User"];
            $ag_password=$user["AG_Pass"];
            $username=$user['UserName'];
            $tp=$user['AG_Type'];

            $login_url = "";

            $sysConfig = SysConfig::all()->first();

            if ($username == "guest") {
                $AGUtils = new AGUtils($sysConfig);
                $ag_username=strtoupper('TEST'.$AGUtils->getpassword(10));
                $ag_password=strtoupper($AGUtils->getpassword(10));
                $result=$AGUtils->Addmember($ag_username,$ag_password,0);
                $results=$AGUtils->Deposit($ag_username,$ag_password,2000,'IN');
                $login_url=$AGUtils->getGameUrl($ag_username,$ag_password,"A",$_SERVER['HTTP_HOST'],0);
            } else {
                $AGUtils = new AGUtils($sysConfig);
                if ($ag_username==null || $ag_username=="") {
                    $WebCode =ltrim(trim($sysConfig['AG_User']));
                    if(!preg_match("/^[A-Za-z0-9]{4,12}$/", $user['UserName'])){ 
                        $ag_username=$WebCode.'_'.$AGUtils->getpassword(10);
                    }else{
                        $ag_username=$WebCode.'_'.trim($user['UserName']).$AGUtils->getpassword(1);
                    }
                    $ag_username=strtoupper($ag_username);
                    $ag_password=strtoupper($AGUtils->getpassword(10));
                    $result=$AGUtils->Addmember($ag_username,$ag_password,1);
                    return $result;
                    if ($result['info']=='0'){
                        User::where("UserName", $username)->update([
                            "AG_User" => $ag_username,
                            "AG_Pass" => $ag_password,
                        ]);
                    } else {
                        $response["message"] = '网络异常，请与在线客服联系！';
                        return response()->json($response, $response['status']);
                    }
                }

                $login_url=$AGUtils->getGameUrl($ag_username,$ag_password,$tp,$_SERVER['HTTP_HOST'],1,$game_type);
            }

            $response["data"] = $login_url;
            $response['message'] = "AG Game URL fetched successfully!";
            $response['success'] = TRUE;
            $response['status'] = STATUS_OK;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage() . ' Line No ' . $e->getLine() . ' in File' . $e->getFile();
            Log::error($e->getTraceAsString());
            $response['status'] = STATUS_GENERAL_ERROR;
        }

        return response()->json($response, $response['status']);
    }

    public function getAGTransaction(Request $request) {

        $response = [];
        $response['success'] = FALSE;
        $response['status'] = STATUS_BAD_REQUEST;

        try {

            $sysConfig = SysConfig::all()->first();
            $agentCode = $sysConfig['AG_User'];
            $key = '';
            $url = '';

            if ($agentCode < "M1" || $agentCode == "R9") {
                $url="http://ag.pj6678.com/ag_data.php?agentCod=$agentCode&key=";
            } else {
                $url="http://888.bbin-api8.com/ag_data.php?agentCod=$agentCode&key=";
            }

            $web_report_zr = WebReportZr::where("platformType", 'AGIN')
                ->orWhere("platformType", "XIN")
                ->select(DB::raw("max(VendorId) as VendorId"))
                ->first();

            if (isset($web_report_zr)) {
                $url = $url."&VendorId=".$web_report_zr->VendorId;
            }

            $htmlcode = GetUrl($url);
            $htmlcode=ltrim(trim($htmlcode));
            $data=explode("\r\n",$htmlcode);
            $allcount=0;
            $UserName_arr=array();

            foreach($data as $item) {
                $zr_data=json_decode($item);
                if(count($zr_data) <= 0) continue;
                $billNo = $zr_data->billNo;
                $playerName=$zr_data->playerName;
                $Type=$zr_data->Type;
                $GameType=$zr_data->GameType;
                $gameCode=$zr_data->gameCode;
                $netAmount=$zr_data->netAmount;
                $betTime=$zr_data->betTime;
                $betAmount=$zr_data->betAmount;
                $validBetAmount=$zr_data->validBetAmount;
                $playType=$zr_data->playType;
                $tableCode=$zr_data->tableCode;
                $loginIP=$zr_data->loginIP;
                $recalcuTime=$zr_data->recalcuTime;
                $platformType=$zr_data->platformType;
                $round=$zr_data->round;
                $VendorId=$zr_data->VendorId;
                $result=$zr_data->result;
                $UserName = "";
                if($UserName_arr[$playerName] == '') {
                    $user = User::where("AG_User", $playerName)->first();
                    $UserName=$user['UserName'];
                    $UserName_arr[$playerName]=$UserName;
                }else{
                    $UserName=$UserName_arr[$playerName];
                }

                $gameType=addslashes($GameType);
                $gameCode=addslashes($gameCode);
                $web_report_zr = WebReportZr::where("billNo", $billNo)
                    ->where("platformType", $platformType)->first();

                $new_data = array (
                    "billNo" => $billNo,
                    "UserName" => $UserName,
                    "playerName" => $playerName,
                    "Type" => $Type,
                    "gameType" => $gameType,
                    "gameCode" => $gameCode,
                    "netAmount" => $netAmount,
                    "betTime" => $betTime,
                    "betAmount" => $betAmount,
                    "validBetAmount" => $validBetAmount,
                    "playType" => $playType,
                    "tableCode" => $tableCode,
                    "loginIP" => $loginIP,
                    "recalcuTime" => $recalcuTime,
                    "round" => $round,
                    "platformType" => $platformType,
                    "VendorId" => $VendorId,
                    "Checked" => 1,
                );

                if (isset($web_report_zr)) {
                    $web_report_zr = new WebReportZr;
                    $web_report_zr->create($new_data);
                } else {
                    WebReportZr::where("billNo", $billNo)
                        ->where("platformType", $platformType)
                        ->update($new_data);
                }
            
                $AGUtils = new AGUtils($sysConfig);

                $user = User::where("UserName", $UserName)->first();                

                $balance= $AGUtils->getMoney($user["AG_User"], $user["AG_Pass"]);

                User::where("UserName", $UserName)->update([
                    "AG_Money" => $balance,
                ]);

            }

            $response['message'] = "AG Game Transaction saved successfully!";
            $response['success'] = TRUE;
            $response['status'] = STATUS_OK;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage() . ' Line No ' . $e->getLine() . ' in File' . $e->getFile();
            Log::error($e->getTraceAsString());
            $response['status'] = STATUS_GENERAL_ERROR;
        }

        return response()->json($response, $response['status']);
    }

    public function getYoplayTransaction(Request $request) {

        $response = [];
        $response['success'] = FALSE;
        $response['status'] = STATUS_BAD_REQUEST;

        try {

            $sysConfig = SysConfig::all()->first();
            $agentCode = $sysConfig['AG_User'];
            $key = '';
            $url = '';

            if ($agentCode < "M1" || $agentCode == "R9") {
                $url="http://ag.pj6678.com/yoplay_data.php?agentCod=$agentCode&key=";
            } else {
                $url="http://888.bbin-api8.com/yoplay_data.php?agentCod=$agentCode&key=";
            }

            $web_report_zr = WebReportZr::where("platformType", 'YOPLAY')
                ->select(DB::raw("max(VendorId) as VendorId"))
                ->first();

            if (isset($web_report_zr)) {
                $url = $url."&VendorId=".$web_report_zr->VendorId;
            }

            $htmlcode = GetUrl($url);
            $htmlcode=ltrim(trim($htmlcode));
            $data=explode("\r\n",$htmlcode);
            $allcount=0;
            $UserName_arr=array();

            foreach($data as $item) {
                $zr_data=json_decode($item);
                if(count($zr_data) <= 0) continue;
                $billNo = $zr_data->billNo;
                $playerName=$zr_data->playerName;
                $Type=$zr_data->Type;
                $GameType=$zr_data->GameType;
                $gameCode=$zr_data->gameCode;
                $netAmount=$zr_data->netAmount;
                $betTime=$zr_data->betTime;
                $betAmount=$zr_data->betAmount;
                $validBetAmount=$zr_data->validBetAmount;
                $playType=$zr_data->playType;
                $tableCode=$zr_data->tableCode;
                $loginIP=$zr_data->loginIP;
                $recalcuTime=$zr_data->recalcuTime;
                $platformType=$zr_data->platformType;
                $round=$zr_data->round;
                $VendorId=$zr_data->VendorId;
                $result=$zr_data->result;
                $UserName = "";
                if($UserName_arr[$playerName] == '') {
                    $user = User::where("AG_User", $playerName)->first();
                    $UserName=$user['UserName'];
                    $UserName_arr[$playerName]=$UserName;
                }else{
                    $UserName=$UserName_arr[$playerName];
                }

                $gameType=addslashes($GameType);
                $gameCode=addslashes($gameCode);
                $web_report_zr = WebReportZr::where("billNo", $billNo)
                    ->where("platformType", $platformType)->first();

                $new_data = array (
                    "billNo" => $billNo,
                    "UserName" => $UserName,
                    "playerName" => $playerName,
                    "Type" => $Type,
                    "gameType" => $gameType,
                    "gameCode" => $gameCode,
                    "netAmount" => $netAmount,
                    "betTime" => $betTime,
                    "betAmount" => $betAmount,
                    "validBetAmount" => $validBetAmount,
                    "playType" => $playType,
                    "tableCode" => $tableCode,
                    "loginIP" => $loginIP,
                    "recalcuTime" => $recalcuTime,
                    "round" => $round,
                    "platformType" => $platformType,
                    "VendorId" => $VendorId,
                    "Checked" => 1,
                );

                if (isset($web_report_zr)) {
                    $web_report_zr = new WebReportZr;
                    $web_report_zr->create($new_data);
                } else {
                    WebReportZr::where("billNo", $billNo)
                        ->where("platformType", $platformType)
                        ->update($new_data);
                }
            
                $AGUtils = new AGUtils($sysConfig);

                $user = User::where("UserName", $UserName)->first();                

                $balance= $AGUtils->getMoney($user["AG_User"], $user["AG_Pass"]);

                User::where("UserName", $UserName)->update([
                    "AG_Money" => $balance,
                ]);

            }

            $response['message'] = "YOPLAY Game Transaction saved successfully!";
            $response['success'] = TRUE;
            $response['status'] = STATUS_OK;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage() . ' Line No ' . $e->getLine() . ' in File' . $e->getFile();
            Log::error($e->getTraceAsString());
            $response['status'] = STATUS_GENERAL_ERROR;
        }

        return response()->json($response, $response['status']);
    }
}