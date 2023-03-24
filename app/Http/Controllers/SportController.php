<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sport;
use App\Models\User;
use App\Models\Web\Report;
use App\Models\Config;
use App\Models\Web\MoneyLog;
use App\Models\WebReportTemp;
use App\Utils\Utils;
use DB;
use Validator;

include("include.php");

class SportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
    }
    public function index(Request $request)
    {
        //
        $limit = $request->query('limit');
        if ($limit == '' || $limit == null) $limit = 10;
        return Sport::paginate($limit);
    }

    public function match_count()
    {
        return Sport::count();
    }

    public function getItem(Request $request)
    {
        $id = $request->post('id');
        $data = Sport::select('MID', 'Type', 'M_Date', 'M_Time', 'MB_Team', 'TG_Team', 'MB_Inball', 'TG_Inball', 'MB_Inball_HR', 'TG_Inball_HR', 'M_League')
            ->where('MID', $id)->get();
        return $data;
    }

    public function getItems(Request $request)
    {
        $m_date = $request['m_date'] ?? date('Y-m-d');
        $type = $request['type'] ?? 'FT';
        $offset = $request['offset'] ?? 0;
        $limit = $request['limit'] ?? 20;

        $mids = Report::select('MID')->where('M_Date', $m_date)->get();

        $items = Sport::select('MID', 'M_Date', 'M_Time', 'MB_Team', 'TG_Team', 'MB_Inball', 'TG_Inball', 'MB_Inball_HR', 'TG_Inball_HR', 'Cancel', 'Checked', 'Open', 'M_League')
            ->where('M_Date', $m_date)
            ->where('Type', $type);
        if ($request['score'] === 0)
            $items = $items->where('Score', 0);
        else $items = $items->where('Score', '!=', 0);
        $items = $items->whereIn('MID', $mids)
            ->skip($offset)
            ->take($limit)
            ->get();
        return $items;
    }

    public function saveScore(Request $request)
    {
        $item = $request->post('item');
        $mb_inball = $item['MB_Inball'];
        $tg_inball = $item['TG_Inball'];
        $mb_inball_hr = $item['MB_Inball_HR'];
        $tg_inball_hr = $item['TG_Inball_HR'];
        $gtype = $item['Type'];
        $gid = $request->post('id');
        Sport::where('Type', $gtype)->where('MID', $gid)
            ->update([
                'MB_Inball' => $mb_inball,
                'MB_Inball_HR' => $mb_inball_hr,
                'TG_Inball' => $tg_inball,
                'TG_Inball_HR' => $tg_inball_hr
            ]);
    }

    public function saveScore1(Request $request)
    {
        $id = $request->id;
        $data = $request->item;
        $item = Sport::where('MID', $id)->get();
        $m_date = date('Y-m-d');

        $bc_arr = array('VRC', 'VRH', 'VMN', 'VMC', 'VMH', 'VOUH', 'VOUC', 'VRMH', 'VRMC', 'VRMN', 'VROUH', 'VROUC', 'VRRH', 'VRRC');
        $Score_arr = array();
        $Score_arr[1] = '取消';
        $Score_arr[2] = '赛事腰斩';
        $Score_arr[3] = '赛事改期';
        $Score_arr[4] = '赛事延期';
        $Score_arr[5] = '赛事延赛';
        $Score_arr[6] = '赛事取消';
        $Score_arr[7] = '赛事无PK加时';
        $Score_arr[8] = '球员弃权';
        $Score_arr[9] = '队名错误';
        $Score_arr[10] = '主客场错误';
        $Score_arr[11] = '先发投手更换';
        $Score_arr[12] = '选手更换';
        $Score_arr[13] = '联赛名称错误';
        $Score_arr[19] = '提前开赛';

        $m_result1 = Sport::select('MID', 'MB_MID', 'TG_MID', 'MB_Team', 'TG_Team', 'MB_Inball', 'TG_Inball', 'MB_Inball_HR', 'TG_Inball_HR', 'M_Start')
            ->where('Type', 'FT')
            ->where('M_Date', $m_date)
            ->where('MB_Inball', '')
            ->where('Score', 0)
            ->orderBy('M_Start', 'asc')
            ->orderBy('MID', 'asc')
            ->get();

        foreach ($m_result1 as $key => $mrow) {
            $gid = $mrow['MID'];
            // ProcessUpdate($gid, 3); // Stops multi-processing
            $mb_in_score = $mrow['MB_Inball'];
            $tg_in_score = $mrow['TG_Inball'];
            $mb_in_score_v = $mrow['MB_Inball_HR'];
            $tg_in_score_v = $mrow['TG_Inball_HR'];
            $result = Report::select('ID', 'MID', 'OrderID', 'Active', 'M_Name', 'LineType', 'OpenType', 'ShowType', 'Mtype', 'Gwin', 'VGOLD', 'TurnRate', 'BetType', 'M_Place', 'M_Rate', 'Middle', 'BetScore', 'A_Rate', 'B_Rate', 'C_Rate', 'D_Rate', 'A_Point', 'B_Point', 'C_Point', 'D_Point', 'Pay_Type', 'Checked')
                ->whereRaw('FIND_IN_SET(?, MID) > 0', [$gid])
                ->whereIn('Active', [1, 11])
                ->where('LineType', '!=', 8)
                ->where('Cancel', '!=', 1)
                ->orderBy('LineType', 'asc')
                ->get();

            foreach ($result as $row_index => $row) {
                $mtype = $row['Mtype'];
                $id = $row['ID'];
                $user = $row['M_Name'];
                switch ($row['LineType']) {
                    case 1:
                        $graded = Utils::win_chk($mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 2:
                        $graded = Utils::odds_letb($mb_in_score, $tg_in_score, $row['ShowType'], $row['M_Place'], $row['Mtype']);
                        break;
                    case 3:
                        $graded = Utils::odds_dime($mb_in_score, $tg_in_score, $row['M_Place'], $row['Mtype']);
                        break;
                    case 4:
                        $graded = Utils::odds_pd($mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 5:
                        $graded = Utils::odds_eo($mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 6:
                        $graded = Utils::odds_t($mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 7:
                        $graded = Utils::odds_half($mb_in_score_v, $tg_in_score_v, $mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 9:
                        $score = explode('<FONT color=red><b>', $row['Middle']);
                        $msg = explode("</b></FONT><br>", $score[1]);
                        $bcd = explode(":", $msg[0]);
                        $m_in = $bcd[0];
                        $t_in = $bcd[1];
                        if ($row['ShowType'] == 'H') {
                            $mbinscore1 = $mb_in_score - $m_in;
                            $tginscore1 = $tg_in_score - $t_in;
                        } else {
                            $mbinscore1 = $mb_in_score - $t_in;
                            $tginscore1 = $tg_in_score - $m_in;
                        }
                        $graded = Utils::odds_letb_rb($mbinscore1, $tginscore1, $row['ShowType'], $row['M_Place'], $row['Mtype']);
                        break;
                    case 19:
                        $score = explode('<FONT color=red><b>', $row['Middle']);
                        $msg = explode("</b></FONT><br>", $score[1]);
                        $bcd = explode(":", $msg[0]);
                        $m_in = $bcd[0];
                        $t_in = $bcd[1];
                        if ($row['ShowType'] == 'H') {
                            $mbinscore1 = $mb_in_score_v - $m_in;
                            $tginscore1 = $tg_in_score_v - $t_in;
                        } else {
                            $mbinscore1 = $mb_in_score_v - $t_in;
                            $tginscore1 = $tg_in_score_v - $m_in;
                        }
                        $graded = Utils::odds_letb_vrb($mbinscore1, $tginscore1, $row['ShowType'], $row['M_Place'], $row['Mtype']);
                        break;
                    case 10:
                        $graded = Utils::odds_dime_rb($mb_in_score, $tg_in_score, $row['M_Place'], $row['Mtype']);
                        break;
                    case 50:
                        $graded = Utils::odds_dime_rb($mb_in_score, $tg_in_score, $row['M_Place'], $row['Mtype']);
                        break;
                    case 20:
                        $graded = Utils::odds_dime_vrb($mb_in_score_v, $tg_in_score_v, $row['M_Place'], $row['Mtype']);
                        break;
                    case 21:
                        $graded = Utils::win_chk_rb($mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 31:
                        $graded = Utils::win_chk_vrb($mb_in_score_v, $tg_in_score_v, $row['Mtype']);
                        break;
                    case 11:
                        $graded = Utils::win_chk_v($mb_in_score_v, $tg_in_score_v, $row['Mtype']);
                        break;
                    case 12:
                        $graded = Utils::odds_letb_v($mb_in_score_v, $tg_in_score_v, $row['ShowType'], $row['M_Place'], $row['Mtype']);
                        break;
                    case 13:
                        $graded = Utils::odds_dime_v($mb_in_score_v, $tg_in_score_v, $row['M_Place'], $row['Mtype']);
                        break;
                    case 14:
                        $graded = Utils::odds_pd_v($mb_in_score_v, $tg_in_score_v, $row['Mtype']);
                        break;
                }
                //echo $graded."-----------<br>";
                if ($row['M_Rate'] < 0) {
                    $num = str_replace("-", "", $row['M_Rate']);
                } else if ($row['M_Rate'] > 0) {
                    $num = 1;
                }
                switch ($graded) {
                    case 1:
                        $g_res = $row['Gwin'];
                        break;
                    case 0.5:
                        $g_res = $row['Gwin'] * 0.5;
                        break;
                    case -0.5:
                        $g_res = -$row['BetScore'] * 0.5 * $num;
                        break;
                    case -1:
                        $g_res = -$row['BetScore'] * $num;
                        break;
                    case 0:
                        $g_res = 0;
                        break;
                }
                /*$vgold=abs($graded)*$row['BetScore'];
                $betscore=$row['BetScore'];
                $turn=abs($graded)*$row['BetScore']*$row['TurnRate']/100;*/
                $betscore = $row['BetScore'];  //投注金额
                $vgold = $row['VGOLD']; //有效金额
                if (empty($vgold) or $vgold <> 0) {
                    $vgold = abs($graded) * $row['BetScore'];
                } else {
                    $vgold = 0;
                }
                $turn = abs($graded) * $vgold * $row['TurnRate'] / 100;  //返水

                $d_point = $row['D_Point'] / 100;
                $c_point = $row['C_Point'] / 100;
                $b_point = $row['B_Point'] / 100;
                $a_point = $row['A_Point'] / 100;

                $members = $g_res + $turn; //和会员结帐的金额
                $agents = $g_res * (1 - $d_point) + (1 - $d_point) * $row['D_Rate'] / 100 * $row['BetScore'] * abs($graded); //上缴总代理结帐的金额
                $world = $g_res * (1 - $c_point - $d_point) + (1 - $c_point - $d_point) * $row['C_Rate'] / 100 * $row['BetScore'] * abs($graded); //上缴股东结帐
                if (1 - $b_point - $c_point - $d_point != 0) {
                    $corprator = $g_res * (1 - $b_point - $c_point - $d_point) + (1 - $b_point - $c_point - $d_point) * $row['B_Rate'] / 100 * $row['BetScore'] * abs($graded); //上缴公司结帐
                } else {
                    $corprator = $g_res * ($b_point + $a_point) + ($b_point + $a_point) * $row['B_Rate'] / 100 * $row['BetScore'] * abs($graded); //和公司结帐
                }
                $super = $g_res * $a_point + $a_point * $row['A_Rate'] / 100 * $row['BetScore'] * abs($graded); //和公司结帐
                $agent = $g_res * 1 + 1 * $row['D_Rate'] / 100 * $row['BetScore'] * abs($graded); //公司退水帐目


                $previousAmount = Utils::GetField($user, 'Money');
                $user_id = Utils::GetField($user, 'ID');
                $datetime = date("Y-m-d H:i:s", time() + 12 * 3600);
                $q1 = 0;
                if (in_array($mtype, $bc_arr)) {
                    $isQC = 0;
                } else {
                    $isQC = 1;
                }  //是否全场赛事注单
                if ($mb_in_score_v < 0 and $mb_in_score < 0) {
                    $BiFen = "半场:" . $Score_arr[abs($mb_in_score_v)] . " 全场:" . $Score_arr[abs($mb_in_score)];
                } elseif ($mb_in_score < 0) {
                    $BiFen = "半场:$mb_in_score_v-$tg_in_score_v 全场:" . $Score_arr[abs($mb_in_score)];
                } else {
                    $BiFen = "半场:$mb_in_score_v-$tg_in_score_v 全场:$mb_in_score-$tg_in_score";
                }
                if (($mb_in_score < 0 and $isQC == 1) or $mb_in_score_v < 0) {  //取消注单  全场比分为“取消”只取消全场  半场比分取消：全部取消
                    if ($row['Checked'] == 0) {
                        if ($row['Pay_Type'] == 1) {
                            $cash = $row['BetScore'];
                            Utils::ProcessUpdate($user);  //防止并发
                            $q1 = User::where('UserName', $user)->increment('Money', $cash)->get();
                        }
                    }

                    if ($q1 == 1) {
                        $currentAmount = Utils::GetField($user, 'Money');
                        $Order_Code = $row['OrderID'];
                        $new_log = new MoneyLog;
                        $new_log->user_id = $user_id;
                        $new_log->order_num = "$Order_Code";
                        $new_log->about =  "loginname" . "系统取消赛事($BiFen)<br>MID:" . $row['MID'] . "<br>RID:" . $row['ID'];
                        $new_log->update_time = $datetime;
                        $new_log->type = $row['Middle'];
                        $new_log->order_value = $cash;
                        $new_log->assets = $previousAmount;
                        $new_log->balance = $currentAmount;
                        $new_log->save();

                        $for_update = Report::where('ID', $id)->whereIn('active', [1, 11])->where('LineType', '!=', 8)
                            ->update([
                                'VGOLD' => 0,
                                'M_Result' => 0,
                                'D_Result' => 0,
                                'C_Result' => 0,
                                'B_Result' => 0,
                                'A_Result' => 0,
                                'T_Result' => 0,
                                'Cancel' => 1,
                                'Checked' => 1,
                                'Confirmed' => $mb_in_score
                            ]);
                    }
                } else {  //结算注单
                    if ($row['Checked'] == 0) {
                        if ($row['Pay_Type'] == 1) {
                            $cash = $row['BetScore'] + $members;
                            //ProcessUpdate($user);  //防止并发
                            $mysql = "update web_member_data set Money=Money+$cash where UserName='$user'";
                            $q1 = User::where('UserName', $user)->increment('Money', $cash);
                        }
                    }
                    if ($q1 == 1 or $cash == 0) {
                        $currentAmount = Utils::GetField($user, 'Money');
                        $Order_Code = $row['OrderID'];
                        $new_log = new MoneyLog;
                        $new_log->user_id = $user_id;
                        $new_log->order_num = "$Order_Code";
                        $new_log->update_time = $datetime;
                        $new_log->type = $row['Middle'];
                        $new_log->order_value = $cash;
                        $new_log->assets = $previousAmount;
                        $new_log->balance = $currentAmount;
                        if ($cash < $row['BetScore']) {
                            $new_log->about =  "系统取消赛事($BiFen)<br>输<br>MID:" . $row['MID'] . "<br>RID:" . $row['ID'];
                        } else {
                            $new_log->about =  "系统取消赛事($BiFen)<br>MID:" . $row['MID'] . "<br>RID:" . $row['ID'];
                        }
                        $new_log->save();

                        $for_update = Report::where('ID', $id)
                            ->update([
                                'VGOLD' => 0,
                                'M_Result' => $members,
                                'D_Result' => $agents,
                                'C_Result' => $world,
                                'B_Result' => $corprator,
                                'A_Result' => $super,
                                'T_Result' => $agent,
                                'Checked' => 1
                            ]);
                    }
                }
            }
            Sport::where('Type', 'FT')->where('MID', $gid)->update(['Score' => 1]);
        }
    }

    public function checkScore(Request $request)
    {
        $gid = $request['id'];
        $new_item = $request['item'];
        $type = $new_item['type'];
        $item = Sport::where('MID', $gid)->first();
        $mb_in_score = $new_item['MB_Inball'];
        $tg_in_score = $new_item['TG_Inball'];
        $mb_in_score_v = $new_item['MB_Inball_HR'];
        $tg_in_score_v = $new_item['TG_Inball_HR'];
        $data = array(); // return data
        if ($type == 'FT') {
            if (trim($mb_in_score) == "-" || trim($tg_in_score) == "-" || trim($mb_in_score) == "" || trim($tg_in_score) == "" || trim($mb_in_score) == "－" || trim($tg_in_score) == "－") {
                Sport::where('MID', $gid)->update(['MB_Inball' => $mb_in_score, 'TG_Inball' => $tg_in_score]);
            }
            if ($mb_in_score < 0 or $tg_in_score < 0 or $mb_in_score_v < 0 or $tg_in_score_v < 0) {
                Sport::where('MID', $gid)->update([
                    'MB_Inball' => $mb_in_score,
                    'TG_Inball' => $tg_in_score,
                    'MB_Inball_HR' => $mb_in_score_v,
                    'TG_Inball_HR' => $tg_in_score_v
                ]);
            }

            Utils::ProcessUpdate($gid, 3);

            $result = Sport::where('MID', $gid)->where('MB_Inball', '')->where('TG_Inball', '')->count();
            if ($result == 0) {
                return [
                    'code' => 'settled',
                    'message' => '本场赛事已经结算!'
                ];
            }

            //需直接传递过来比分：上半和全场，可根据实际情况分别分批传递
            $bc_arr = array('VRC', 'VRH', 'VMN', 'VMC', 'VMH', 'VOUH', 'VOUC', 'VRMH', 'VRMC', 'VRMN', 'VROUH', 'VROUC', 'VRRH', 'VRRC');
            $Score_arr = array();
            $Score_arr[1] = '取消';
            $Score_arr[2] = '赛事腰斩';
            $Score_arr[3] = '赛事改期';
            $Score_arr[4] = '赛事延期';
            $Score_arr[5] = '赛事延赛';
            $Score_arr[6] = '赛事取消';
            $Score_arr[7] = '赛事无PK加时';
            $Score_arr[8] = '球员弃权';
            $Score_arr[9] = '队名错误';
            $Score_arr[10] = '主客场错误';
            $Score_arr[11] = '先发投手更换';
            $Score_arr[12] = '选手更换';
            $Score_arr[13] = '联赛名称错误';
            $Score_arr[19] = '提前开赛';

            $result = Report::select('ID', 'MID', 'OrderID', 'Active', 'M_Name', 'LineType', 'OpenType', 'ShowType', 'Mtype', 'Gwin', 'VGOLD', 'TurnRate', 'BetType', 'M_Place', 'M_Rate', 'Middle', 'BetScore', 'A_Rate', 'B_Rate', 'C_Rate', 'D_Rate', 'A_Point', 'B_Point', 'C_Point', 'D_Point', 'Pay_Type', 'Checked')
                ->whereRaw('FIND_IN_SET(?, MID) > 0', [$gid])
                ->whereIn('Active', [1, 11])
                ->where('LineType', '!=', 8)
                ->where('Cancel', '!=', 1)
                ->orderBy('LineType', 'asc')
                ->get();

            foreach ($result as $row_index => $row) {
                $mtype = $row['Mtype'];
                $id = $row['ID'];
                $user = $row['M_Name'];
                switch ($row['LineType']) {
                    case 1:
                        $graded = Utils::win_chk($mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 2:
                        $graded = Utils::odds_letb($mb_in_score, $tg_in_score, $row['ShowType'], $row['M_Place'], $row['Mtype']);
                        break;
                    case 3:
                        $graded = Utils::odds_dime($mb_in_score, $tg_in_score, $row['M_Place'], $row['Mtype']);
                        break;
                    case 4:
                        $graded = Utils::odds_pd($mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 5:
                        $graded = Utils::odds_eo($mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 6:
                        $graded = Utils::odds_t($mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 7:
                        $graded = Utils::odds_half($mb_in_score_v, $tg_in_score_v, $mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 9:
                        $score = explode('<FONT color=red><b>', $row['Middle']);
                        $msg = explode("</b></FONT><br>", $score[1]);
                        $bcd = explode(":", $msg[0]);
                        $m_in = $bcd[0];
                        $t_in = $bcd[1];
                        if ($row['ShowType'] == 'H') {
                            $mbinscore1 = $mb_in_score - $m_in;
                            $tginscore1 = $tg_in_score - $t_in;
                        } else {
                            $mbinscore1 = $mb_in_score - $t_in;
                            $tginscore1 = $tg_in_score - $m_in;
                        }
                        $graded = Utils::odds_letb_rb($mbinscore1, $tginscore1, $row['ShowType'], $row['M_Place'], $row['Mtype']);
                        break;
                    case 19:
                        $score = explode('<FONT color=red><b>', $row['Middle']);
                        $msg = explode("</b></FONT><br>", $score[1]);
                        $bcd = explode(":", $msg[0]);
                        $m_in = $bcd[0];
                        $t_in = $bcd[1];
                        if ($row['ShowType'] == 'H') {
                            $mbinscore1 = $mb_in_score_v - $m_in;
                            $tginscore1 = $tg_in_score_v - $t_in;
                        } else {
                            $mbinscore1 = $mb_in_score_v - $t_in;
                            $tginscore1 = $tg_in_score_v - $m_in;
                        }
                        $graded = Utils::odds_letb_vrb($mbinscore1, $tginscore1, $row['ShowType'], $row['M_Place'], $row['Mtype']);
                        break;
                    case 10:
                        $graded = Utils::odds_dime_rb($mb_in_score, $tg_in_score, $row['M_Place'], $row['Mtype']);
                        break;
                    case 50:
                        $graded = Utils::odds_dime_rb($mb_in_score, $tg_in_score, $row['M_Place'], $row['Mtype']);
                        break;
                    case 20:
                        $graded = Utils::odds_dime_vrb($mb_in_score_v, $tg_in_score_v, $row['M_Place'], $row['Mtype']);
                        break;
                    case 21:
                        $graded = Utils::win_chk_rb($mb_in_score, $tg_in_score, $row['Mtype']);
                        break;
                    case 31:
                        $graded = Utils::win_chk_vrb($mb_in_score_v, $tg_in_score_v, $row['Mtype']);
                        break;
                    case 11:
                        $graded = Utils::win_chk_v($mb_in_score_v, $tg_in_score_v, $row['Mtype']);
                        break;
                    case 12:
                        $graded = Utils::odds_letb_v($mb_in_score_v, $tg_in_score_v, $row['ShowType'], $row['M_Place'], $row['Mtype']);
                        break;
                    case 13:
                        $graded = Utils::odds_dime_v($mb_in_score_v, $tg_in_score_v, $row['M_Place'], $row['Mtype']);
                        break;
                    case 14:
                        $graded = Utils::odds_pd_v($mb_in_score_v, $tg_in_score_v, $row['Mtype']);
                        break;
                }
                //echo $graded."-----------<br>";
                $num = 0;
                if (floatval($row['M_Rate']) < 0) {
                    $num = str_replace("-", "", $row['M_Rate']);
                } else if (floatval($row['M_Rate']) > 0) {
                    $num = 1;
                }
                switch ($graded) {
                    case 1:
                        $g_res = $row['Gwin'];
                        break;
                    case 0.5:
                        $g_res = $row['Gwin'] * 0.5;
                        break;
                    case -0.5:
                        $g_res = -$row['BetScore'] * 0.5 * $num;
                        break;
                    case -1:
                        $g_res = -$row['BetScore'] * $num;
                        break;
                    case 0:
                        $g_res = 0;
                        break;
                }
                /*$vgold=abs($graded)*$row['BetScore'];
                $betscore=$row['BetScore'];
                $turn=abs($graded)*$row['BetScore']*$row['TurnRate']/100;*/
                $betscore = $row['BetScore'];  //投注金额
                $vgold = $row['VGOLD']; //有效金额
                if (empty($vgold) or $vgold <> 0) {
                    $vgold = abs($graded) * $row['BetScore'];
                } else {
                    $vgold = 0;
                }
                $turn = abs($graded) * $vgold * intval($row['TurnRate']) / 100;  //返水

                $d_point = intval($row['D_Point']) / 100;
                $c_point = intval($row['C_Point']) / 100;
                $b_point = intval($row['B_Point']) / 100;
                $a_point = intval($row['A_Point']) / 100;

                $members = $g_res + $turn; //和会员结帐的金额
                $agents = $g_res * (1 - $d_point) + (1 - $d_point) * intval($row['D_Rate']) / 100 * intval($row['BetScore']) * abs($graded); //上缴总代理结帐的金额
                $world = $g_res * (1 - $c_point - $d_point) + (1 - $c_point - $d_point) * intval($row['C_Rate']) / 100 * $row['BetScore'] * abs($graded); //上缴股东结帐
                if (1 - $b_point - $c_point - $d_point != 0) {
                    $corprator = $g_res * (1 - $b_point - $c_point - $d_point) + (1 - $b_point - $c_point - $d_point) * intval($row['B_Rate']) / 100 * $row['BetScore'] * abs($graded); //上缴公司结帐
                } else {
                    $corprator = $g_res * ($b_point + $a_point) + ($b_point + $a_point) * intval($row['B_Rate']) / 100 * $row['BetScore'] * abs($graded); //和公司结帐
                }
                $super = $g_res * $a_point + $a_point * intval($row['A_Rate']) / 100 * $row['BetScore'] * abs($graded); //和公司结帐
                $agent = $g_res * 1 + 1 * intval($row['D_Rate']) / 100 * $row['BetScore'] * abs($graded); //公司退水帐目


                $previousAmount = Utils::GetField($user, 'Money');
                $user_id = Utils::GetField($user, 'id');
                $datetime = date("Y-m-d H:i:s", time() + 12 * 3600);
                $q1 = 0;
                if (in_array($mtype, $bc_arr)) {
                    $isQC = 0;
                } else {
                    $isQC = 1;
                }  //是否全场赛事注单
                if ($mb_in_score_v < 0 and $mb_in_score < 0) {
                    $BiFen = "半场:" . $Score_arr[abs($mb_in_score_v)] . " 全场:" . $Score_arr[abs($mb_in_score)];
                } elseif ($mb_in_score < 0) {
                    $BiFen = "半场:$mb_in_score_v-$tg_in_score_v 全场:" . $Score_arr[abs($mb_in_score)];
                } else {
                    $BiFen = "半场:$mb_in_score_v-$tg_in_score_v 全场:$mb_in_score-$tg_in_score";
                }
                if (($mb_in_score < 0 and $isQC == 1) or $mb_in_score_v < 0) {  //取消注单  全场比分为“取消”只取消全场  半场比分取消：全部取消
                    if ($row['Checked'] == 0) {
                        if ($row['Pay_Type'] == 1) {
                            $cash = $row['BetScore'];
                            Utils::ProcessUpdate($user);  //防止并发
                            $q1 = User::where('UserName', $user)->increment('Money', $cash)->get();
                        }
                    }

                    if ($q1 == 1) {
                        $currentAmount = Utils::GetField($user, 'Money');
                        $Order_Code = $row['OrderID'];
                        $new_log = new MoneyLog;
                        $new_log->user_id = $user_id;
                        $new_log->order_num = "$Order_Code";
                        $new_log->about =  "loginname" . "系统取消赛事($BiFen)<br>MID:" . $row['MID'] . "<br>RID:" . $row['ID'];
                        $new_log->update_time = $datetime;
                        $new_log->type = $row['Middle'];
                        $new_log->order_value = $cash;
                        $new_log->assets = $previousAmount;
                        $new_log->balance = $currentAmount;
                        $new_log->save();

                        $for_update = Report::where('ID', $id)->whereIn('active', [1, 11])->where('LineType', '!=', 8)
                            ->update([
                                'VGOLD' => 0,
                                'M_Result' => 0,
                                'D_Result' => 0,
                                'C_Result' => 0,
                                'B_Result' => 0,
                                'A_Result' => 0,
                                'T_Result' => 0,
                                'Cancel' => 1,
                                'Checked' => 1,
                                'Confirmed' => $mb_in_score
                            ]);
                    }
                } else {  //结算注单
                    $cash = 0;
                    if ($row['Checked'] == 0) {
                        if ($row['Pay_Type'] == 1) {
                            $cash = $row['BetScore'] + $members;
                            Utils::ProcessUpdate($user);  //防止并发
                            $q1 = User::where('UserName', $user)->increment('Money', $cash);
                        }
                    }
                    if ($q1 == 1 or $cash == 0) {
                        $currentAmount = Utils::GetField($user, 'Money');
                        $Order_Code = $row['OrderID'];
                        $new_log = new MoneyLog;
                        $new_log->user_id = $user_id;
                        $new_log->order_num = "$Order_Code";
                        $new_log->update_time = $datetime;
                        $new_log->type = $row['Middle'];
                        $new_log->order_value = $cash;
                        $new_log->assets = $previousAmount;
                        $new_log->balance = $currentAmount;
                        if ($cash < $row['BetScore']) {
                            $new_log->about =  "系统取消赛事($BiFen)<br>输<br>MID:" . $row['MID'] . "<br>RID:" . $row['ID'];
                        } else {
                            $new_log->about =  "系统取消赛事($BiFen)<br>MID:" . $row['MID'] . "<br>RID:" . $row['ID'];
                        }
                        $new_log->save();

                        $for_update = Report::where('ID', $id)
                            ->update([
                                'VGOLD' => $vgold,
                                'M_Result' => $members,
                                'D_Result' => $agents,
                                'C_Result' => $world,
                                'B_Result' => $corprator,
                                'A_Result' => $super,
                                'T_Result' => $agent,
                                'Checked' => 1
                            ]);
                    }
                }
            }
            Sport::where('Type', 'FT')->where('MID', $gid)->update(['Score' => 1]);

            switch ($row['OddsType']) {
                case 'H':
                    $Odds = '<BR><font color =green>' . Utils::Rep_HK . '</font>';
                    break;
                case 'M':
                    $Odds = '<BR><font color =green>' . Utils::Rep_Malay . '</font>';
                    break;
                case 'I':
                    $Odds = '<BR><font color =green>' . Utils::Rep_Indo . '</font>';
                    break;
                case 'E':
                    $Odds = '<BR><font color =green>' . Utils::Rep_Euro . '</font>';
                    break;
                case '':
                    $Odds = '';
                    break;
            }

            $time = $row['BetTime'];
            $times = date("Y-m-d", $time) . '<br>' . date("H:i:s", $time);

            $temp = array(
                'field_count' => $row_index,
                'times' => $times,
                'M_Name' => $row['M_Name'],
                'OpenType' => $row['OpenType'],
                'TurnRate' => $row['TurnRate'],
                'Mnu_Soccer' => Utils::Mnu_Soccer,
                'Odds' => $Odds,
                'LineType' => $row['LineType'],
                'BetType' => $row['BetType'],
                'voucher' => Utils::show_voucher($row['LineType'], $row['ID']),
                'Middle' => $row['Middle'],
                'BetScore' => $row['BetScore'],
                'd_point' => $d_point,
                'c_point' => $c_point,
                'b_point' => $b_point,
                'a_point' => $a_point,
                'turn' => $turn,
                'g_res' => $g_res,
                'actual_amount' => $members,
                'agents' => $agents,
                'world' => $world,
                'corprator' => $corprator,
                'pay_type' => $row['Pay_Type'],
                'memname' => $row['M_Name'],
                'BetScore' => $row['BetScore'],
                'id' => $row['ID'],
                'mb_inball' => $mb_in_score,
                'tg_inball' => $tg_in_score,
                'mb_inball_v' => $mb_in_score_v,
                'tg_inball_v' => $tg_in_score_v,
                'gtype' => $item['Type'],
                'gid' => $gid,
            );
            array_push($data, $temp);
        }
        return json_encode($data);
    }

    public function showData(Request $request) // BetSlip
    {
        $uid = $request['uid'];
        // $langx = $request->langx;
        $active = $request['active'];
        $id = $request['id'];
        $gid = $request['gid'];
        $gtype = $request['gtype'];
        $key = $request['key'];
        $confirmed = $request['confirmed'];

        $Score = Utils::Scores;

        $table = [];
        switch ($gtype) {
            case 'FT':
                $table = [1, 11];
                break;
            case 'BK':
                $table = [2, 22];
                break;
            case 'BS':
                $table = [3, 33];
                break;
            case 'TN':
                $table = [4, 44];
                break;
            case 'VB':
                $table = [5, 55];
                break;
            case 'OP':
                $table = [6, 66];
                break;
            case 'FU':
                $table = [7, 77];
                break;
            case 'FS':
                $table = [8];
                break;
        }

        // 取消注单 - Cancel bet
        if ($key == 'cancel') {
            $rresult = Report::select('M_Name', 'Pay_Type', 'BetScore', 'M_Result')->where('MID', $gid)->where('ID', $id)->where('Pay_Type', 1);
            foreach ($rresult as $rrow_index => $rrow) {
                $username = $rrow['M_Name'];
                $betscore = $rrow['BetScore'];
                $m_result = $rrow['M_Result'];
                if ($rrow['Pay_Type'] == 1) { //结算之后的现金返回 - Cash back after settlement
                    if ($m_result == '') {
                        User::where('UserName', $username)->where('Pay_Type', 1)->increment('Money', $betscore) or die("操作失败11!");
                        Utils::MoneyToSsc($username);
                    } else {
                        User::where('UserName', $username)->where('Pay_Type', 1)->decrement('Money', $m_result) or die("操作失败22!");
                        Utils::MoneyToSsc($username);
                    }
                }
            }
            Report::where('ID', $id)->update([
                'VGOLD' => 0,
                'M_Result' => 0,
                'D_Result' => 0,
                'C_Result' => 0,
                'B_Result' => 0,
                'A_Result' => 0,
                'T_Result' => 0,
                'Cancel' => 1,
                'Checked' => 1,
                'Danger' => 0,
                'Confirmed' => $confirmed
            ]) or die("操作失败!");
            // echo "<script languag='JavaScript'>self.location='showdata.php?uid=$uid&id=$id&gid=$gid&gtype=$gtype&langx=$langx'</script>";
        }

        //恢复注单 - Resume bet
        if ($key == 'resume') {
            $rresult = Report::select('M_Name', 'Pay_Type', 'BetScore', 'M_Result', 'Checked')->where('MID', $gid)->where('ID', $id)->where('Pay_Type', 1)->get();
            foreach ($rresult as $rrow_index => $rrow) {
                $username = $rrow['M_Name'];
                $betscore = $rrow['BetScore'];
                $m_result = $rrow['M_Result'];
                if ($rrow['Pay_Type'] == 1) { //结算之后的现金返回
                    if ($rrow['Checked'] == 1) {
                        $cash = $betscore + $m_result;
                        User::where('UserName', $username)->where('Pay_Type', 1)->decrement('Money', $cash) or die("操作失败1!");
                        Utils::MoneyToSsc($username);
                    }
                }
            }
            Report::where('id', $id)->update([
                'VGOLD' => '',
                'M_Result' => '',
                'D_Result' => '',
                'C_Result' => '',
                'B_Result' => '',
                'A_Result' => '',
                'T_Result' => '',
                'Cancel' => 0,
                'Checked' => 0,
                'Danger' => 0,
                'Confirmed' => 0
            ]);
            // echo "<script languag='JavaScript'>self.location='showdata.php?uid=$uid&id=$id&gid=$gid&gtype=$gtype&langx=$langx'</script>";
        }
        $result1 = Sport::where('Type', $gtype)->where('MID', $gid)->get();
        $mrow = $result1[0];
        $result = Report::select('ID', 'MID', 'Active', 'LineType', 'Mtype', 'Pay_Type', 'M_Date', 'BetTime', 'BetScore', 'CurType', 'Middle', 'BetType', 'M_Place', 'M_Rate', 'M_Name', 'Gwin', 'Glost', 'VGOLD', 'M_Result', 'A_Result', 'B_Result', 'C_Result', 'D_Result', 'T_Result', 'TurnRate', 'OpenType', 'OddsType', 'ShowType', 'Cancel', 'Confirmed', 'Danger')
            ->whereRaw('FIND_IN_SET(?, MID) > 0', [$gid]);
        if (count($table) > 0)
            $result = $result->whereIn('Active', $table);
        $result = $result->orderBy('BetTime', 'asc')->orderBy('LineType', 'asc')->orderBy('Mtype', 'asc')->get();

        $data = [];
        foreach ($result as $key => $row) {
            switch ($row['Active']) {
                case 1:
                    $Title = Utils::Mnu_Soccer;
                    break;
                case 11:
                    $Title = Utils::Mnu_Soccer;
                    break;
                case 2:
                    $Title = Utils::Mnu_BasketBall;
                    break;
                case 22:
                    $Title = Utils::Mnu_BasketBall;
                    break;
                case 3:
                    $Title = Utils::Mnu_Base;
                    break;
                case 33:
                    $Title = Utils::Mnu_Base;
                    break;
                case 4:
                    $Title = Utils::Mnu_Tennis;
                    break;
                case 44:
                    $Title = Utils::Mnu_Tennis;
                    break;
                case 5:
                    $Title = Utils::Mnu_Voll;
                    break;
                case 55:
                    $Title = Utils::Mnu_Voll;
                    break;
                case 6:
                    $Title = Utils::Mnu_Other;
                    break;
                case 66:
                    $Title = Utils::Mnu_Other;
                    break;
                case 7:
                    $Title = Utils::Mnu_Stock;
                    break;
                case 77:
                    $Title = Utils::Mnu_Stock;
                    break;
                case 8:
                    $Title = Utils::Mnu_Guan;
                    break;
            }
            switch ($row['OddsType']) {
                case 'H':
                    $Odds = '<BR><font color =green>' . Utils::Rep_HK . '</font>';
                    break;
                case 'M':
                    $Odds = '<BR><font color =green>' . Utils::Rep_Malay . '</font>';
                    break;
                case 'I':
                    $Odds = '<BR><font color =green>' . Utils::Rep_Indo . '</font>';
                    break;
                case 'E':
                    $Odds = '<BR><font color =green>' . Utils::Rep_Euro . '</font>';
                    break;
                case '':
                    $Odds = '';
                    break;
            }
            $time = strtotime($row['BetTime']);
            $times = date("Y-m-d", $time) . '<br>' . date("H:i:s", $time);

            if ($row['Danger'] == 1 or $row['Cancel'] == 1) {
                $bettimes = '<font color="#FFFFFF"><span style="background-color: #FF0000">' . $times . '</span></font>';
                $betscore = '<S><font color=#cc0000>' . number_format($row['BetScore']) . '</font></S>';
            } else {
                $bettimes = $times;
                $betscore = number_format(floatval($row['BetScore']));
            }

            $temp = [
                'bettimes' => $bettimes,
                'Title' => $Title,
                'Odds' => $Odds,
                'M_Name' => $row['M_Name'],
                'OpenType' => $row['OpenType'],
                'TurnRate' => $row['TurnRate'],
                'BetType' => $row['BetType'],
                'LineType' => $row['LineType'],
                'ID' => $row['ID'],
                'voucher' => Utils::show_voucher($row['LineType'], $row['ID']),
                'Middle' => $row['Middle'],
                'betscore' => $betscore,
                'Cancel' => $row['Cancel'],
                'Confirmed' => $row['Confirmed'],
                'M_Result' => number_format(floatval($row['M_Result']), 1),
                'operate' => '<font color=blue><b>正常</b></font>',
                'function' => []
            ];
            for ($i = 0; $i <= 21; $i++) {
                array_push($temp['function'], ['value' => -$i, 'label' => $Score[20 + $i]]);
            }
            if ($row['Cancel']) {
                switch ($row['Confirmed']) {
                    case 0:
                        $zt = $Score20;
                        break;
                    case -1:
                        $zt = $Score21;
                        break;
                    case -2:
                        $zt = $Score22;
                        break;
                    case -3:
                        $zt = $Score23;
                        break;
                    case -4:
                        $zt = $Score24;
                        break;
                    case -5:
                        $zt = $Score25;
                        break;
                    case -6:
                        $zt = $Score26;
                        break;
                    case -7:
                        $zt = $Score27;
                        break;
                    case -8:
                        $zt = $Score28;
                        break;
                    case -9:
                        $zt = $Score29;
                        break;
                    case -10:
                        $zt = $Score30;
                        break;
                    case -11:
                        $zt = $Score31;
                        break;
                    case -12:
                        $zt = $Score32;
                        break;
                    case -13:
                        $zt = $Score33;
                        break;
                    case -14:
                        $zt = $Score34;
                        break;
                    case -15:
                        $zt = $Score35;
                        break;
                    case -16:
                        $zt = $Score36;
                        break;
                    case -17:
                        $zt = $Score37;
                        break;
                    case -18:
                        $zt = $Score38;
                        break;
                    case -19:
                        $zt = $Score39;
                        break;
                    case -20:
                        $zt = $Score40;
                        break;
                    case -21:
                        $zt = $Score41;
                        break;
                    default:
                        break;
                }
                $temp['M_Result'] = $zt;
                $temp['operate'] = '<a href="showdata.php?uid=' . $uid . '&id=' . $row['ID'] . '&gid=' . $row['MID'] . '&pay_type=' . $row['Pay_Type'] . '&key=resume&result=' . $row['M_Result'] . '&user=' . $row['M_Name'] . '&confirmed=0&gtype=' . $gtype . '"><font color=red><b>恢复</b></font></a>';
            }

            array_push($data, $temp);
        }
        return array(
            'mrow' => $mrow,
            'result' => $data
        );
    }

    public function get_item_date(Request $request)
    {

        $m_date = $request->post('m_date');
        $type = $request->post('type');
        $get_type = $request->post('get_type');

        if ($m_date == '') {
            $m_date = '2021-07-11';  //////temp date
            if ($get_type == 'count') //////get number of item
            {
                if ($type != '') {
                    $items = Sport::selectRaw('*')->whereRaw("Type='$type' and `M_Date` >='$m_date'")->count();
                } else {
                    $items = Sport::selectRaw('MID')->whereRaw("`M_Date` >='$m_date'")->count();
                }

                return $items;
            }

            if ($get_type == '')  //////get data
            {
                if ($type != '') {
                    $items = Sport::selectRaw('*')->whereRaw("Type='$type' and `M_Date` >='$m_date'")->get();
                } else {
                    $items = Sport::selectRaw('*')->whereRaw("`M_Date` >='$m_date'")->get();
                }

                return $items;
            }
        } else {
            if ($get_type == 'count') //////get number of item
            {
                if ($type != '') {
                    $items = Sport::selectRaw('*')->whereRaw("Type='$type' and `M_Date` = '$m_date'")->count();
                } else {
                    $items = Sport::selectRaw('MID')->whereRaw("`M_Date` = '$m_date'")->count();
                }

                return $items;
            }

            if ($get_type == '')  //////get data
            {

                if ($type != '') {
                    $items = Sport::selectRaw('*')->whereRaw("Type='$type' and `M_Date` = '$m_date'")->get();
                } else {
                    $items = Sport::selectRaw('*')->whereRaw("`M_Date` = '$m_date'")->get();
                }

                return $items;
            }
        }
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
     * @param  \App\Models\Sport  $sport
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        //
        $sport = Sport::findOrFail($id);
        return $sport;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Sport  $sport
     * @return \Illuminate\Http\Response
     */
    public function edit(Sport $sport)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Sport  $sport
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Sport  $sport
     * @return \Illuminate\Http\Response
     */
    public function destroy(Sport $sport)
    {
        //
    }

    // betFt function
    public function betFt($id, $gold, $gid, $line, $type, $active)
    {
        //todo: should come from index.php
        $FT_Order = ""; //temp variable
        $mb_ball = 0;
        $tg_ball = 0;
        $gnum = 0;
        $strong = "";
        $ioradio_r_h = 1;
        $odd_f_type = "E";
        $rtype = "r";
        $langx = "zh-cn"; //come from session

        //todo:change language
        $Order_1st_Half = '上半';
        $Order_2nd_Half = '下半';
        $Order_1_x_2_betting_order = '单式独赢交易单';
        $Order_Handicap_betting_order = '单式让球交易单';
        $Order_Over_Under_betting_order = '单式大小交易单';
        $Order_1st_Half_1_x_2_betting_order = '上半场独赢交易单';
        $Order_1st_Half_Handicap_betting_order = '上半场让球交易单';
        $Order_1st_Half_Over_Under_betting_order = '上半场大小交易单';
        $Order_1st_Half_Correct_Score_betting_order = '上半波胆交易单';
        $Order_Other_Score = '其它比分';
        $member = User::selectRaw('*')->whereRaw("id='" . $id . "' and Status=0")->get()[0];
        $sport = Sport::selectRaw('*')->whereRaw("`M_Start`>now() and `MID`='" . $gid . "' and Cancel!=1 and Open=1 and MB_Team!='' and MB_Team_tw!='' and MB_Team_en!=''")->get();

        $config = Config::selectRaw('HG_Confirm,BadMember,kf4 as BadMember2,kf3 as BadMember3,BadMember_JQ as BadMember_JQ')->get();
        $badname_jq = explode(",", $config[0]['BadMember_JQ']);

        $gtype = "R";
        if ($line == '1') $gtype = 'M';   //独赢=win alone
        if ($line == '2') $gtype = 'R';   //让球=handicap
        if ($line == '3') $gtype = 'OU';  //大小=size
        if ($line == '4') $gtype = 'PD';  //波胆=cholesterol
        if ($line == '5') $gtype = 'R';  //单双=single and double
        if ($line == '6') $gtype = 'T';  //总入球=total goals
        if ($line == '7') $gtype = 'F';   //半全场=half time
        if ($line == '11') $gtype = 'M';  //半场独赢=win at half time
        if ($line == '12') $gtype = 'R';  //半场让球=half time handicap
        if ($line == '13') $gtype = 'OU';  //半场大小=half court size
        if ($line == '14') $gtype = 'PD';  //半场波胆=half time correct score

        $open = $member['OpenType'];
        $pay_type = $member['Pay_Type'];

        $memname = $member['UserName'];

        $agents = $member['Agents'];
        $world = $member['World'];
        $corprator = $member['Corprator'];
        $super = $member['Super'];
        $admin = $member['Admin'];
        $w_ratio = $member['ratio'];
        $w_current = $member['CurType'];

        $btset = Utils::singleset($gtype);

        if ($btset[0] > 0) $XianEr = $btset[0];
        if (count($sport) == 0) {
            //TOdo: Eror Handling, no sport
            return response()->json([
                'success' => false,
                'message' => "Not found"
            ], 404);
        }

        $w_tg_team = $sport[0]['TG_Team'];
        $w_tg_team_tw = $sport[0]['TG_Team_tw'];
        $w_tg_team_en = $sport[0]['TG_Team_en'];

        //取出四种语言的主队名称,并去掉其中的“主”和“中”字样
        $w_mb_team = Utils::filiter_team(trim($sport[0]['MB_Team']));
        $w_mb_team_tw = Utils::filiter_team(trim($sport[0]['MB_Team_tw']));
        $w_mb_team_en = Utils::filiter_team(trim($sport[0]['MB_Team_en']));
        $w_mb_mid = $sport[0]['MB_MID'];
        $w_tg_mid = $sport[0]['TG_MID'];


        if (strpos($w_tg_team, '角球') or strpos($w_mb_team, '角球') or strpos($w_tg_team, '点球') or strpos($w_mb_team, '点球')) {  //屏蔽角球、点球投注
            if (in_array($memname, $badname_jq)) {
                return response()->json([
                    'success' => false,
                    'message' => "赛程已关闭,无法进行交易!!"
                ], 404);
            }
        }

        $s_mb_team = Utils::filiter_team($sport[0]['MB_Team']);
        $s_tg_team = Utils::filiter_team($sport[0]['TG_team']);
        if ($gold <= 0) {
            return response()->json([
                'success' => false,
                'message' => "非法参数！"
            ], 404);
        }

        if ($gold < $XianEr) {
            return response()->json([
                'success' => false,
                'message' => "最低投注額是RMB"
            ], 404);
        }


        //下注时间
        $m_date = $sport[0]["M_Date"];
        $showtype = $sport[0]["ShowTypeR"];
        if ($line == '12' or $line == '13' or $line == '14') {  //获取半场
            $showtype = $sport[0]["ShowTypeHR"];
        }

        $bettime = date('Y-m-d H:i:s');
        $m_start = strtotime($sport[0]['M_Start']);
        $datetime = time();

        if ($m_start - $datetime < 120) {
            return response()->json([
                'success' => false,
                'message' => "赛程已关闭,无法进行交易!!"
            ], 404);
        }

        $s_sleague = $sport[0]["M_League"];
        switch ($line) {
            case 1:
                $bet_type = '独赢';
                $bet_type_tw = '獨贏';

                $bet_type_en = "1x2";
                $caption = $FT_Order . $Order_1_x_2_betting_order;
                $turn_rate = "FT_Turn_M";
                $turn = "FT_Turn_M";

                switch ($type) {
                    case "H":
                        $w_m_place = $w_mb_team;
                        $w_m_place_tw = $w_mb_team_tw;
                        $w_m_place_en = $w_mb_team_en;
                        $s_m_place = $s_mb_team;
                        $w_m_rate = Utils::num_rate($open, $sport[0]["MB_Win_Rate"]);
                        $mtype = 'MH';
                        break;
                    case "C":
                        $w_m_place = $w_tg_team;
                        $w_m_place_tw = $w_tg_team_tw;
                        $w_m_place_en = $w_tg_team_en;
                        $s_m_place = $s_tg_team;
                        $w_m_rate = Utils::num_rate($open, $sport[0]["TG_Win_Rate"]);
                        $mtype = 'MC';
                        break;
                    case "N":
                        $w_m_place = "和局";
                        $w_m_place_tw = "和局";
                        $w_m_place_en = "Flat";
                        $s_m_place = "和局";
                        $w_m_rate = Utils::num_rate($open, $sport[0]["M_Flat_Rate"]);
                        $mtype = 'MN';
                        break;
                }
                $Sign = "VS.";
                $grape = "";
                $gwin = ($w_m_rate - 1) * $gold;
                $ptype = 'M';
                break;
            case 2:
                $bet_type = '让球';
                $bet_type_tw = "讓球";
                $bet_type_en = "Handicap";
                $caption = $FT_Order . $Order_Handicap_betting_order;
                $turn_rate = "FT_Turn_R_" . $open;
                $rate = Utils::get_other_ioratio($odd_f_type, $sport[0]["MB_LetB_Rate"], $sport[0]["TG_LetB_Rate"], 100);
                switch ($type) {
                    case "H":
                        $w_m_place = $w_mb_team;
                        $w_m_place_tw = $w_mb_team_tw;
                        $w_m_place_en = $w_mb_team_en;
                        $s_m_place = $s_mb_team;
                        $w_m_rate = Utils::change_rate($open, $rate[0]);
                        $turn_url = "/app/member/FT_order/FT_order_r.php?gid=" . $gid . "&uid=" . $id . "&type=" . $type . "&gnum=" . $gnum . "&strong=" . $strong . "&odd_f_type=" . $odd_f_type;
                        $mtype = 'RH';
                        break;
                    case "C":
                        $w_m_place = $w_tg_team;
                        $w_m_place_tw = $w_tg_team_tw;
                        $w_m_place_en = $w_tg_team_en;
                        $s_m_place = $s_tg_team;
                        $w_m_rate = Utils::change_rate($open, $rate[1]);
                        $turn_url = "/app/member/FT_order/FT_order_r.php?gid=" . $gid . "&uid=" . $id . "&type=" . $type . "&gnum=" . $gnum . "&strong=" . $strong . "&odd_f_type=" . $odd_f_type;
                        $mtype = 'RC';
                        break;
                }
                $Sign = $sport[0]['M_LetB'];
                $grape = $Sign;
                if ($showtype == "H") {
                    $l_team = $s_mb_team;
                    $r_team = $s_tg_team;
                    $w_l_team = $w_mb_team;
                    $w_l_team_tw = $w_mb_team_tw;
                    $w_l_team_en = $w_mb_team_en;
                    $w_r_team = $w_tg_team;
                    $w_r_team_tw = $w_tg_team_tw;
                    $w_r_team_en = $w_tg_team_en;
                } else {
                    $r_team = $s_mb_team;
                    $l_team = $s_tg_team;
                    $w_r_team = $w_mb_team;
                    $w_r_team_tw = $w_mb_team_tw;
                    $w_r_team_en = $w_mb_team_en;
                    $w_l_team = $w_tg_team;
                    $w_l_team_tw = $w_tg_team_tw;
                    $w_l_team_en = $w_tg_team_en;
                }
                $s_mb_team = $l_team;
                $s_tg_team = $r_team;
                $w_mb_team = $w_l_team;
                $w_mb_team_tw = $w_l_team_tw;
                $w_mb_team_en = $w_l_team_en;
                $w_tg_team = $w_r_team;
                $w_tg_team_tw = $w_r_team_tw;
                $w_tg_team_en = $w_r_team_en;

                $turn = "FT_Turn_R";
                if ($odd_f_type == 'H') {
                    $gwin = ($w_m_rate) * $gold;
                } else if ($odd_f_type == 'M' or $odd_f_type == 'I') {
                    if ($w_m_rate < 0) {
                        $gwin = $gold;
                    } else {
                        $gwin = ($w_m_rate) * $gold;
                    }
                } else if ($odd_f_type == 'E') {
                    $gwin = ($w_m_rate - 1) * $gold;
                }
                $ptype = 'R';
                break;
            case 3:
                $bet_type = '大小';
                $bet_type_tw = "大小";
                $bet_type_en = "Over/Under";
                $caption = $FT_Order . $Order_Over_Under_betting_order;
                $turn_rate = "FT_Turn_OU_" . $open;
                $rate = Utils::get_other_ioratio($odd_f_type, $sport[0]["MB_Dime_Rate"], $sport[0]["TG_Dime_Rate"], 100);
                switch ($type) {
                    case "C":
                        $w_m_place = $sport[0]["MB_Dime"];
                        $w_m_place = str_replace('O', '大&nbsp;', $w_m_place);
                        $w_m_place_tw = $sport[0]["MB_Dime"];
                        $w_m_place_tw = str_replace('O', '大&nbsp;', $w_m_place_tw);
                        $w_m_place_en = $sport[0]["MB_Dime"];
                        $w_m_place_en = str_replace('O', 'over&nbsp;', $w_m_place_en);

                        $m_place = $sport[0]["MB_Dime"];

                        $s_m_place = $sport[0]["MB_Dime"];
                        if ($langx == "zh-cn") {
                            $s_m_place = str_replace('O', '大&nbsp;', $s_m_place);
                        } else if ($langx == "zh-tw") {
                            $s_m_place = str_replace('O', '大&nbsp;', $s_m_place);
                        } else if ($langx == "en-us" or $langx == "th-tis") {
                            $s_m_place = str_replace('O', 'over&nbsp;', $s_m_place);
                        }
                        $w_m_rate = Utils::change_rate($open, $rate[0]);
                        $turn_url = "/app/member/FT_order/FT_order_ou.php?gid=" . $gid . "&uid=" . $id . "&type=" . $type . "&gnum=" . $gnum . "&odd_f_type=" . $odd_f_type;
                        $mtype = 'OUH';
                        break;
                    case "H":
                        $w_m_place = $sport[0]["TG_Dime"];
                        $w_m_place = str_replace('U', '小&nbsp;', $w_m_place);
                        $w_m_place_tw = $sport[0]["TG_Dime"];
                        $w_m_place_tw = str_replace('U', '小&nbsp;', $w_m_place_tw);
                        $w_m_place_en = $sport[0]["TG_Dime"];
                        $w_m_place_en = str_replace('U', 'under&nbsp;', $w_m_place_en);

                        $m_place = $sport[0]["TG_Dime"];

                        $s_m_place = $sport[0]["TG_Dime"];
                        if ($langx == "zh-cn") {
                            $s_m_place = str_replace('U', '小&nbsp;', $s_m_place);
                        } else if ($langx == "zh-tw") {
                            $s_m_place = str_replace('U', '小&nbsp;', $s_m_place);
                        } else if ($langx == "en-us" or $langx == "th-tis") {
                            $s_m_place = str_replace('U', 'under&nbsp;', $s_m_place);
                        }

                        $w_m_rate = Utils::change_rate($open, $rate[1]);
                        $turn_url = "/app/member/FT_order/FT_order_ou.php?gid=" . $gid . "&uid=" . $id . "&type=" . $type . "&gnum=" . $gnum . "&odd_f_type=" . $odd_f_type;
                        $mtype = 'OUC';
                        break;
                }
                $Sign = "VS.";
                $grape = $m_place;
                $turn = "FT_Turn_OU";
                if ($odd_f_type == 'H') {
                    $gwin = ($w_m_rate) * $gold;
                } else if ($odd_f_type == 'M' or $odd_f_type == 'I') {
                    if ($w_m_rate < 0) {
                        $gwin = $gold;
                    } else {
                        $gwin = ($w_m_rate) * $gold;
                    }
                } else if ($odd_f_type == 'E') {
                    $gwin = ($w_m_rate - 1) * $gold;
                }
                $ptype = 'OU';
                break;
            case 4:
                $bet_type = '波胆';
                $bet_type_tw = "波膽";
                $bet_type_en = "Correct Score";
                $caption = $FT_Order . $Order_Correct_Score_betting_order;
                $turn_rate = "FT_Turn_PD";
                if ($rtype != 'OVH') {
                    $rtype = str_replace('C', 'TG', str_replace('H', 'MB', $rtype));
                    $w_m_rate = $sport[0][$rtype];
                } else {
                    $w_m_rate = $sport[0]['UP5'];
                }
                if ($rtype == "OVH") {
                    $s_m_place = $Order_Other_Score;
                    $w_m_place = '其它比分';
                    $w_m_place_tw = '其它比分';
                    $w_m_place_en = 'Other Score';
                    $Sign = "VS.";
                } else {
                    $M_Place = "";
                    $M_Sign = $rtype;
                    $M_Sign = str_replace("MB", "", $M_Sign);
                    $M_Sign = str_replace("TG", ":", $M_Sign);
                    $Sign = $M_Sign . "";
                }
                $grape = "";
                $turn = "FT_Turn_PD";
                $gwin = ($w_m_rate - 1) * $gold;
                $ptype = 'PD';
                $mtype = $rtype;
                break;
            case 5:
                $bet_type = '单双';
                $bet_type_tw = "單雙";
                $bet_type_en = "Odd/Even";
                $caption = $FT_Order . $Order_Odd_Even_betting_order;
                $turn_rate = "FT_Turn_EO_" . $open;
                switch ($rtype) {
                    case "ODD":
                        $w_m_place = '单';
                        $w_m_place_tw = '單';
                        $w_m_place_en = 'odd';
                        $s_m_place = '(' . $Order_Odd . ')';
                        $w_m_rate = Utils::num_rate($open, $sport[0]["S_Single_Rate"]);
                        break;
                    case "EVEN":
                        $w_m_place = '双';
                        $w_m_place_tw = '雙';
                        $w_m_place_en = 'even';
                        $s_m_place = '(' . $Order_Even . ')';
                        $w_m_rate = Utils::num_rate($open, $sport[0]["S_Double_Rate"]);
                        break;
                }
                $Sign = "VS.";
                $turn = "FT_Turn_EO";
                $gwin = ($w_m_rate - 1) * $gold;
                $ptype = 'EO';
                $mtype = $rtype;
                break;
            case 6:
                $bet_type = '总入球';
                $bet_type_tw = "總入球";
                $bet_type_en = "Total";
                $caption = $FT_Order . $Order_Total_Goals_betting_order;
                $turn_rate = "FT_Turn_T";
                switch ($rtype) {
                    case "0~1":
                        $w_m_place = '0~1';
                        $w_m_place_tw = '0~1';
                        $w_m_place_en = '0~1';
                        $s_m_place = '(0~1)';
                        $w_m_rate = $sport[0]["S_0_1"];
                        break;
                    case "2~3":
                        $w_m_place = '2~3';
                        $w_m_place_tw = '2~3';
                        $w_m_place_en = '2~3';
                        $s_m_place = '(2~3)';
                        $w_m_rate = $sport[0]["S_2_3"];
                        break;
                    case "4~6":
                        $w_m_place = '4~6';
                        $w_m_place_tw = '4~6';
                        $w_m_place_en = '4~6';
                        $s_m_place = '(4~6)';
                        $w_m_rate = $sport[0]["S_4_6"];
                        break;
                    case "OVER":
                        $w_m_place = '7up';
                        $w_m_place_tw = '7up';
                        $w_m_place_en = '7up';
                        $s_m_place = '(7up)';
                        $w_m_rate = $sport[0]["S_7UP"];
                        break;
                }
                $turn = "FT_Turn_T";
                $Sign = "VS.";
                $gwin = ($w_m_rate - 1) * $gold;
                $ptype = 'T';
                $mtype = $rtype;
                break;
            case 7:
                $bet_type = '半全场';
                $bet_type_tw = "半全場";
                $bet_type_en = "Half/Full Time";
                $caption = $FT_Order . $Order_Half_Full_Time_betting_order;
                $turn_rate = "FT_Turn_F";
                switch ($rtype) {
                    case "FHH":
                        $w_m_place = $w_mb_team . '&nbsp;/&nbsp;' . $w_mb_team;
                        $w_m_place_tw = $w_mb_team_tw . '&nbsp;/&nbsp;' . $w_mb_team_tw;
                        $w_m_place_en = $w_mb_team_en . '&nbsp;/&nbsp;' . $w_mb_team_en;
                        $s_m_place = $sport[0]["MB_Team"] . '&nbsp;/&nbsp;' . $sport[0]["MB_Team"];
                        $w_m_rate = $sport[0]["MBMB"];
                        break;
                    case "FHN":
                        $w_m_place = $w_mb_team . '&nbsp;/&nbsp;和局';
                        $w_m_place_tw = $w_mb_team_tw . '&nbsp;/&nbsp;和局';
                        $w_m_place_en = $w_mb_team_en . '&nbsp;/&nbsp;Flat';
                        $s_m_place = $sport[0]["MB_Team"] . '&nbsp;/&nbsp;' . "和局";
                        $w_m_rate = $sport[0]["MBFT"];
                        break;
                    case "FHC":
                        $w_m_place = $w_mb_team . '&nbsp;/&nbsp;' . $w_tg_team;
                        $w_m_place_tw = $w_mb_team_tw . '&nbsp;/&nbsp;' . $w_tg_team_tw;
                        $w_m_place_en = $w_mb_team_en . '&nbsp;/&nbsp;' . $w_tg_team_en;
                        $s_m_place = $sport[0]["MB_Team"] . '&nbsp;/&nbsp;' . $sport[0]["TG_Team"];
                        $w_m_rate = $sport[0]["MBTG"];
                        break;
                    case "FNH":
                        $w_m_place = '和局&nbsp;/&nbsp;' . $w_mb_team;
                        $w_m_place_tw = '和局&nbsp;/&nbsp;' . $w_mb_team_tw;
                        $w_m_place_en = 'Flat&nbsp;/&nbsp;' . $w_mb_team_en;
                        $s_m_place = "和局" . '&nbsp;/&nbsp;' . $sport[0]["MB_Team"];
                        $w_m_rate = $sport[0]["FTMB"];
                        break;
                    case "FNN":
                        $w_m_place = '和局&nbsp;/&nbsp;和局';
                        $w_m_place_tw = '和局&nbsp;/&nbsp;和局';
                        $w_m_place_en = 'Flat&nbsp;/&nbsp;Flat';
                        $s_m_place = "和局" . '&nbsp;/&nbsp;' . "和局";
                        $w_m_rate = $sport[0]["FTFT"];
                        break;
                    case "FNC":
                        $w_m_place = '和局&nbsp;/&nbsp;' . $w_tg_team;
                        $w_m_place_tw = '和局&nbsp;/&nbsp;' . $w_tg_team_tw;
                        $w_m_place_en = 'Flat&nbsp;/&nbsp;' . $w_tg_team_en;
                        $s_m_place = "和局" . '&nbsp;/&nbsp;' . $sport[0]["TG_Team"];
                        $w_m_rate = $sport[0]["FTTG"];
                        break;
                    case "FCH":
                        $w_m_place = $w_tg_team . '&nbsp;/&nbsp;' . $w_mb_team;
                        $w_m_place_tw = $w_tg_team_tw . '&nbsp;/&nbsp;' . $w_mb_team_tw;
                        $w_m_place_en = $w_tg_team_en . '&nbsp;/&nbsp;' . $w_mb_team_en;
                        $s_m_place = $sport[0]["TG_Team"] . '&nbsp;/&nbsp;' . $sport[0]["MB_Team"];
                        $w_m_rate = $sport[0]["TGMB"];
                        break;
                    case "FCN":
                        $w_m_place = $w_tg_team . '&nbsp;/&nbsp;和局';
                        $w_m_place_tw = $w_tg_team_tw . '&nbsp;/&nbsp;和局';
                        $w_m_place_en = $w_tg_team_en . '&nbsp;/&nbsp;Flat';
                        $s_m_place = $sport[0]["TG_Team"] . '&nbsp;/&nbsp;' . "和局";
                        $w_m_rate = $sport[0]["TGFT"];
                        break;
                    case "FCC":
                        $w_m_place = $w_tg_team . '&nbsp;/&nbsp;' . $w_tg_team;
                        $w_m_place_tw = $w_tg_team_tw . '&nbsp;/&nbsp;' . $w_tg_team_tw;
                        $w_m_place_en = $w_tg_team_en . '&nbsp;/&nbsp;' . $w_tg_team_en;
                        $s_m_place = $sport[0]["TG_Team"] . '&nbsp;/&nbsp;' . $sport[0]["TG_Team"];
                        $w_m_rate = $sport[0]["TGTG"];
                        break;
                }
                $Sign = "VS.";
                $turn = "FT_Turn_F";
                $gwin = ($w_m_rate - 1) * $gold;
                $ptype = 'F';
                $mtype = $rtype;
                break;
            case 11:
                $bet_type = '半场独赢';
                $bet_type_tw = "半場獨贏";
                $bet_type_en = "1st Half 1x2";
                $btype = "-&nbsp;<font color=red><b>[$Order_1st_Half]</b></font>";
                $caption = $FT_Order . $Order_1st_Half_1_x_2_betting_order;
                $turn_rate = "FT_Turn_M";
                $turn = "FT_Turn_M";
                switch ($type) {
                    case "H":
                        $w_m_place = $w_mb_team;
                        $w_m_place_tw = $w_mb_team_tw;
                        $w_m_place_en = $w_mb_team_en;
                        $s_m_place = $sport[0]["MB_Team"];
                        $w_m_rate = Utils::num_rate($open, $sport[0]["MB_Win_Rate_H"]);
                        $mtype = 'VMH';
                        break;
                    case "C":
                        $w_m_place = $w_tg_team;
                        $w_m_place_tw = $w_tg_team_tw;
                        $w_m_place_en = $w_tg_team_en;
                        $s_m_place = $sport[0]["TG_Team"];
                        $w_m_rate = Utils::num_rate($open, $sport[0]["TG_Win_Rate_H"]);
                        $mtype = 'VMC';
                        break;
                    case "N":
                        $w_m_place = "和局";
                        $w_m_place_tw = "和局";
                        $w_m_place_en = "Flat";
                        $s_m_place = "和局";
                        $w_m_rate = Utils::num_rate($open, $sport[0]["M_Flat_Rate_H"]);
                        $mtype = 'VMN';
                        break;
                }
                $Sign = "VS.";
                $grape = "";
                $gwin = ($w_m_rate - 1) * $gold;
                $ptype = 'VM';
                break;
            case 12:
                $bet_type = '半场让球';
                $bet_type_tw = "半場讓球";
                $bet_type_en = "1st Half Handicap";
                $btype = "-&nbsp;<font color=red><b>[$Order_1st_Half]</b></font>";
                $caption = $FT_Order . $Order_1st_Half_Handicap_betting_order;
                $turn_rate = "FT_Turn_R_" . $open;
                $rate = Utils::get_other_ioratio($odd_f_type, $sport[0]["MB_LetB_Rate_H"], $sport[0]["TG_LetB_Rate_H"], 100);
                switch ($type) {
                    case "H":
                        $w_m_place = $w_mb_team;
                        $w_m_place_tw = $w_mb_team_tw;
                        $w_m_place_en = $w_mb_team_en;
                        $s_m_place = $sport[0]["MB_Team"];
                        $w_m_rate = Utils::change_rate($open, $rate[0]);
                        $turn_url = "/app/member/FT_order/FT_order_hr.php?gid=" . $gid . "&uid=" . $id . "&type=" . $type . "&gnum=" . $gnum . "&strong=" . $strong . "&odd_f_type=" . $odd_f_type;
                        $mtype = 'VRH';
                        break;
                    case "C":
                        $w_m_place = $w_tg_team;
                        $w_m_place_tw = $w_tg_team_tw;
                        $w_m_place_en = $w_tg_team_en;
                        $s_m_place = $sport[0]["TG_Team"];
                        $w_m_rate = Utils::change_rate($open, $rate[1]);
                        $turn_url = "/app/member/FT_order/FT_order_hr.php?gid=" . $gid . "&uid=" . $id . "&type=" . $type . "&gnum=" . $gnum . "&strong=" . $strong . "&odd_f_type=" . $odd_f_type;
                        $mtype = 'VRC';
                        break;
                }
                $Sign = $sport[0]['M_LetB_H'];
                $grape = $Sign;
                if ($showtype == "H") {
                    $l_team = $s_mb_team;
                    $r_team = $s_tg_team;

                    $w_l_team = $w_mb_team;
                    $w_l_team_tw = $w_mb_team_tw;
                    $w_l_team_en = $w_mb_team_en;
                    $w_r_team = $w_tg_team;
                    $w_r_team_tw = $w_tg_team_tw;
                    $w_r_team_en = $w_tg_team_en;
                } else {
                    $r_team = $s_mb_team;
                    $l_team = $s_tg_team;
                    $w_r_team = $w_mb_team;
                    $w_r_team_tw = $w_mb_team_tw;
                    $w_r_team_en = $w_mb_team_en;
                    $w_l_team = $w_tg_team;
                    $w_l_team_tw = $w_tg_team_tw;
                    $w_l_team_en = $w_tg_team_en;
                }
                $s_mb_team = $l_team;
                $s_tg_team = $r_team;
                $w_mb_team = $w_l_team;
                $w_mb_team_tw = $w_l_team_tw;
                $w_mb_team_en = $w_l_team_en;
                $w_tg_team = $w_r_team;
                $w_tg_team_tw = $w_r_team_tw;
                $w_tg_team_en = $w_r_team_en;
                $turn = "FT_Turn_R";
                if ($odd_f_type == 'H') {
                    $gwin = ($w_m_rate) * $gold;
                } else if ($odd_f_type == 'M' or $odd_f_type == 'I') {
                    if ($w_m_rate < 0) {
                        $gwin = $gold;
                    } else {
                        $gwin = ($w_m_rate) * $gold;
                    }
                } else if ($odd_f_type == 'E') {
                    $gwin = ($w_m_rate - 1) * $gold;
                }
                $ptype = 'VR';
                break;
            case 13:
                $bet_type = '半场大小';
                $bet_type_tw = "半場大小";
                $bet_type_en = "1st Half Over/Under";
                $caption = $FT_Order . $Order_1st_Half_Over_Under_betting_order;
                $btype = "-&nbsp;<font color=red><b>[$Order_1st_Half]</b></font>";
                $turn_rate = "FT_Turn_OU_" . $open;
                $rate = Utils::get_other_ioratio($odd_f_type, $sport[0]["MB_Dime_Rate_H"], $sport[0]["TG_Dime_Rate_H"], 100);
                switch ($type) {
                    case "C":
                        $w_m_place = $sport[0]["MB_Dime_H"];
                        $w_m_place = str_replace('O', '大&nbsp;', $w_m_place);
                        $w_m_place_tw = $sport[0]["MB_Dime_H"];
                        $w_m_place_tw = str_replace('O', '大&nbsp;', $w_m_place_tw);
                        $w_m_place_en = $sport[0]["MB_Dime_H"];
                        $w_m_place_en = str_replace('O', 'over&nbsp;', $w_m_place_en);

                        $m_place = $sport[0]["MB_Dime_H"];

                        $s_m_place = $sport[0]["MB_Dime_H"];
                        if ($langx == "zh-cn") {
                            $s_m_place = str_replace('O', '大&nbsp;', $s_m_place);
                        } else if ($langx == "zh-tw") {
                            $s_m_place = str_replace('O', '大&nbsp;', $s_m_place);
                        } else if ($langx == "en-us" or $langx == "th-tis") {
                            $s_m_place = str_replace('O', 'over&nbsp;', $s_m_place);
                        }
                        $w_m_rate = Utils::change_rate($open, $rate[0]);
                        $turn_url = "/app/member/FT_order/FT_order_hou.php?gid=" . $gid . "&uid=" . $id . "&type=" . $type . "&gnum=" . $gnum . "&odd_f_type=" . $odd_f_type;
                        $mtype = 'VOUH';
                        break;
                    case "H":
                        $w_m_place = $sport[0]["TG_Dime_H"];
                        $w_m_place = str_replace('U', '小&nbsp;', $w_m_place);
                        $w_m_place_tw = $sport[0]["TG_Dime_H"];
                        $w_m_place_tw = str_replace('U', '小&nbsp;', $w_m_place_tw);
                        $w_m_place_en = $sport[0]["TG_Dime_H"];
                        $w_m_place_en = str_replace('U', 'under&nbsp;', $w_m_place_en);

                        $m_place = $sport[0]["TG_Dime_H"];

                        $s_m_place = $sport[0]["TG_Dime_H"];
                        if ($langx == "zh-cn") {
                            $s_m_place = str_replace('U', '小&nbsp;', $s_m_place);
                        } else if ($langx == "zh-tw") {
                            $s_m_place = str_replace('U', '小&nbsp;', $s_m_place);
                        } else if ($langx == "en-us" or $langx == "th-tis") {
                            $s_m_place = str_replace('U', 'under&nbsp;', $s_m_place);
                        }
                        $w_m_rate = Utils::change_rate($open, $rate[1]);
                        $turn_url = "/app/member/FT_order/FT_order_hou.php?gid=" . $gid . "&uid=" . $id . "&type=" . $type . "&gnum=" . $gnum . "&odd_f_type=" . $odd_f_type;
                        $mtype = 'VOUC';
                        break;
                }
                $Sign = "VS.";
                $grape = $m_place;
                $turn = "FT_Turn_OU";
                if ($odd_f_type == 'H') {
                    $gwin = ($w_m_rate) * $gold;
                } else if ($odd_f_type == 'M' or $odd_f_type == 'I') {
                    if ($w_m_rate < 0) {
                        $gwin = $gold;
                    } else {
                        $gwin = ($w_m_rate) * $gold;
                    }
                } else if ($odd_f_type == 'E') {
                    $gwin = ($w_m_rate - 1) * $gold;
                }
                $ptype = 'VOU';
                break;
            case 14:
                $bet_type = '半场波胆';
                $bet_type_tw = "半場波膽";
                $bet_type_en = "1st Half Correct Score";
                $caption = $FT_Order . $Order_1st_Half_Correct_Score_betting_order;
                $btype = "-&nbsp;<font color=red><b>[$Order_1st_Half]</b></font>";
                $turn_rate = "FT_Turn_PD";
                if ($rtype != 'OVH') {
                    $rtype = str_replace('C', 'TG', str_replace('H', 'MB', $rtype));
                    $w_m_rate = $sport[0]["UP5H"];
                } else {
                    $w_m_rate = $sport[0]['UP5H'];
                }
                if ($rtype == "OVH") {
                    $s_m_place = $Order_Other_Score;
                    $w_m_place = '其它比分';
                    $w_m_place_tw = '其它比分';
                    $w_m_place_en = 'Other Score';
                    $Sign = "VS.";
                } else {
                    $s_m_place = $Order_Other_Score;
                    $M_Place = "";
                    $M_Sign = $rtype;
                    $M_Sign = str_replace("MB", "", $M_Sign);
                    $M_Sign = str_replace("TG", ":", $M_Sign);
                    $Sign = $M_Sign . "";
                }
                $grape = "";
                $turn = "FT_Turn_PD";
                $gwin = ($w_m_rate - 1) * $gold;
                $ptype = 'VPD';
                $mtype = $rtype;
        }

        if ($line == 11 or $line == 12 or $line == 13 or $line == 14) {
            $bottom1_cn = "-&nbsp;<font color=#666666>[上半]</font>&nbsp;";
            $bottom1_tw = "-&nbsp;<font color=#666666>[上半]</font>&nbsp;";
            $bottom1_en = "-&nbsp;<font color=#666666>[1st Half]</font>&nbsp;";
        }

        if ($line == 2 or $line == 3 or $line == 12 or $line == 13) {
            // if ($w_m_rate!=$ioradio_r_h){
            //     $turn_url=$turn_url.'&error_flag=1';
            //     echo "<script language='javascript'>self.location='$turn_url';</script>";
            //     //exit();
            //     //Todo:
            // }
            $oddstype = $odd_f_type;
        } else {
            $oddstype = '';
        }
        $s_m_place = Utils::filiter_team(trim($s_m_place));

        $w_mid = "<br>[" . $sport[0]['MB_MID'] . "]vs[" . $sport[0]['TG_MID'] . "]<br>";
        $lines = $sport[0]['M_League'] . $w_mid . $w_mb_team . "&nbsp;&nbsp;<FONT COLOR=#0000BB><b>" . $Sign . "</b></FONT>&nbsp;&nbsp;" . $w_tg_team . "<br>";
        // $lines=$lines."<FONT color=#cc0000>".$w_m_place."</FONT>&nbsp;".$bottom1_cn."@&nbsp;<FONT color=#cc0000><b>".$w_m_rate."</b></FONT>";
        // $lines_tw=$sport[0]['M_League_tw'].$w_mid.$w_mb_team_tw."&nbsp;&nbsp;<FONT COLOR=#0000BB><b>".$Sign."</b></FONT>&nbsp;&nbsp;".$w_tg_team_tw."<br>";
        // $lines_tw=$lines_tw."<FONT color=#cc0000>".$w_m_place_tw."</FONT>&nbsp;".$bottom1_tw."@&nbsp;<FONT color=#cc0000><b>".$w_m_rate."</b></FONT>";
        // $lines_en=$sport[0]['M_League_en'].$w_mid.$w_mb_team_en."&nbsp;&nbsp;<FONT COLOR=#0000BB><b>".$Sign."</b></FONT>&nbsp;&nbsp;".$w_tg_team_en."<br>";
        // $lines_en=$lines_en."<FONT color=#cc0000>".$w_m_place_en."</FONT>&nbsp;".$bottom1_en."@&nbsp;<FONT color=#cc0000><b>".$w_m_rate."</b></FONT>";

        if ($w_m_rate == '' or $gwin <= 0 or $gwin == '') {
            return response()->json([
                'success' => false,
                'message' => "赛程已关闭,无法进行交易!!"
            ], 404);
        }

        $ip_addr = Utils::get_ip();

        $msql = "select $turn as M_turn from web_member_data where UserName='$memname'";
        $mrow = DB::select($msql)[0];
        $m_turn = $mrow->M_turn + 0;

        $asql = "select $turn_rate as A_turn from web_agents_data where UserName='$super'";
        $arow = DB::select($asql)[0];
        $a_rate = $arow->A_turn + 0;

        $bsql = "select $turn_rate as B_turn from web_agents_data where UserName='$corprator'";
        $brow = DB::select($bsql)[0];
        $b_rate = $brow->B_turn + 0;

        $csql = "select $turn_rate as C_turn from web_agents_data where UserName='$world'";
        $crow = DB::select($csql)[0];
        $c_rate = $crow->C_turn + 0;

        $dsql = "select $turn_rate as D_turn from web_agents_data where UserName='$agents'";
        $drow = DB::select($dsql)[0];
        $d_rate = $drow->D_turn + 0;

        $psql = "select * from web_agents_data where UserName='$agents'";
        $prow = DB::select($psql)[0];
        $a_point = $prow->A_Point + 0;
        $b_point = $prow->B_Point + 0;
        $c_point = $prow->C_Point + 0;
        $d_point = $prow->D_Point + 0;
        //  return "aaaaaasssss";
        $max_sql = "select max(ID) max_id from web_report_data where BetTime<'$bettime'";
        $max_row = DB::select($max_sql)[0];
        $max_id = $max_row->max_id;
        $num = rand(10, 50);

        $newid = $max_id + $num;
        $OrderID = Utils::show_voucher($line, $newid);  //定单号
        if ($oddstype == '') $oddstype = 'H';


        $sql = "INSERT INTO web_report_data	(ID,OrderID,MID,Active,LineType,Mtype,M_Date,BetTime,BetScore,Middle,BetType,M_Place,M_Rate,M_Name,Gwin,TurnRate,OpenType,OddsType,ShowType,Agents,World,Corprator,Super,Admin,A_Rate,B_Rate,C_Rate,D_Rate,A_Point,B_Point,C_Point,D_Point,BetIP,Ptype,Gtype,CurType,Ratio,MB_MID,TG_MID,Pay_Type,MB_Ball,TG_Ball)                values ('$newid','$OrderID','$gid','$active','$line','$mtype','$m_date','$bettime','$gold','$lines',   '$bet_type','$grape','$w_m_rate','$memname','$gwin','$m_turn','$open','$oddstype','$showtype', '$agents','$world','$corprator','$super','$admin','$a_rate','$b_rate','$c_rate','$d_rate','$a_point', '$b_point', '$c_point','$d_point','$ip_addr','$ptype','FT','$w_current','$w_ratio','$w_mb_mid', '$w_tg_mid','$pay_type','$mb_ball','$tg_ball')";
        DB::insert($sql);

        $ouid = DB::getPdo()->lastInsertId();
        // return  $ouid;
        $assets = Utils::GetField($memname, 'Money');
        $user_id = Utils::GetField($memname, 'ID');
        $datetime = date("Y-m-d H:i:s", time() + 12 * 3600);

        Utils::ProcessUpdate($memname);  //防止并发
        $hMoney = $member['Money'];
        $rMoney = $hMoney - $gold;

        $q1 = User::where('id', $id)->update(['Money' => $rMoney]);

        if ($q1 == 1) {
            $balance = Utils::GetField($memname, 'Money');
            $money_log_sql = "insert into money_log set user_id='$user_id',order_num='$OrderID',about='投注足球<br>gid:$gid<br>RID:$ouid',update_time='$datetime',type='$lines',order_value='-$gold',assets=$assets,balance=$balance";
            DB::insert($money_log_sql);
        } else {
            DB::raw("delete from web_report_data where id=" . $ouid);
            echo "<script>alert('投注不成功,请联系客服!');top.location.reload();</script>";
            //todo: exit()
        }
        // return "aaaassssaaaaa";
        $t = date("Y-m-d H:i:s");
        $pp = file_get_contents("php://input");
        $tmpfile = $_SERVER['DOCUMENT_ROOT'] . "/tmp/FT-" . date("Ymd") . ".txt";
        if (file_exists($tmpfile)) {
            $f = fopen($tmpfile, 'a');
        } else {
            $f = fopen($tmpfile, 'w');
        }
        fwrite($f, $t . "\r\n" . $sql . "\r\n");
        fwrite($f, $pp . "\r\n\r\n");
        fclose($f);
        return $rMoney;
    }
    // Single Betting FT
    public function singleBetFt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gold' => 'required|Integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->messages()->toArray()
            ], 500);
        }
        $id = $request->post('id');
        $gold = $request->post('gold');
        $gid = $request->post('gid');
        $line = $request->post('line_type');
        $type = $request->post('type');
        $active = $request->post('active');    //not used but final sql
        $money = $this->betFt($id, $gold, $gid, $line, $type, $active);
        return response()->json([
            "success" => true,
            "data" => $money
        ], 200);
    }

    // Multi Betting FT
    public function multiBetFt(Request $request)
    {

        $data = $request->post('data');
        $count = $request->post('count');
        for ($i = 0; $i < $count; $i++) {
            $item = (object)$data[$i];
            $id = $item->id;
            $gold = $item->gold;
            $gid = $item->gid;
            $line = $item->line_type;
            $type = $item->type;
            $active = $item->active;
            $money = $this->betFt($id, $gold, $gid, $line, $type, $active);
        }
        $this->deleteTemps();
        return response()->json([
            "success" => true,
            "data" => $money
        ], 200);
    }

    // GET Betting Records API
    public function get_betting_records(Request $request)
    {

        $m_name = $request->post('m_name');

        $report_count = Report::where("M_Name", $m_name)->count();
        $items = Report::with('sport')->whereRaw("M_Name='$m_name'")->get();
        return response()->json([
            "success" => true,
            "data" => $items,
            "count" => $count
        ], 200);
    }

    // ADD Temp of BET
    public function addTemp(Request $request)
    {
        $temp = new WebReportTemp;
        $temp->type = $request->type;
        $temp->title = $request->title;
        $temp->league = $request->league;
        $temp->m_team = $request->m_team;
        $temp->t_team = $request->t_team;
        $temp->select_team = $request->select_team;
        $temp->text = $request->text;
        $temp->rate = $request->rate;
        $temp->gold = $request->gold;
        $temp->m_win = $request->m_win;
        $temp->uid = $request->uid;
        $temp->gid = $request->gid;
        $temp->line_type = $request->line_type;
        $temp->g_type = $request->g_type;
        $temp->active = $request->active;
        $temp->save();
        $responseMessage = "添加成功";
        return response()->json([
            'success' => true,
            'message' => $responseMessage
        ], 200);
    }

    // DELETE Temps
    public function deleteTemps()
    {
        WebReportTemp::query()->delete();
        $responseMessage = "删除成功";
        return response()->json([
            'success' => true,
            'message' => $responseMessage
        ], 200);
    }

    // GET Temps
    public function getTemps()
    {
        $temps = WebReportTemp::all();
        return response()->json([
            'success' => true,
            'data' => $temps
        ], 200);
    }

    // Edit Temp
    public function editTemp(Request $request)
    {
        $gold = $request->gold;

        $validator = Validator::make($request->all(), [
            'gold' => 'required',
        ]);

        if ($validator->fails())
            return response()->json([
                'success' => false,
                'message' => $validator->messages()->toArray()
            ]);
        WebReportTemp::where('id', $request->id)->update(['gold' => $gold, 'm_win' => $request->m_win]);
        $responseMessage = "更新成功";
        return response()->json([
            'success' => true,
            'message' => $responseMessage
        ], 200);
    }
}
