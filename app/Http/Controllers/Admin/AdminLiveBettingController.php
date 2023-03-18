<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Web\Report;
use App\Utils\Utils;
use App\Models\Sport;
use Exception;


class AdminLiveBettingController extends Controller
{
    //
    public function getItems(Request $request)
    {

        $response = [];
        $response['success'] = FALSE;
        $response['status'] = STATUS_BAD_REQUEST;
        try {
            $m_date = $request['m_date'] ?? date('Y-m-d');
            // $mids = Report::select('MID')->where('M_Date', $m_date)->get();

            $data = array();

            $mids = Report::where('M_Date', $m_date)->where(function($query) {
                        $query->where('LineType', 9)
                            ->orWhere('LineType', 19)
                            ->orWhere('LineType', 10)
                            ->orWhere('LineType', 20)
                            ->orWhere('LineType', 21)
                            ->orWhere('LineType', 31);
                    })->orderBy('BetTime', "desc")->get();
            $items = Sport::select('MID')->get();

            foreach($mids as $row){


                if($row['Cancel'] == 1){
                    $operate = '<font color=red><b>恢复</b></font></a>';
                }else {
                    $operate = '<font color=blue><b>正常</b></font>';
                }


                //  state
                if($row['Active'] == 0){
                    $state = '结算';
                }else if($row['Active'] == 1){
                    $state = '<font color=red>未结算</font>';
                }

                $temp = array(
                    'id' => $row->id,
                    'betTime' => $row['BetTime'],
                    'userName' => $row['M_Name'],
                    'gameType' => $row['BetType'],
                    'content' => $row['Middle'],
                    'state' => $state,
                    'betAmount' => $row['BetScore'],
                    'winableAmount' => $row['Gwin'],
                    'result' => '0',
                    'operate' => $operate,
                    'function' => 'function',
                );
                array_push($data, $temp);
            }
            return $data;
        }catch (Exception $e) {
            $response['message'] = $e->getMessage() . ' Line No ' . $e->getLine() . ' in File' . $e->getFile();
            // $response['message'] = "ok";
            Log::error($e->getTraceAsString());
            $response['status'] = STATUS_GENERAL_ERROR;
        }
    }

    public function getFunctionItems() {
        $scors = Utils::Scores;
        $scors = array_splice($scors, 20, 23);
        $data = array();

        foreach($scors as $row) {
            $temp = array(
                'label' => $row,
                'value' => $row,
            );
            array_push($data, $temp);
        }
        return $data;
    }
}
