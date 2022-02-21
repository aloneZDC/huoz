<?php
//投票 俱乐部
namespace app\h5\controller;

use app\common\model\Areas;
use app\common\model\StoresCardLog;
use app\common\model\StoresConfig;
use app\common\model\StoresConvertConfig;
use app\common\model\StoresConvertLog;
use app\common\model\StoresFinancialLog;
use app\common\model\StoresList;
use app\common\model\StoresListSearch;
use app\common\model\StoresMainProject;
use app\common\model\UserAgentAddress;
use think\Db;
use think\Exception;
use think\Log;
use think\Request;

class Stores extends Base
{
    protected $is_decrypt = false; //不验证签名
    protected $public_action = ["areas","apply_index"];

    //线下商家首页
    public function index() {
        $r['code']= SUCCESS;
        $r['message']=lang("data_success");
        $r['result']=null;

        $r['result']['banner'] = Db::name('Flash')->field('title,pic')->where(['type'=>9,'lang'=>$this->lang])->order('sort asc')->limit(8)->select();
        $r['result']['apply_info'] = StoresList::apply_info($this->member_id,true);
        $r['result']['main_projects'] = StoresMainProject::get_list();

        $verify_state = Db::name('verify_file')->where(['member_id' => $this->member_id])->value('verify_state');
        if(!$verify_state && !is_numeric($verify_state)) $verify_state = -1;
        $r['result']['verify_state'] = $verify_state;

        $this->output_new($r);
    }

    public function index_map() {
        $r['code']= ERROR1;
        $r['message'] = lang("not_data");
        $r['result']=null;

        //经纬度
        $longitude = input('longitude','');
        $latitude = input('latitude','');


        if(is_numeric($longitude) && is_numeric($latitude)) {
            $stores = StoresListSearch::get_my_near_stores($latitude,$longitude,1,50);
            if($stores['code']==SUCCESS) {
                $r['result'] = $stores['result'];
                $r['code'] = SUCCESS;
                $r['message'] = lang('data_success');
            }
        }
        $this->output_new($r);
    }

    //线下商家分类首页
    public function index_main_projects() {
        $main_project_id = intval(input('main_project_id'));
        $longitude = input('longitude','');
        $latitude = input('latitude','');
        $page =intval(input('page',0));
        $rows =intval(input('rows',0));
        if($rows<=0 || $rows>20) $rows = 10;
        $stores = StoresListSearch::get_my_near_stores($latitude,$longitude,$page,$rows,$main_project_id);
        $this->output_new($stores);
    }

    public function index_search() {
        $search = strval(input('search',''));
        $longitude = input('longitude','');
        $latitude = input('latitude','');
        $page =intval(input('page',0));
        $rows =intval(input('rows',0));
        if($rows<=0 || $rows>20) $rows = 10;
        $stores = StoresListSearch::get_my_near_stores($latitude,$longitude,$page,$rows,0,$search);
        $this->output_new($stores);
    }

    //店铺首页
    public function stores_home() {
        $stores_id = intval(input('stores_id'));
        $stores = StoresList::stores_home($this->member_id,$stores_id);
        $this->output_new($stores);
    }

    //在线商家申请列表
    public function apply_index() {
        $r['code']= SUCCESS;
        $r['message']=lang("data_success");
        $r['result']=null;

        $r['result'] = StoresMainProject::get_list();
        $this->output_new($r);
    }

    //线下商家申请详情
    public function apply_info() {
        $r['code']= SUCCESS;
        $r['message']=lang("data_success");
        $r['result']=null;

        $r['result'] = StoresList::apply_info($this->member_id);
        $this->output_new($r);
    }

    //申请成为线下商家
    public function apply()
    {
        $apply_image = input('apply_image', '');
        $banner_image = input('banner_image', '');
        // $legal_person_image = input('legal_person_image','');
        $logo = input('logo', '');
        $province_id = intval(input('province_id'));
        $city_id = intval(input('city_id'));
        $area_id = intval(input('area_id'));

        $name = input('name');
        $phone = input('phone');
        $address = input('address');
        $stores_name = input('stores_name');
        // $main_project_id = intval(input('main_project_id'));

        //经纬度
        $longitude = input('longitude', '');
        $latitude = input('latitude', '');

        //营业时间 前端提交的是从1开始的 统一减去1
        $week_start = intval(input('week_start')) - 1;
        $week_stop = intval(input('week_stop')) - 1;
        $hour_start = intval(input('hour_start')) - 1;
        $hour_stop = intval(input('hour_stop')) - 1;

        $agent_province_id = intval(input('agent_province_id'));
        $agent_city_id = intval(input('agent_city_id'));
        $agent_area_id = intval(input('agent_area_id'));

        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (empty($agent_area_id) or empty($agent_city_id) or empty($agent_province_id)) {
            return $this->output_new($r);
        }

        $verify_info = Db::name('verify_file')->field('verify_state')->where(['member_id' => $this->member_id])->find();
        if (!$verify_info) {
            $r['code'] = 30100;
            $r['message'] = lang("lan_user_authentication_first");
            $this->output_new($r);
        }
        if ($verify_info['verify_state'] == 2) {
            $r['code'] = 30100;
            $r['message'] = lang("lan_user_authentication_first_wait");
            $this->output_new($r);
        }
        if ($verify_info['verify_state'] != 1) {
            $r['code'] = 30100;
            $r['message'] = lang("lan_user_authentication_first");
            $this->output_new($r);
        }

        foreach (['apply_image', 'banner_image', 'logo'] as $field) {
            if ($$field) {
                $attachments_list = $this->oss_base64_upload($$field, 'stores');
                if ($attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0 || empty($attachments_list)) {
                    $r['message'] = lang("lan_network_busy_try_again");
                    return $r;
                }
                $$field = $attachments_list['Msg'][0];
            }
        }
        try {
            Db::startTrans();
            $agentFlag = UserAgentAddress::addAgent($this->member_id, $agent_province_id, $agent_city_id, $agent_area_id);
            if (empty($agentFlag)) {
                $r['message'] = lang('system_error_please_try_again_later');
                return $this->output_new($r);
            }

            $result = StoresList::apply($this->member_id, $logo, $apply_image, $banner_image,/*$legal_person_image,*/ $name, $phone, $stores_name, /*$main_project_id, */$province_id, $city_id, $area_id, $address, $longitude, $latitude, $week_start, $week_stop, $hour_start, $hour_stop);
            Db::commit();
            return $this->output_new($result);
        } catch (\Exception $exception) {
            Db::rollback();
            $r['message'] = lang('system_error_please_try_again_later');
            return $this->output_new($r);
        }

    }


    //可用兑换 卡包I券 列表
    public function num_to_card() {
        $r['code']=ERROR1;
        $r['message']=lang("data_success");
        $r['result']=null;

        $list = StoresConvertConfig::num_to_card();
        if($list) $r['result'] = $list;
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
        $result = StoresCardLog::get_list($this->member_id,$income_type,$page);
        $this->output_new($result);
    }

    //理财包详情列表
    public function financial_list() {
        $page = intval(input('page'));
        $income_type = input('income_type','');
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

    public function card_num() {
        $type = input('type','num');
        $result = StoresConfig::card_num($this->member_id,$type);
        $this->output_new($result);
    }
}
