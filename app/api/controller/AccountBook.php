<?php
/**
 *发送邮件
 */
namespace app\api\controller;
use app\common\model\CurrencyUserTransfer;
use think\Db;
use think\Exception;

class AccountBook extends Base
{
    protected $is_method_filter = true;

    //账本记录
    public function index() {
        $currency_id = input('currency_id',0,'intval');
        $type = input('type',0,'intval');

        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');

        $real_type = input('real_type','');
        $where = [];
        switch ($real_type)   {
            case 'chong':
                $where['type'] = 5;
                break;
            case 'ti':
                $where['type'] = 6;
                break;
            case 'ct':
                $where['type'] = ['in',[5,6]];
                break;
        }

        $count = false;
        $list = model('AccountBook')->getLog($this->member_id,$currency_id,$type,$page,$page_size,$this->lang,$count,$where);
        $this->output(10000,lang('lan_operation_success'),$list);
    }

    //详情
    public function info() {
        $id = input('book_id',0,'intval');
        $info = Db::name('accountbook')->where(['id'=>$id,'member_id'=>$this->member_id])->find();
        if(!$info) $this->output(10001,lang('not_data'));

        if(!in_array($info['type'], [5,6,7,600])) $this->output(10001,lang('not_data'));

        // 转账详情记录
        if($info['type'] == 600) {
            $transferLog= $this->transferLog($info['third_id']);
            $this->output(10000,lang('lan_operation_success'),$transferLog);
        }

        $tibi = Db::name('tibi')->alias('a')
                    ->field('a.to_url as address,a.from_url as url2,a.status,a.num,a.actual,a.fee,a.add_time,a.remark as remarks,b.currency_mark as currency_name,a.tag,a.ti_id')
                    ->join('__CURRENCY__ b','a.currency_id=b.currency_id','LEFT')
                    ->where(['id'=>$info['third_id']])->find();
        if(!$tibi) $this->output(10001,lang('not_data'));
        if(!empty($tibi['remark']))$tibi['remark']="";
        $tibi['to_url'] = $tibi['address'];
        //原生type 0充币 1提币
        if($info['number_type']==1){
            $tibi['type'] = 0;
//            $tibi['address'] = $tibi['url2'] ?: '';
            $tibi['fee'] = 0;
        } else {
            $tibi['type'] = 1;
            //$tibi['actual'] = $tibi['num'];
        }

        //数据库 0为提币中 1为提币成功  2为充值中 3位充值成功 8兑换 -1 审核中 -2 撤销
        if($tibi['status']==1 || $tibi['status']==3) {
            $tibi['status'] = 1;
        } elseif ($tibi['status']==0 || $tibi['status']==-1) {
            $tibi['status'] = 0;
        } elseif ($tibi['status']==-2) {
            $tibi['status'] = -1;
        } else {
            $tibi['status'] = 0;
        }
       // if($tibi['currency_name']=="XRP")$tibi['address']."_".$tibi['tag'];
        //原生 -1已撤销 0审核中 1已完成
        $tibi['add_time'] = date('Y-m-d H:i:s',$tibi['add_time']);
        if(strtolower($tibi['currency_name'])=='erc20') $tibi['currency_name'] = 'USDT';
        $this->output(10000,lang('lan_operation_success'),$tibi);
    }

    /**
     * 转账详情记录
     * @param $cut_id
     * @return array|bool|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function transferLog($cut_id)
    {
        $transferLog = CurrencyUserTransfer::alias('t1')
            ->field(['t1.cut_num' => 'num', 't1.cut_fee' => 'fee', 't1.cut_add_time' => 'add_time', 't1.cut_memo' => 'remarks', 't1.cut_hash' => 'ti_id', 't2.chongzhi_url' => 'to_url'])
            ->join([config("database.prefix") . 'currency_user' => 't2'], ['t2.member_id = t1.cut_target_user_id', 't2.currency_id = t1.cut_currency_id'], 'LEFT')
            ->where(['t1.cut_id' => $cut_id])
            ->find();

        $transferLog['actual'] = keepPoint(($transferLog['num'] - $transferLog['fee']),6);
        $transferLog['add_time'] = date('Y-m-d H:i:s',$transferLog['add_time']);

        return $transferLog;
    }
}
