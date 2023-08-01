<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LotteryUserConfig extends Model
{
    use HasFactory;
    protected $fillable = [
        "userid",
        "username",
        "cq_lower_bet",
        "cq_max_bet",
        "cq_bet",
        "cq_bet_reb",
        "jx_lower_bet",
        "jx_bet",
        "jx_bet_reb",
        "tj_bet",
        "tj_bet_reb",
        "gdsf_lower_bet",
        "gdsf_bet",
        "gdsf_bet_reb",
        "gxsf_lower_bet",
        "gxsf_bet",
        "gxsf_bet_reb",
        "tjsf_lower_bet",
        "tjsf_bet",
        "tjsf_bet_reb",
        "bjpk_lower_bet",
        "bjpk_bet",
        "bjpk_bet_reb",
        "xyft_lower_bet",
        "xyft_bet",
        "xyft_bet_reb",
        "ffc5_lower_bet",
        "ffc5_bet",
        "ffc5_bet_reb",
        "txssc_lower_bet",
        "txssc_bet",
        "txssc_bet_reb",
        "twssc_lower_bet",
        "twssc_bet",
        "twssc_bet_reb",
        "azxy5_lower_bet",
        "azxy5_bet",
        "azxy5_bet_reb",
        "azxy10_lower_bet",
        "azxy10_bet",
        "azxy10_bet_reb",
        "bjkn_lower_bet",
        "bjkn_bet",
        "bjkn_bet_reb",
        "gd11_lower_bet",
        "gd11_bet",
        "gd11_bet_reb",
        "t3_lower_bet",
        "t3_bet",
        "t3_bet_reb",
        "d3_lower_bet",
        "d3_bet",
        "d3_bet_reb",
        "p3_lower_bet",
        "p3_bet",
        "p3_bet_reb",
        "cqsf_lower_bet",
        "cqsf_bet",
        "cqsf_bet_reb",
        "jx_max_bet",
        "tj_max_bet",
        "gdsf_max_bet",
        "gxsf_max_bet",
        "tjsf_max_bet",
        "bjpk_max_bet",
        "xyft_max_bet",
        "ffc5_max_bet",
        "txssc_max_bet",
        "twssc_max_bet",
        "azxy5_max_bet",
        "azxy10_max_bet",
        "bjkn_max_bet",
        "gd11_max_bet",
        "t3_max_bet",
        "d3_max_bet",
        "p3_max_bet",
        "cqsf_max_bet",
    ];
    protected $table = "lottery_user_config";
    public $timestamps = false;
}
