<?php
//投票 俱乐部
namespace app\api\controller;

use app\common\model\Areas;
use app\common\model\CurrencyUser;
use app\common\model\StoresCardLog;
use app\common\model\StoresConfig;
use app\common\model\StoresConvertConfig;
use app\common\model\StoresConvertLog;
use app\common\model\StoresFinancialLog;
use app\common\model\StoresList;
use app\common\model\StoresMainProject;
use think\Db;
use think\Exception;
use think\Request;

class Stores extends Base
{
    protected $is_decrypt = false; //不验证签名
    protected $public_action = ["areas"];

    //卡包
    public function card_index() {
        $result = StoresConfig::card_index($this->member_id);
        $this->output_new($result);
    }

    public function card_num() {
        $type = input('type','num');
        $result = StoresConfig::card_num($this->member_id,$type);
        $this->output_new($result);
    }

    //可用兑换 卡包I券 列表
    public function num_to_card()
    {
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = null;

        $list = StoresConvertConfig::num_to_card();
        if ($list) {
            $from =[];
            foreach ($list as $item) {
                if(!isset($from[$item['to_currency_id']])) {
                    $user_currency = CurrencyUser::getCurrencyUser($this->member_id,$item['to_currency_id']);
                    $from[$item['to_currency_id']] = [];
                    $from[$item['to_currency_id']]['to_currency'] = [
                        'currency_id' => $item['to_currency_id'],
                        'currency_name' => $item['to_currency_name'],
                        'user_num' => $user_currency ? $user_currency[StoresConvertConfig::CARD_FIELD] : 0,
                    ];
                }

                $user_currency = CurrencyUser::getCurrencyUser($this->member_id,$item['currency_id']);
                $from[$item['to_currency_id']]['currency'][] = [
                    'currency_id' => $item['currency_id'],
                    'currency_name' => $item['currency_name'],
                    'ratio' => $item['to_currency_inc_percent'],
                    'min_num' => $item['min_num'],
                    'max_num' => $item['max_num'],
                    'fee' => $item['fee'],
                    'user_num' => $user_currency ? $user_currency[StoresConvertConfig::NUM_FIELD] : 0,
                ];
            }
            $from = array_values($from);
            $r['result'] = $from;
        }
        $this->output_new($r);
    }

    //IO积分 兑换  卡包I券锁仓
    public function num_card() {
        $num = input('num');
        $currency_id = intval(input('currency_id'));
        $to_currency_id = intval(input('to_currency_id'));
        if(empty($currency_id)) {
            $list = StoresConvertConfig::num_to_card();
            if(!empty($list)) {
                $currency_id = $list[0]['currency_id'];
                $to_currency_id = $list[0]['to_currency_id'];
            }
        }
        $result = StoresConvertLog::convert_num_to_card($this->member_id,$currency_id,$to_currency_id,$num);
        $this->output_new($result);
    }

    //卡包I券 兑换 理财包O券 列表
    public function card_to_financial() {
        $r['code']=ERROR1;
        $r['message']=lang("data_success");
        $r['result']=null;

        $list = StoresConvertConfig::card_to_financial();
        if($list) $r['result'] = $list;
        $this->output_new($r);
    }

    //卡包I券 兑换 理财包O券 列表
    public function card_financial() {
        $num = input('num');
        $currency_id = intval(input('currency_id'));
        $to_currency_id = intval(input('to_currency_id'));
        if(empty($currency_id)) {
            $list = StoresConvertConfig::card_to_financial();
            if(!empty($list)) {
                $currency_id = $list[0]['currency_id'];
                $to_currency_id = $list[0]['to_currency_id'];
            }
        }
        $result = StoresConvertLog::convert_card_to_financial($this->member_id,$currency_id,$to_currency_id,$num);
        $this->output_new($result);
    }

    //卡包详情列表
    public function card_list() {
        $page = intval(input('page'));
        $income_type = input('income_type','');
        if($income_type=='ins') $income_type = 'in';
        $result = StoresCardLog::get_list($this->member_id,$income_type,$page);
        $this->output_new($result);
    }

    //理财包详情列表
    public function financial_list() {
        $page = intval(input('page'));
        $income_type = input('income_type','');
        if($income_type=='ins') $income_type = 'in';
        $result = StoresFinancialLog::get_list($this->member_id,$income_type,$page);
        $this->output_new($result);
    }

    public function areas() {
        $type = intval(input('type'));
        $parent_id = intval(input('parent_id'));
        $list = Areas::get_list($type,$parent_id);

        $r['code']= SUCCESS;
        $r['message']=lang("data_success");
        $r['result']= $list;
        $this->output_new($r);
    }
}