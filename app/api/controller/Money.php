<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/24
 * Time: 9:22
 */

namespace app\api\controller;

use app\common\model\CurrencyUser;
use app\common\model\Member;
use app\common\model\MoneyInterest;
use app\common\model\MoneyInterestConfig;
use app\common\model\Currency;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Request;

class Money extends Base
{
    /**
     *根据币种id获取持币生息期数列表
     * Created by Red.
     * Date: 2018/12/24 9:29
     */
    function getConfigByCurrenciId(){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $currency_id=input("post.currency_id");
        if(!empty($currency_id)){
            $list=MoneyInterestConfig::getConfigByCurrenciId($currency_id);
            if(!empty($list)){
                $currencyList=Currency::field("currency_id,currency_mark")->select()->toArray();
                $currencyList=array_column($currencyList,null,"currency_id");
                foreach ($list as &$value){
                    $value['add_time']=time();
                    $language = input('language');
                    // 国际化处理
                    if (strtolower($language) == "zh-tw" or strtolower($language) == "zh-cn") {
                        $value['title'] = $value['cn_title'];
                        $value['characteristic'] = $value['cn_characteristic'];
                        $value['details'] = $value['cn_details'];
                    } else {
                        $value['title'] = $value['en_title'];
                        $value['characteristic'] = $value['en_characteristic'];
                        $value['details'] = $value['en_details'];
                    }
                    /*$value['title']=strtolower(input('language'))=="zh-tw"?$value['cn_title']:$value['en_title'];
                    $value['characteristic']=strtolower(input('language'))=="zh-tw"?$value['cn_characteristic']:$value['en_characteristic'];
                    $value['details']=strtolower(input('language'))=="zh-tw"?$value['cn_details']:$value['en_details'];*/
                    $value['currency_mark']=isset($currencyList[$value["currency_id"]]['currency_mark'])?$currencyList[$value["currency_id"]]['currency_mark']:lang("lan_Invalid_currency");
                    unset($value['cn_title']);
                    unset($value['en_title']);
                    unset($value['en_characteristic']);
                    unset($value['cn_characteristic']);
                    unset($value['cn_details']);
                    unset($value['en_details']);
                }
                $r['code']=SUCCESS;
                $r['message']=lang("lan_data_success");
                $r['result']=$list;
            }else{
                $r['message']=lang("lan_not_data");
            }
        }

        $this->output_new($r);
    }

    /**
     * 持币生息
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index(Request $request)
    {
        $field = 'id, months, rate, cn_title, cn_characteristic, cn_details, en_title, en_characteristic, en_details, day_rate, type, days, add_time';
        $banners = Db::name('Flash')->field('flash_id,pic,jump_url,app_page')->order('sort asc')->where('type=7')->where('lang',$this->lang)->limit(8)->select();
        $hots = MoneyInterestConfig::where('months', 12)->field($field)->find();
        $boutiques = MoneyInterestConfig::where('months', '<>', 12)->field($field)->group('months')->select();
        // 多语言处理
        $language = $request->post('language', 'zh-cn');
        $this->formatConfig($hots, $language);
//        $currencyList=Currency::field("currency_id,currency_mark")->select()->toArray();
//        $currencyList=array_column($currencyList,null,"currency_id");
        foreach ($boutiques as $key => &$boutique) {
           $this->formatConfig($boutique, $language);
        }


        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => [
            'banners' => $banners,
            'hots' => $hots,
            'boutiques' => $boutiques
        ]]);
    }

    /**
     * 格式化数据
     * @param array $config
     * @param string $language
     */
    protected function formatConfig(&$config, $language = 'zh-cn')
    {
        $config['add_time'] = time();
        // 国际化处理
        if ($this->isChinese($language)) {
            $config['title'] = $config['cn_title'];
            $config['characteristic'] = $config['cn_characteristic'];
            $config['details'] = $config['cn_details'];
        } else {
            $config['title'] = $config['en_title'];
            $config['characteristic'] = $config['en_characteristic'];
            $config['details'] = $config['en_details'];
        }
//             $config['currency_mark']=isset($currencyList[$config["currency_id"]]['currency_mark'])?$currencyList[$config["currency_id"]]['currency_mark']:lang("lan_Invalid_currency");
        unset($config['cn_title']);
        unset($config['en_title']);
        unset($config['en_characteristic']);
        unset($config['cn_characteristic']);
        unset($config['cn_details']);
        unset($config['en_details']);
    }

    /**
     * @param string $language zh-tw|zh-cn|en-us
     * @return bool
     */
    private function isChinese($language = "zh-cn")
    {
        return strtolower($language) == "zh-tw" or strtolower($language) == "zh-cn";
    }

    protected $timeTypesArray = [
        'zh-cn' => [
            ['months_type' => 0, 'desc' => '按天'],
            ['months_type' => 3, 'desc' => '3个月'],
            ['months_type' => 6, 'desc' => '6个月'],
//            ['months_type' => 9, 'desc' => '9个月'],
            ['months_type' => 12, 'desc' => '12个月'],
        ],
        'en-us' => [
            ['months_type' => 0, 'desc' => 'days'],
            ['months_type' => 3, 'desc' => '3 months'],
            ['months_type' => 6, 'desc' => '6 months'],
//            ['months_type' => 9, 'desc' => '9 months'],
            ['months_type' => 12, 'desc' => '12 months'],
        ],
    ];

    /**
     * @param Request $request
     *
     */
    public function monthsTypes(Request $request)
    {
        // 时间类型
        $language = $request->post('language', 'zh-cn');
        if ($this->isChinese($language)) {
            $result = $this->timeTypesArray['zh-cn'];
        } else {
            $result = $this->timeTypesArray[$language];
        }
        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $result]);
    }

    /**
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getCurrencyByMonthsType(Request $request)
    {
        $monthsType = $request->post('months_type', null);
        $language = $request->post('language', 'zh-cn');
        if (is_null($monthsType)) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('lan_modifymember_parameter_error'), 'result' => []]);
        }
        $configs = MoneyInterestConfig::where('months', $monthsType)->order('id desc')->with('currency')->select();
        foreach ($configs as &$config) {
            $this->formatConfig($config, $language);
            $cu = CurrencyUser::getCurrencyUser($this->member_id, $config['currency_id']);;
            $config['num'] = $cu['num'] ? (double) $cu['num'] : 0;
        }

        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $configs]);
    }


    /**
     *持币生息可选币种列表
     * Created by Red.
     * Date: 2018/12/24 9:29
     */
    function getCurrencyList(){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $list=MoneyInterestConfig::getCurrencyList($this->member_id);
        if(!empty($list)){
            foreach ($list as &$value){
                $price=$this->exchange_rate_type=="CNY"?$this->getPriceByCurrencyId($value['currency_id'])['cny']:$this->getPriceByCurrencyId($value['currency_id'])['usd'];
                $value['cny']=keepPoint($price * $value['num'], 2);
                $value['new_price_unit']=$this->exchange_rate_type=="CNY"?"¥":"$";
            }
            $r['code']=SUCCESS;
            $r['message']=lang("lan_data_success");
            $r['result']=$list;
        }else{
            $r['message']=lang("lan_not_data");
        }
        $this->output_new($r);
    }

    /**
     * 持币生息操作提交
     * @param int $id               期数表id
     * @param float $num            数量
     * Created by Red.
     * Date: 2018/12/24 11:44
     */
    function addMoneyInterest(){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        //关闭持币生息功能
        // $this->output_new($r);
        $id=input("post.id");
        $num=input("post.num");
        $paypwd=input("post.paypwd");
        if(!empty($id)&&!empty($num)&&!empty($paypwd)){
            //验证支付密码
            $password = Member::verifyPaypwd($this->member_id, $paypwd);
            if($password['code']==SUCCESS){
                $result=MoneyInterest::addMoneyInterest($this->member_id,$id,$num);
                if($result['code']==SUCCESS){
                    $MoneyInterest=MoneyInterest::where(['id'=>$result['result']])->find();
                    if(!empty($MoneyInterest)){
                        $MoneyInterest=$MoneyInterest->toArray();
                        $MoneyInterest['add_time']=date("Y-m-d H:i:s",$MoneyInterest['add_time']);
                        $MoneyInterest['end_time']=date("Y-m-d H:i:s",$MoneyInterest['end_time']);
                        $MoneyInterest['estimate_money'] = keepPoint($MoneyInterest['day_num'] * $MoneyInterest['days'] + $MoneyInterest['num'], 6);
                    }
                    $result['result']=$MoneyInterest;
                    $this->output_new($result);
                }else{
                    $this->output_new($result);
                }
            }else{
                $this->output_new($password);
            }

        }

        $this->output_new($r);

    }

    /**
     * 定存记录列表
     * @param int   $page           当前页数(默认第1页)
     * @param int   $rows           每页显示条目数(默认每页10条)
     * @param int   $type           2为已生息,1为生息中
     * Created by Red.
     * Date: 2018/12/24 11:49
     */
    function getMoneyInterestList(){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $r['image']="https://fama.oss-cn-hongkong.aliyuncs.com/images/exchange.png";
        $page=input("post.page",1);
        $rows=input("post.rows",10);
        $type=input("post.type");
        $list=MoneyInterest::getMoneyInterestList($this->member_id,$page,$rows,$type);
        if(!empty($list)){
            $r['code']=SUCCESS;
            $r['result']=$list;
            $r['message']=lang("lan_data_success");
        }else{
            $r['message']=lang("lan_not_data");
        }
        $this->output_new($r);
    }

    /**
     * 根据币种ID获取定存余额和余额
     * Created by Red.
     * Date: 2019/1/4 15:12
     */
    function getInfoByCuurencyId(){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $currency_id=input("post.currency_id");
        if(!empty($currency_id)){
            $currencyUser=CurrencyUser::getCurrencyUser($this->member_id,$currency_id,"num");
            $total=MoneyInterest::where(['member_id'=>$this->member_id,'currency_id'=>$currency_id,'status'=>0])->sum("num");
            $money=isset($currencyUser->num)?$currencyUser->num:0;
            $r['code']=SUCCESS;
            $r['message']=lang("lan_data_success");
            $r['result']=["deposit_balance"=>$total,"num"=>$money];
        }
        $this->output_new($r);

    }

    /**
     * 支取接口
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function draw() {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];

        $id = input('post.id');
        if (empty($id)) {
            return $this->output_new($r);
        }
        $money = MoneyInterest::where([
            'id' => $id,
            'member_id' => $this->member_id
        ])->find();

        if ($money['status'] != 0) {
            $r['message'] = lang('lan_operation_failure');
            return $this->output_new($r);
        }

        if ($money['type'] != 2) {
            $r['message'] = lang('not_to_be_taken');
            return $this->output_new($r);
        }
        $startTime = strtotime(date("Y-m-d",time()));
        $day = ($startTime - $money['add_time']) / 86400;
        if ($day > 0) {
            $interest = ceil($day) * $money['day_num']; // 利息
        } else {
            $interest = 0;
        }
        $totalMoney = keepPoint($money['num'] + $interest, 6);
        // 更新状态为已生息
        try {
            Db::startTrans();
            $update = MoneyInterest::where('id', $money['id'])->update([
                'status' => 1,
                'profit_time' => time()
            ]);
            if (!$update) {
                throw new Exception(lang('lan_operation_failure'));
            }

            $addAccountBook = model('AccountBook')->addLog([
                'member_id' => $money['member_id'],
                'currency_id' => $money['currency_id'],
                'type' => 12,
                'content' => 'lan_interest_title',
                'number_type' => 1,
                'number' => $totalMoney,
                'third_id' => $money['id']
            ]);
            if (!$addAccountBook) {
                throw new Exception(lang("lan_operation_failure"));
            }
            $currencyUser = CurrencyUser::getCurrencyUser($money['member_id'], $money['currency_id']);
            $currencyUser->num += $totalMoney;
            if (!$currencyUser->save()) {
                throw new Exception(lang("lan_operation_failure"));
            }

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            Db::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $e->getMessage();
        }

        return $this->output_new($r);
    }

}
