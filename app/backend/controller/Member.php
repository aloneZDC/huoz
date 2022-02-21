<?php

namespace app\backend\controller;

use app\common\model\CurrencyUser;
use app\common\model\MemberContract;
use app\common\model\VerifyFile;
use think\Db;
use think\Exception;
use think\Request;

/**
 * 会员管理
 * Class Member
 * @package app\backend\controller
 */
class Member extends AdminQuick
{
    protected $pid = 'member_id';
    protected $public_action = ['getchildnode', 'getuserinfo',
        'member_details', 'user_turn_on_currency', 'user_info', 'user_pay_log', 'user_transaction', 'user_turn_out_currency', 'mutualtransfer', 'orders_trade_log', 'accountbook'
    ];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('member');
    }

    // 合同下载
    public function contract_download(Request $request)
    {
        $member_id = $request->param('member_id', 1, 'intval');
        if ($member_id <= 0) {
            $this->error('参数错误');
        }
        $MemberContract = MemberContract::where(['member_id' => $member_id])->find();
        if (!$MemberContract) {
            $this->error('未签合同');
        }

        //目錄
        $path_url = RUNTIME_PATH . "download/";
        //创建临时目录(没有temp目录的需要先手动创建，或用mkdir创建)
        if (!is_dir($path_url)) {
            if ((mkdir($path_url, 0777, true)) === false) {
                $this->error('临时目录创建失败！');
            }
        }

        $contract_list = [
            'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/hzyc_template/1.jpg',
            'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/hzyc_template/2.jpg',
            'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/hzyc_template/3.jpg',
            'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/hzyc_template/4.jpg',
        ];
        $result_json = json_decode($MemberContract['contract_text']);
        $contract_list[1] = $result_json[1];
        $contract_list[3] = $result_json[2];
        foreach ($contract_list as $key => $value) {
            $img_url = file_get_contents($value);
            file_put_contents($path_url . $key . '.jpg', $img_url); //写入文件
        }

        //批量下载
        $zip = new \ZipArchive();

        //压缩包的名称
        $filename = 'temp.zip';
        $zip_file = $path_url . $filename;

        //打开一个zip文件；\ZipArchive::OVERWRITE覆盖，\ZipArchive::CREATE創建
        $zip->open($zip_file, \ZipArchive::OVERWRITE | \ZipArchive::CREATE);

        $contract_list = [
            $path_url . '0.jpg',
            $path_url . '1.jpg',
            $path_url . '2.jpg',
            $path_url . '3.jpg',
        ];

        //把图片一张一张加进去压缩
        foreach ($contract_list as $key => $value) {
            //将文件添加到压缩包；第一個參數将文件写入zip，第二個參數是文件的重命名（同时防止多级目录出现）
            $zip->addFile($value, "$key.jpg");
        }
        //关闭ziparchive
        $zip->close();

        //可以直接重定向下载
//        header('Location:'.$zip_file);

        //或者输出下载
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header('Content-disposition: attachment; filename=' . basename($zip_file)); //获取文件名；basename：返回路径中的文件名部分,
        header("Content-Type: application/force-download");//强制下载
        header("Content-Transfer-Encoding: binary");//二进制传输
        header('Content-Length: ' . filesize($zip_file)); //告诉浏览器，文件大小
        readfile($zip_file);

        //下载后删除临时目录
        if (is_dir($path_url)) {//is_dir目录存在返回 TRUE
            $this->delDirAndFile($path_url);
        }
    }

    //删除目录
    protected function delDirAndFile($dirName)
    {
        if ($handle = opendir($dirName)) {
            while (false !== ($item = readdir($handle))) {
                if ($item != "." && $item != "..") {
                    if (is_dir("$dirName/$item")) {
                        $this->delDirAndFile("$dirName/$item");
                    } else {
                        unlink("$dirName/$item");
                    }
                }
            }
            closedir($handle);
            rmdir($dirName);
        }
    }

    // 合同列表
    public function contract_list(Request $request)
    {
        if ($request->isAjax()) {
            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');
            $where = [];
            $member_id = $request->param('member_id', 0, 'intval');
            if ($member_id > 0) $where['ym.member_id'] = $member_id;

            $list = \app\common\model\Member::where($where)
                ->alias('ym')
                ->join('member_contract mc', 'ym.member_id = mc.member_id', 'left')
                ->field('ym.member_id,phone,email,ename,mc.add_time')
                ->order(['member_id' => 'desc'])->page($page, $limit)->select();
            foreach ($list as &$item) {
                if ($item['phone'] == '') {
                    $item['phone'] = $item['email'];
                }
                if (!empty($item['add_time'])) {
                    $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);
                    $item['download'] = '<a class="layui-btn layui-btn-xs" href="/backend/Member/contract_download?param=contract&member_id=' . $item['member_id'] . '">下载</a>';
                }
            }

            $count = \app\common\model\Member::where($where)
                ->alias('ym')
                ->join('member_contract mc', 'ym.member_id = mc.member_id', 'left')
                ->count();
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        $member_count = \app\common\model\Member::count();
        $contract_count = \app\common\model\MemberContract::count();
        $future_count = keepPoint($member_count - $contract_count, 0);
        return $this->fetch(null, compact('member_count', 'contract_count', 'future_count'));
    }

    public function index(Request $request)
    {
        $ename = input('ename');
        $email = input('email');
        $member_id = input('member_id');
        $name = input('name');
        $phone = input('phone');
        $pid = input('pid');
        $status = input('status');
        $invit_code = input('invit_code');
        $where = [];
        if (!empty($ename)) {
            $where['ename'] = $ename;
        }
        if (!empty($email)) {
            $where['email'] = $email;
        }
        if (!empty($member_id)) {
            $where['member_id'] = $member_id;
        }
        if (!empty($name)) {
            $where['name'] = $name;
        }
        if (!empty($phone)) {
            $where['phone'] = $phone;
        }
        if (!empty($pid)) {
            $where['pid'] = $pid;
        }
        if (!empty($invit_code)) {
            $where['invit_code'] = $invit_code;
        }

        if (!empty($status)) {
            $where['status'] = $status;
        }

        // 钱包地址筛选
        $chongzhi_url = input('chongzhi_url', '');
        if (!empty($chongzhi_url)) {
            $CurrencyUser = \app\common\model\CurrencyUser::where('chongzhi_url', $chongzhi_url)->find();
            if (!empty($CurrencyUser->member_id)) {
                $where['member_id'] = $CurrencyUser->member_id;
            } else {
                $where['member_id'] = 0;
            }
        }

        $list = $this->model->where($where)->order($this->pid . " desc")->paginate(null, null, ["query" => $this->request->get()]);
        foreach ($list as &$item) {
            if($item['send_type'] == 2) {
                $item['phone'] = $item['email'];
            }
//            if (empty($item['phone'])) $item['phone'] = $item['email'];
        }
        $page = $list->render();
        $count = $list->total();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 修改用户状态
     * @return array
     */
    public function disable_switch()
    {
        $member_id = intval(input("member_id"));
        $status = intval(input("status"));
        $status = $status ?: 2;
        if (!empty($member_id)) {
            $update = $this->model->where(['member_id' => $member_id])->update(['status' => $status, 'token_value' => md5($member_id . time())]);
            if ($update) {
                //清除登录的token
                if ($status == 2) {
                    cache('auto_login_' . $member_id, null);//清除app的登录信息
                    cache('pc_auto_login_' . $member_id, null);//清除pc的登录信息
                }
                return $this->successJson(SUCCESS, "操作成功", null);
            } else {
                return $this->successJson(ERROR1, "操作失败", null);
            }
        }
        return $this->successJson(ERROR1, "参数错误", null);
    }

    //下级资产数量
    public function child_currency_num()
    {
        $member_id = intval(input('member_id'));

        //下级合约资产数量
        $currency_list = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->where(['is_line' => 1])->select();
        $currency_list = array_column($currency_list->toArray(), null, 'currency_id');
        foreach ($currency_list as &$currency) {
            $currency['num'] = $currency['forzen_num'] = $currency['hb_num'] = $currency['dnc_lock'] = $currency['contract_num'] = 0;
        }

        if ($member_id) {
            //下级资产总数量
            $num = Db::query('select currency_id,sum(dnc_lock) as dnc_lock,sum(dnc_other_lock) as dnc_other_lock,sum(num) as num,sum(forzen_num) as forzen_num from ' . config("database.prefix") . 'currency_user  
            where member_id in(
                select child_id from ' . config("database.prefix") . 'member_bind where member_id=' . $member_id . '
            ) group by currency_id');

            if ($num) {
                $num = array_column($num, null, 'currency_id');
                foreach ($num as $item) {
                    if ($item['currency_id'] && isset($currency_list[$item['currency_id']])) {
                        $currency_list[$item['currency_id']]['num'] = $item['num'];
                        $currency_list[$item['currency_id']]['forzen_num'] = $item['forzen_num'];
                        $currency_list[$item['currency_id']]['dnc_lock'] = $item['dnc_lock'] + $item['dnc_other_lock'];
                    }
                }
            }

            //下级合约冻结数量
            $contract_num = Db::query('select money_currency_id,sum(money_currency_num) as num from ' . config("database.prefix") . 'contract_order
			where  money_type=1 AND `status` IN (1,2,3,4) and member_id in (
				select child_id from ' . config("database.prefix") . 'member_bind where member_id=' . $member_id . '
			)');
            if ($contract_num) {
                $contract_num = array_column($contract_num, null, 'currency_id');
                foreach ($contract_num as $item) {
                    if ($item['money_currency_id'] && isset($currency_list[$item['money_currency_id']])) $currency_list[$item['money_currency_id']]['contract_num'] = $item['num'];
                }
            }
        }
        return $this->fetch(null, compact('currency_list'));
    }

    //编辑
    public function edit(Request $request)
    {
        if ($request->isPost()) {
            $id = intval(input('id'));

            $form = input('form/a');
            $info = $this->model->where([$this->pid => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $form = $this->editFilter($form);
            if (!empty($form['phone']) && !empty($form['email'])) {
                return $this->successJson(ERROR1, "手机号和邮箱不能同时存在", null);
            }
            $form['send_type'] = !empty($form['phone']) ? 1 : 2;
            $result = $this->model->save($form, [$this->pid => $info[$this->pid]]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        }

        $id = intval(input('id'));
        $info = $this->model->where([$this->pid => $id])->find();
        if (empty($info)) return $this->error("该记录不存在");

        return $this->fetch(null, compact('info'));
    }

    /**
     * 树形结构
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ztree()
    {
        $where = [];
        $phone = input('phone');
        if (!empty($phone)) {
            if (checkEmail($phone)) {
                $where['email'] = $phone;
            } else {
                $where['phone'] = $phone;
            }
        }
        $member_id = input('member_id', '');
        if ($member_id) {
            $where['member_id'] = $member_id;
        }
        $ename = input('ename', '');
        if ($ename) {
            $where['ename'] = ['like', "%{$ename}%"];
        }

        //根据分类或模糊查找数据数量
        $userinfo = [];
        $users = [];
        if (!empty($member_id) || !empty($phone) || !empty($ename)) {
            $userinfo = Db::name('member')->where($where)->find();
            if ($userinfo) {
                $account = $userinfo['phone'] ? $userinfo['phone'] : $userinfo['email'];
                $users = ["id" => $userinfo['member_id'], "pid" => 0, "name" => $userinfo['member_id'] . '_' . $account, "open" => false, "isParent" => true];
                $total_child = Db::name('member_bind')->where(['member_id' => $userinfo['member_id']])->count();
                $total_child_one = Db::name('member_bind')->where(['member_id' => $userinfo['member_id'], 'level' => 1])->count();
                $userinfo['next_leve_num'] = $total_child;
                $userinfo['total_child_one'] = $total_child_one;
//                $level_name = Db::name('order_total')->alias('a')->join('members_level b', 'a.member_level_id=b.level')->where(['member_id' => $userinfo['member_id']])->value('title');
//                $userinfo['level_name'] = $level_name;
            }
        }

        $where['phone'] = $phone;
        $where['member_id'] = $member_id;
        $users = json_encode($users);

        return $this->fetch('', compact('userinfo', 'where', 'users'));
    }

    /**
     * @标
     * 树形结构获取子节点数据
     */
    public function getchildnode()
    {
        $member_id = input('id');
        $model_member = Db::name('member');
        $model_bind = Db::name("member_bind");
        $user = $model_member->where(['member_id' => $member_id])->find();
        $userList = [];
        if ($user) {
            $list = $model_bind->where(['member_id' => $user['member_id'], 'level' => 1])->select();
            if ($list) {
                foreach ($list as $key => $val) {
                    $child_count = $model_bind->where(['member_id' => $val['child_id']])->count();
                    $child_count_one = $model_bind->where(['member_id' => $val['child_id'], 'level' => 1])->count();
                    if ($child_count > 0) {
                        $isParent = true;
                    } else {
                        $isParent = false;
                    }
                    $member_child = Db::name('member')->where(['member_id' => $val['child_id']])->field('phone,email')->find();
                    $account = '';
                    if ($member_child) $account = $member_child['phone'] ? $member_child['phone'] : $member_child['email'];
//                    $level_name = Db::name('order_total')->alias('a')->join('members_level b', 'a.member_level_id=b.level')->where(['member_id' => $val['child_id']])->value('title');
                    $userList[] = [
//                        'name' => $val['child_id'] . '_' . $account . '_等级:' . $level_name . '_直推' . $child_count_one . '_所有下级:' . $child_count,
                        'name' => $val['child_id'] . '_' . $account . '_直推' . $child_count_one . '_所有下级:' . $child_count,
                        'id' => $val['child_id'],
                        'pid' => $user['member_id'],
                        'isParent' => $isParent,
                    ];
                }
            }
        }
        return $this->ajaxReturn($userList);
    }

    /**
     * 获取用户信息
     * @param int $member_id 用户id
     * @param string $field 字段
     * @return array|bool|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getMemberInfo($member_id, $field = "email,phone")
    {
        if (!empty($member_id)) {
            return \app\common\model\Member::where(['member_id' => $member_id])->field($field)->find();
        }
        return null;
    }

    /**
     * @标
     * 树形结构用户信息
     */
    public function getuserinfo()
    {
        $user_id = input('post.user_id');
        $userinfo = \app\common\model\Member::alias("a")->field('member_id,ename,phone')->where(['member_id' => $user_id])->find();
        if (empty($userinfo)) {
            $total_child = Db::name('member_bind')->where(['member_id' => $userinfo['member_id']])->count();
            $total_child_one = Db::name('member_bind')->where(['member_id' => $userinfo['member_id'], 'level' => 1])->count();
            $userinfo['next_leve_num'] = $total_child;
            $userinfo['total_child_one'] = $total_child_one;
//            $level_name = Db::name('order_total')->alias('a')->join('members_level b', 'a.member_level_id=b.level')->where(['member_id' => $user_id])->value('title');
//            $userinfo['level_name'] = $level_name;
        }
        return $this->ajaxReturn($userinfo);
    }

    // 上级列表
    public function parents()
    {
        $member_id = input('member_id');
        $list = \app\common\model\MemberBind::alias('a')->field('a.*,m.email,m.phone,m.ename')
            ->join(config("database.prefix") . "member m", "a.member_id=m.member_id", "LEFT")
            ->where(['a.child_id' => $member_id])->limit(1000)->order('level desc')->select();
        return $this->fetch(null, compact('list'));
    }

    /**
     * 用户资料弹窗模版
     */
    public function member_details()
    {
        $user_id = input('member_id', 0, 'intval');
        if (!$user_id > 0) {
            $this->error("没有获取到用户ID。");
        }

        $this->assign('user_id', $user_id);
        $this->assign('module', Request::instance()->module());

        //红包锁仓待返还数量
//        $hongbao_wait_back = HongbaoLog::where(['user_id'=>$user_id,'is_back'=>0])->sum('num');
//        $this->assign('hongbao_wait_back',$hongbao_wait_back);

        //合约锁仓数量
//        $contract_order = ContractOrder::where(['member_id'=>$user_id,'money_type'=>1,'status'=>3])->sum('money_currency_num');
//        $this->assign('contract_order',$contract_order);

        //云攒金
        $air_num = \app\common\model\CurrencyUser::where('member_id', $user_id)->sum('air_num');
        $this->assign('air_num', $air_num);

        return $this->fetch();
    }

    /**
     * 充积分记录
     */
    public function user_turn_on_currency()
    {
        $user_id = I('post.user_id');
        $currency_id = I('post.currency_id', -1, 'trim,intval');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $where = [];

        if ($currency_id >= 0) {
            $where['tibi.currency_id'] = $currency_id;
        }

        $model = Db::name('tibi');
        $fields = "tibi.id,(case tibi.status when 0 then '提积分中' when 1 then '提积分成功' when 2 then '充值中' when 3 then '充值成功' end) status,tibi.from_url,tibi.num,tibi.actual,tibi.currency_id,(case tibi.add_time when 0 then '-' else from_unixtime(tibi.add_time, '%Y-%m-%d %H:%i:%s') end) as add_time,m.email,tibi.message1,tibi.message2,tibi.ti_id";

        $tibi_list = $model->alias('tibi')->field($fields)
            ->join("member m", "m.member_id=tibi.to_member_id", "left")
            ->where(['tibi.to_member_id' => $user_id, 'tibi.status' => ['in', [2, 3]]])->where($where)->order("tibi.add_time desc")->select();

        if (!empty($tibi_list)) {
            foreach ($tibi_list as &$value) {
                $value['currency_name'] = intval($value['currency_id']) === 0 ? "人民币" :
                    \app\common\model\Currency::where(['currency_id' => $value['currency_id']])->value('currency_name', '');
                if ($value['currency_name'] == "XRP") {
                    $value['ti_id'] = strtoupper($value['ti_id']);
                }
                unset($value['currency_id']);
            }
        }

        $tibi_count = $model->alias('tibi')->field("round(ifnull(sum(tibi.num), '0.0000'), 4) as totalnum,round(ifnull(sum(tibi.actual), '0.0000'), 4) as totalactual")
            ->join("currency c", "c.currency_id=tibi.currency_id", "left")
            ->where(['tibi.to_member_id' => $user_id, 'tibi.status' => 3])->where($where)->find();

        $result = [
            'currency_list' => $this->user_currency_list(),
            'tibi_list' => $tibi_list,
            'tibi_count' => [
                'totalnum' => $tibi_count['totalnum'],
                'totalactual' => $tibi_count['totalactual'],
            ],
            'currency_id' => $currency_id,
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 积分列表
     * @return array
     */
    private function user_currency_list()
    {
        $currency_list = [
            [
                'currency_id' => -1,
                'currency_name' => '全部',
            ],
//            [
//                'currency_id' => 0,
//                'currency_name' => '人民币',
//            ],
        ];

        $currency = \app\common\model\Currency::currency();
        foreach ($currency as $value) {
            $currency_list[] = [
                'currency_id' => $value['currency_id'],
                'currency_name' => $value['currency_name'] . ' - ' . lang('bfw_' . $value['account_type']),
            ];
        }
        return $currency_list;
    }

    /**
     * 会员信息
     */
    public function user_info()
    {
        $user_id = I('post.user_id');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $user_fields = "member_id,email,name,phone,rmb,forzen_rmb,from_unixtime(reg_time, '%Y-%m-%d %H:%i:%s') reg_time,ifnull(remarks, '暂无用户备注') remarks";
        $user_info = \app\common\model\Member::field($user_fields)->where(['member_id' => $user_id])->find();
        if (empty($user_info)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '用户不存在']);
        }

        $currency_fields = "c.currency_name,c.account_type,c.is_trade_currency,cu.num,cu.forzen_num,dnc_lock,dnc_other_lock,keep_num,cu.lock_num,
        cu.num_award,cu.sum_award,cu.currency_id,cu.internal_buy,cu.remaining_principal,cu.release_lock";
        $user_currency = CurrencyUser::alias("cu")->field($currency_fields)
            ->join("currency c", "c.currency_id=cu.currency_id")
            ->where(['cu.member_id' => $user_id, 'c.is_line' => 1])->select();

        $tibi_model = Db::name('tibi');
        $trade_model = Db::name('trade');

        if (!empty($user_currency)) {
            foreach ($user_currency as $key => &$value) {
                $value['dnc_lock'] = keepPoint($value['dnc_lock'] + $value['dnc_other_lock'], 6);
//                if($value['is_trade_currency']==1) {
//                    $value['currency_name'] .= '(币币)';
//                }
                // 增加账户名称
                $value['currency_name'] .= ' - ' . lang('bfw_' . $value['account_type']);

                $value['chongbi_num'] = $tibi_model->field("ifnull(round(sum(num), 4), '0.0000') as chongbi_num")->where(['to_member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'status' => 3, 'transfer_type' => "1"])->find()['chongbi_num'];
                //内部互转充币
                $value['hzchongbi_num'] = $tibi_model->field("ifnull(round(sum(`actual`), 4), '0.0000') as hzchongbi_num")->where(['to_member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'transfer_type' => "2"])->find()['hzchongbi_num'];
                $value['tibi_num'] = $tibi_model->field("ifnull(round(sum(num), 4), '0.0000') as tibi_num")->where(['from_member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'status' => 1, 'transfer_type' => "1"])->find()['tibi_num'];
                //内部互转提币
                $value['hztibi_num'] = $tibi_model->field("ifnull(round(sum(num), 4), '0.0000') as hztibi_num")->where(['from_member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'transfer_type' => "2"])->find()['hztibi_num'];
                $value['buy_num'] = $trade_model->field("ifnull(round(sum(num), 4), '0.0000') as buy_num")->where(['member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'type' => 'buy'])->find()['buy_num'];
                $value['sell_num'] = $trade_model->field("ifnull(round(sum(num), 4), '0.0000') as sell_num")->where(['member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'type' => 'sell'])->find()['sell_num'];
//                $value['issue_num'] = M("issue")->alias("issue")->field("ifnull(sum(issue_log.num), '0.0000') as num")->join("yang_issue_log as issue_log on issue.id = issue_log.iid")->where(['issue_log.uid' => $user_info['member_id'], 'issue.currency_id' => $value['currency_id']])->find()['num']; //认购数量
                $value['pay_num'] = Db::name("pay")->field("ifnull(round(sum(money), 4), '0.0000') as money")->where(['member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id']])->find()['money']; //管理员充值
                $award_money = Db::name("currency_user_num_award")->field("sum(num_award) as money")->where(['member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id']])->find()['money']; //邀请奖励
                $value['sum'] = $value['num'] + $value['forzen_num'] + $value['num_award'] + $value['lock_num'] + $value['release_lock'];  //总量
//            if ($value['currency_id']==29){
//                    //挖矿
//                    $wa_num = M("mining_bonus")->field("sum(num) as wa_num")->where(['member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id']])->find()['wa_num']; //邀请奖励
//                    //买币
//                    $value['must_buy_num'] = $trade_model->field("ifnull(round(sum(money), 6), '0.0000') as must_buy_num")->where(['member_id' => $user_info['member_id'], 'currency_trade_id' => $value['currency_id'], 'type' => 'buy'])->find()['must_buy_num'];
//                    $value['must_sell_num'] = $trade_model->field("ifnull(round(sum(money), 6), '0.0000') as must_sell_num")->where(['member_id' => $user_info['member_id'], 'currency_trade_id' => $value['currency_id'], 'type' => 'sell'])->find()['must_sell_num'];
//                    //买卖手续费
//                    $value['must_fee'] = $trade_model->field("ifnull(round(sum(fee), 6), '0.0000') as must_fee")->where(['member_id' => $user_info['member_id'], 'currency_trade_id' => $value['currency_id']])->find()['must_fee'];
//
//                    $value['balance'] = ($value['chongbi_num'] + $value['buy_num']) - ($value['sell_num'] + $value['tibi_num']) - ($value['num'] + $value['forzen_num'] + $value['lock_num']) + $value['issue_num'] + $value['pay_num'] + $award_money - $value['must_buy_num'] - $value['must_fee']+$wa_num+$value['must_sell_num']; //余额 = （充积分数量 + 购买量） - （卖出量  +  提积分数量 ） - （持有数量 + 冻结数量 +锁仓） + 认购数量 + 管理员充值 + 邀请奖励 +转换母币 -买卖手续费+挖矿分享
//                    //挖矿
//                    $value['wakuang'] = M('mining_bonus')->field("ifnull(sum(round(num, 4)), '0.0000') as wakuang")->where(['member_id' => $user_info['member_id'], 'column' => 1, 'currency_id' => '29'])->find()['wakuang'];
//                    //分红
//                    $value['fenhong'] = M('mining_bonus')->field("ifnull(sum(round(num, 4)), '0.0000') as fenhong")->where(['member_id' => $user_info['member_id'], 'column' => 1, 'currency_id' => '29'])->find()['fenhong'];
//
//
//
//                }else {

                $value['balance'] = ($value['chongbi_num'] + $value['hzchongbi_num'] + $value['buy_num'] + $value['pay_num'] + $award_money) - ($value['sell_num'] + $value['tibi_num'] + $value['hztibi_num'] + $value['num'] + $value['forzen_num']); //余额 = （充积分数量 + 购买量） - （卖出量  +  提积分数量 ） - （持有数量 + 冻结数量 +锁仓） + 认购数量 + 管理员充值 + 邀请奖励
//                    $value['wakuang'] = '0.0000';
//                    $value['fenhong'] = '0.0000';
//                }

                // if (!($value['num'] + $value['tibi_num'] + $value['chongbi_num'] + $value['forzen_num'] + $value['num_award'] + $value['sum_award'] + $value['totalcharging'] + $value['buy_num'] + $value['sell_num']) > 0) {
                //     unset($user_currency[$key]);
                // }

//                $value['issue_num'] = number_format($value['issue_num'], 4, '.', '');
                $value['balance'] = number_format($value['balance'], 4, '.', ''); //余额
            }
        }

        //实际充值钱
        $user_info['totalcount'] = Db::name('pay')->field("ifnull(sum(round(count, 4)), '0.0000') as totalcount")->where(['member_id' => $user_info['member_id'], 'status' => 1, 'currency_id' => '0'])->find()['totalcount'];
        //管理员实际充值钱
        $user_info['total_adminmoney'] = Db::name('pay')->field("ifnull(sum(round(money, 4)), '0.0000') as total_adminmoney")->where(['member_id' => $user_info['member_id'], 'status' => 1, 'type' => 3, 'currency_id' => '0'])->find()['total_adminmoney'];
        //提现钱
//        $user_info['totalmoney'] = Db::name('bank')->alias('bank')->field("ifnull(sum(round(withdraw.money, 4)), '0.0000') as totalmoney")->join("yang_withdraw as withdraw ON withdraw.bank_id = bank.id and withdraw.uid = bank.uid")->join("yang_areas as b ON b.area_id = bank.address")->join("yang_areas as a ON a.area_id = b.parent_id")->where(['bank.uid' => $user_info['member_id'], 'withdraw.status' => ['in', [2, 4]]])->find()['totalmoney'];
        $user_info['totalmoney'] = 0;
        //余额
        $user_info['fifmoney'] = number_format($user_info['totalcount'] - $user_info['totalmoney'], 4);

        $result = [
            'user_info' => $user_info,
            'user_currency' => $user_currency,
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 充值记录
     */
    public function user_pay_log()
    {
        $user_id = I('post.user_id');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $pay_model = Db::name('pay');
        $fields = "pay.pay_id,pay.member_name,pay.member_id,pay.account,pay.money,pay.count,(case pay.status when 0 then '请付款' when 1 then '充值成功' 
        when 2 then '充值失败' when 3 then '已失效' else '暂无' end) status,pay.currency_id,(case when pay.currency_id > 0 then '充积分' else '充值' end)
         currency_type,(case pay.add_time when 0 then '-' else from_unixtime(pay.add_time, '%Y-%m-%d %H:%i:%s') end) add_time,pay.due_bank,pay.batch,pay.capital,
         pay.commit_name,(case pay.commit_time when 0 then '-' else from_unixtime(pay.commit_time, '%Y-%m-%d %H:%i:%s') end) commit_time,pay.audit_name,
         (case pay.audit_time when 0 then '-' else from_unixtime(pay.audit_time, '%Y-%m-%d %H:%i:%s') end) audit_time,m.email,m.phone,a.username,pay.message";

        $pay_list = $pay_model->alias('pay')->field($fields)
            ->join(config("database.prefix") . "member m", "m.member_id=pay.member_id", "LEFT")
            ->join(config("database.prefix") . "admin a", "a.admin_id=pay.admin_id", "LEFT")
            ->where(['pay.member_id' => $user_id, 'pay.status' => 1])->order('pay.add_time desc')->select();

        $pay_sum_money = $pay_model->field("ifnull(round(sum(money), 4), '0.0000') as totalczmoney,ifnull(round(sum(count), 4), '0.0000') as totalczcount")->where(['member_id' => $user_id, 'status' => 1, 'currency_id' => '0'])->find();
        $pay_sum_currency = $pay_model->field("ifnull(round(sum(money), 4), '0.0000') as totalcurrency")->where(['member_id' => $user_id, 'status' => 1, 'currency_id' => ['gt', '0']])->find();
        if (!empty($pay_list)) {
            foreach ($pay_list as &$value) {
                $value['currency_type'] = getCurrencynameByCurrency($value['currency_id']);
            }
        }
        $result = [
            'pay_list' => $pay_list,
            'pay_sum' => [
                'totalcurrency' => $pay_sum_currency['totalcurrency'], //充积分合计
                'totalczmoney' => $pay_sum_money['totalczmoney'], //充值合计
                'totalczcount' => $pay_sum_money['totalczcount'], //实际打款合计
            ]
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 交易记录
     */
    public function user_transaction()
    {
        $user_id = I('post.user_id');
        $currency_id = I('post.currency_id', -1, 'trim,intval');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $where = [
            'a.member_id' => $user_id,
        ];

        if ($currency_id >= 0) {
            $where['a.currency_id'] = $currency_id;
        }

        $model = Db::name('trade');
        $field = "a.*,b.currency_name as b_name,d.currency_name as b_trade_name,c.email as email,c.phone,c.member_id as member_id,c.name as name,c.phone as phone,(case a.add_time when 0 then '-' else from_unixtime(a.add_time, '%Y-%m-%d %H:%i:%s') end) add_time";

        $trade_list = Db::name('Trade')
            ->alias('a')
            ->field($field)
            ->join(config("database.prefix") . "currency b", "b.currency_id=a.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency d", "d.currency_id=a.currency_trade_id", "LEFT")
            ->join(config("database.prefix") . "member c", "c.member_id=a.other_member_id", "LEFT")
            ->where($where)
            ->order("a.add_time desc")
            ->select();

        if (!empty($trade_list)) {
            foreach ($trade_list as &$value) {
                $value['currency_name'] = intval($value['currency_id']) === 0 ? "人民币" :
                    \app\common\model\Currency::where(['currency_id' => $value['currency_id']])->value('currency_name', '');
                $value['type_name'] = getOrdersType($value['type']);
                unset($value['currency_id']);
            }
        }

        $trade_buy_count = $model->alias('a')->field("round(ifnull(sum(a.num), '0.0000'), 4) as buynum,round(ifnull(sum(a.money), '0.0000'), 4) as buymoney")
            ->join(config("database.prefix") . "currency c", "c.currency_id=a.currency_id", "LEFT")
            ->where($where)->where(['a.type' => 'buy'])->find();
        $trade_sell_count = $model->alias('a')->field("round(ifnull(sum(a.num), '0.0000'), 4) as sellnum,round(ifnull(sum(a.money), '0.0000'), 4) as sellmoney")
            ->join(config("database.prefix") . "currency c", "c.currency_id=a.currency_id", "LEFT")
            ->where($where)->where(['a.type' => 'sell'])->find();

        $result = [
            'currency_list' => $this->user_currency_list(),
            'trade_list' => $trade_list,
            'trade_count' => [
                'buynum' => $trade_buy_count['buynum'],
                'buymoney' => $trade_buy_count['buymoney'],
                'sellnum' => $trade_sell_count['sellnum'],
                'sellmoney' => $trade_sell_count['sellmoney'],
            ],
            'currency_id' => $currency_id,
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 提积分记录
     */
    public function user_turn_out_currency()
    {
        $user_id = I('post.user_id');
        $currency_id = I('post.currency_id', -1, 'trim,intval');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $where = [
            'tibi.from_member_id' => $user_id,
            'tibi.status' => ['in', [0, 1]],
        ];

        if ($currency_id >= 0) {
            $where['tibi.currency_id'] = $currency_id;
        }
        $where['tibi.transfer_type'] = "1";
        $model = Db::name('tibi');
        $fields = "tibi.id,(case tibi.status when 0 then '提积分中' when 1 then '提积分成功' when 2 then '充值中' when 3 then '充值成功' end) status,tibi.to_url,round(tibi.num, 4) num,round(tibi.actual, 4) `actual`,tibi.currency_id,(case tibi.add_time when 0 then '-' else from_unixtime(tibi.add_time, '%Y-%m-%d %H:%i:%s') end) as add_time,m.email,tibi.message1,tibi.message2,tibi.ti_id";

        $tibi_list = $model->alias('tibi')->field($fields)
            ->join(config("database.prefix") . "member m", "m.member_id=tibi.from_member_id", "LEFT")
            ->where($where)->order("tibi.add_time desc")->select();

        if (!empty($tibi_list)) {
            foreach ($tibi_list as &$value) {
                $value['currency_name'] = intval($value['currency_id']) === 0 ? "人民币" :
                    \app\common\model\Currency::where(['currency_id' => $value['currency_id']])->value('currency_name', '');
                unset($value['currency_id']);
            }
        }

        $tibi_count = $model->alias('tibi')->field("round(ifnull(sum(tibi.actual), '0.0000'), 4) as totalcurrency")
            ->join(config("database.prefix") . "currency c", "c.currency_id=tibi.currency_id", "LEFT")
            ->where($where)->find();

        $result = [
            'currency_list' => $this->user_currency_list(),
            'tibi_list' => $tibi_list,
            'tibi_count' => [
                'totalcurrency' => $tibi_count['totalcurrency'],
            ],
            'currency_id' => $currency_id,
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 内部互转
     * @throws Exception
     * Created by Red.
     * Date: 2019/1/15 18:13
     */
    public function mutualTransfer()
    {
        $member_id = I('user_id');
        $currency_id = I('currency_id');
        $where = null;
        if (!empty($currency_id) && $currency_id > 0) {
            $where['t.currency_id'] = $currency_id;
        }
        $where['t.b_type'] = 0;
        $where['t.transfer_type'] = "2";//内部互转类型
        if (!empty($member_id)) {
            $where["t.from_member_id|t.to_member_id"] = $member_id;
//            $where['_string'] = ' (t.from_member_id =' . $member_id . ') OR ( t.to_member_id =' . $member_id . ')';
        }

        $field = "t.*,m.email,m.name,m.phone,c.currency_name,
        c.currency_type,m.remarks,tm.name as tname,tm.phone as tphone, tm.email as temail";

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = Db::name("Tibi")->alias("t")->field($field)->where($where)
            ->join("member m", "m.member_id=t.from_member_id", "LEFT")
            ->join("member tm", "tm.member_id=t.to_member_id", "LEFT")
            ->join("currency c", "c.currency_id=t.currency_id", "LEFT")
            ->order("add_time desc")->select();
        if (!empty($list)) {
            foreach ($list as &$value) {
                $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                if ($value['from_member_id'] == $member_id) {
                    $value['num'] = -$value['num'];
                    $value['actual'] = -$value['actual'];
                }
            }
        }
        $result = ['currency_list' => $this->user_currency_list(), 'list' => $list, 'currency_id' => $currency_id];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);

    }

    //查看OTC广告交易记录
    public function orders_trade_log()
    {
        $membe_id = I('user_id');
        $currency_id = I('post.currency_id');
        $where['a.member_id'] = $membe_id;
        if ($currency_id > 0) {
            $where['a.currency_id'] = $currency_id;
        }
        $where['a.status'] = array('not in', [4]);
        //获取挂单记录
        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $list = Db::name('Trade_otc')
            ->alias('a')
            ->field($field)
            ->join("currency b", "b.currency_id=a.currency_id", "LEFT")
            ->join("member c", "c.member_id=a.member_id", "LEFT")
            ->where($where)
            ->order("a.trade_id desc")
            ->select();
        if ($list) {
            foreach ($list as $key => $vo) {
                if ($vo['type'] == "buy") {
                    $list[$key]['change_num'] = $vo['num'] - $vo['fee'];
                } elseif ($vo['type'] == "sell") {
                    $list[$key]['change_num'] = -($vo['num'] + $vo['fee']);
                }
                $list[$key]['type_name'] = getOrdersType($vo['type']);
                $list[$key]['add_time'] = date('Y-m-d H:i:s', $vo['add_time']);
                $list[$key]['buy_payment'] = '';
                $list[$key]['sell_payment'] = '';
                $list[$key]['payment_type'] = '';
                //获取卖家银行卡信息
                if (!empty($vo['money_type'])) {
                    $payment = explode(':', $vo['money_type']);
                    if ($vo['type'] == 'buy') {
                        if ($payment[0] == 'bank') {
                            $model = Db::name('member_bank');
                            $re = $model->field('truename,bankname,bankadd,bankcard')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '银行卡';
                            $list[$key]['sell_payment'] = $re['bankname'] . $re['bankadd'] . $re['bankcard'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['member_id'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment['bankname'] . $buy_payment['bankadd'] . $buy_payment['bankcard'];
                        } else if ($payment[0] == 'wechat') {
                            $model = Db::name('member_wechat');
                            $re = $model->field('truename,wechat')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '微信';
                            $list[$key]['sell_payment'] = $re['wechat'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['member_id'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment['wechat'];
                        } else {
                            $model = Db::name('member_alipay');
                            $re = $model->field('truename,alipay')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '支付宝';
                            $list[$key]['sell_payment'] = $re['alipay'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['member_id'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment['alipay'];
                        }

                    } else {
                        if ($payment[0] == 'bank') {
                            $model = Db::name('member_bank');
                            $re = $model->field('truename,bankname,bankadd,bankcard')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '银行卡';
                            $list[$key]['sell_payment'] = $re['bankname'] . $re['bankadd'] . $re['bankcard'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['other_member'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment['bankname'] . $buy_payment['bankadd'] . $buy_payment['bankcard'];
                        } else if ($payment[0] == 'wechat') {
                            $model = Db::name('member_wechat');
                            $re = $model->field('truename,wechat')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '微信';
                            $list[$key]['sell_payment'] = $re['wechat'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['other_member'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment['wechat'];
                        } else {
                            $model = Db::name('member_alipay');
                            $re = $model->field('truename,alipay')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '支付宝';
                            $list[$key]['sell_payment'] = $re['alipay'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['other_member'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment ? $buy_payment['alipay'] : '';
                        }

                    }
                }

                if ($vo['status'] == 0) {
                    $list[$key]['status'] = '未付款';
                } else if ($vo['status'] == 1) {
                    $list[$key]['status'] = '待放行';
                } else if ($vo['status'] == 2) {
                    $list[$key]['status'] = '申诉中';
                } else if ($vo['status'] == 3) {
                    $list[$key]['status'] = '已完成';
                } else if ($vo['status'] == 4) {
                    $list[$key]['status'] = '已取消';
                }

            }
        }

        //交易记录
        $field1 = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $ordersInfo = Db::name('Orders_otc')
            ->alias('a')
            ->field($field1)
            ->join("currency b", "b.currency_id=a.currency_id", "LEFT")
            ->join("member c", "c.member_id=a.member_id", "LEFT")
            ->where($where)
            ->order("a.orders_id desc")
            ->select();
        if ($ordersInfo) {
            foreach ($ordersInfo as $key => $vo) {
                $ordersInfo[$key]['add_time'] = date('Y-m-d H:i:s', $vo['add_time']);
                $ordersInfo[$key]['type_name'] = getOrdersType($vo['type']);
                if ($vo['status'] == 0) {
                    $ordersInfo[$key]['status'] = '未成交';
                } else if ($vo['status'] == 1) {
                    $ordersInfo[$key]['status'] = '部分成交';
                } else if ($vo['status'] == 2) {
                    $ordersInfo[$key]['status'] = '已成交';
                } else if ($vo['status'] == 3) {
                    $ordersInfo[$key]['status'] = '已撤销';
                }
            }
        }
        $result = ['order_list' => $ordersInfo, 'trade_list' => $list, 'currency_list' => $this->user_currency_list(), 'currency_id' => $currency_id];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);

    }

    /**
     * 用户帐本记录
     * Created by Red.
     * Date: 2019/1/16 19:48
     */
    public function accountbook()
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        $type_list = [
            'flop' => ['name' => '方舟', 'ids' => [1000, 1001, 1002, 1003, 1005, 1006, 1007, 1008, 1009]],
            'hongbao' => ['name' => '锦鲤红包', 'ids' => [950, 951, 952]],
            'contract' => ['name' => '合约', 'ids' => [1100, 1101, 1102, 1103, 1104, 1105, 1106, 1107, 1108]],
            'air' => ['name' => '云梯', 'ids' => [1400, 1401, 1402, 1403, 1404, 1405, 1406, 1407]],
        ];

        $member_id = I('user_id');
        $currency_id = I('currency_id');
        $type_get = input('type', '');
        if ($type_get && isset($type_list[$type_get])) {
            $where['type'] = ['in', $type_list[$type_get]['ids']];
        }

        $page = I("page", 1);
        $rows = I("rows", 10);
        if (!empty($currency_id) && $currency_id > 0) {
            $where['currency_id'] = $currency_id;
        }
        $where['member_id'] = $member_id;
        $count = Db::name("accountbook")->where($where)->count("id");
        // 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name("accountbook")->where($where)->order("id desc")/*->page($page,$rows)*/ ->select();
        if (!$list) $list = [];
        $list2 = [];//Db::name("accountbook_admin")->where($where)->order("id desc")/*->page($page,$rows)*/ ->select();
        if (!$list2) $list2 = [];

        foreach ($list2 as $item2) {
            $list[] = $item2;
        }
        $list2 = [];

        if (!empty($list)) {
            $currencyList = $this->user_currency_list();
            $currencyList = array_column($currencyList, null, 'currency_id');
            $accounType = Db::name("accountbook_type")->field("id,name_tc")->select();
            $typeList = array_column($accounType, null, "id");
            foreach ($list as &$value) {
                $type = $value['type'];
                if ($value['type'] == 24) {
                    $value['ad_remark'] .= $value['ad_remark'] . "<a target='_blank' href='" . U('BossPlan/bouns_detail', ['today' => date('Y-m-d', $value['add_time']), 'member_id' => $value['member_id']]) . "'>动态分红详情</a>";
                }
                $value['type'] = isset($typeList[$value['type']]) ? $typeList[$value['type']]['name_tc'] : '';
                $value['currency_name'] = isset($currencyList[$value['currency_id']]) ? $currencyList[$value['currency_id']]['currency_name'] : '';
                $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                $value['number'] = $value['number_type'] == 1 ? $value['number'] : -$value['number'];
                $value['after'] = bcadd($value['number'], $value['current'], 8);
                $value['change'] = $value['number_type'] == 1 ? "收入" : "支出";
                $value['from_member_id'] = "";
                $value['from_phone'] = "";
                $value['from_email'] = "";
                $value['toMemberId'] = "";
                $value['to_phone'] = "";
                $value['to_email'] = "";
                $value['currency_pair'] = "";
                if ($value['third_id'] > 0 || is_numeric($value['content']) || $value['to_member_id'] > 0) {
                    switch ($type) {
                        case 5:
                            //充币类型
                            $tibi = Db::name("tibi")->where(['id' => $value['third_id']])->field("to_member_id,from_member_id,transfer_type")->find();
                            if ($tibi['transfer_type'] == "2") {
//                                $value['type'] = "平台内" . $value['type'];
                                $value['type'] = "内部" . $value['type'];
                            }
                            if (!empty($tibi)) {
                                if ($tibi['to_member_id'] > 0) {
                                    $toMember = $this->getMemberInfo($tibi['to_member_id']);
                                    $value['toMemberId'] = $tibi['to_member_id'];
                                    $value['to_phone'] = $toMember['phone'];
                                    $value['to_email'] = $toMember['email'];
                                }
                                if ($tibi['from_member_id'] > 0) {
                                    $fromMember = $this->getMemberInfo($tibi['from_member_id']);
                                    $value['from_member_id'] = $tibi['from_member_id'];
                                    $value['from_phone'] = $fromMember['phone'];
                                    $value['from_email'] = $fromMember['email'];
                                }
                            }
                            break;
                        case 6:
                            //提币类型
                            $tibi = Db::name("tibi")->where(['id' => $value['third_id']])->field("to_member_id,from_member_id,transfer_type")->find();
                            if ($tibi['transfer_type'] == "2") {
//                                $value['type'] = "平台内" . $value['type'];
                                $value['type'] = "内部" . $value['type'];
                            }
                            if (!empty($tibi)) {
                                if ($tibi['to_member_id'] > 0) {
                                    $toMember = $this->getMemberInfo($tibi['to_member_id']);
                                    $value['toMemberId'] = $tibi['to_member_id'];
                                    $value['to_phone'] = $toMember['phone'];
                                    $value['to_email'] = $toMember['email'];
                                }
                                if ($tibi['from_member_id'] > 0) {
                                    $fromMember = $this->getMemberInfo($tibi['from_member_id']);
                                    $value['from_member_id'] = $tibi['from_member_id'];
                                    $value['from_phone'] = $fromMember['phone'];
                                    $value['from_email'] = $fromMember['email'];
                                }
                            }
                            break;
                        case 9:
                            //otc交易类型
                            $tradeOtc = Db::name("trade_otc")->where(['trade_id' => $value['third_id']])->field("member_id,other_member")->find();
                            if (!empty($tradeOtc)) {
                                if ($tradeOtc['other_member'] > 0) {
                                    $toMember = $this->getMemberInfo($tradeOtc['other_member']);
                                    $value['toMemberId'] = $tradeOtc['other_member'];
                                    $value['to_phone'] = $toMember['phone'];
                                    $value['to_email'] = $toMember['email'];
                                }
                                if ($tradeOtc['member_id'] > 0) {
                                    $fromMember = $this->getMemberInfo($tradeOtc['member_id']);
                                    $value['from_member_id'] = $tradeOtc['member_id'];
                                    $value['from_phone'] = $fromMember['phone'];
                                    $value['from_email'] = $fromMember['email'];
                                }
                            }

                            break;
                        case 11:
                            //币币交易类型
                            if ($value['to_member_id'] > 0) {
                                $toMember = $this->getMemberInfo($value['to_member_id']);
                                $value['toMemberId'] = $value['to_member_id'];
                                $value['to_phone'] = $toMember['phone'];
                                $value['to_email'] = $toMember['email'];
                            }
                            if ($value['member_id'] > 0) {
                                $fromMember = $this->getMemberInfo($value['member_id']);
                                $value['from_member_id'] = $value['member_id'];
                                $value['from_phone'] = $fromMember['phone'];
                                $value['from_email'] = $fromMember['email'];
                            }
                            if ($value['to_currency_id'] > 0) {
                                $value['currency_pair'] = isset($currencyList[$value['to_currency_id']]) ? $value['currency_name'] . "/" . $currencyList[$value['to_currency_id']]['currency_name'] : '';
                            }
                            break;
                        case 18:
                            //内转帐
                            if ($value['to_member_id'] > 0 || is_numeric($value['content'])) {
                                $uid = $value['to_member_id'] > 0 ? $value['to_member_id'] : $value['content'];
                                $toMember = $this->getMemberInfo($uid);
                                $value['toMemberId'] = $uid;
                                $value['to_phone'] = $toMember['phone'];
                                $value['to_email'] = $toMember['email'];
                            }
                            if ($value['member_id'] > 0) {
                                $fromMember = $this->getMemberInfo($value['member_id']);
                                $value['from_member_id'] = $value['member_id'];
                                $value['from_phone'] = $fromMember['phone'];
                                $value['from_email'] = $fromMember['email'];
                            }
                            break;
                    }
                }
            }
        }
        $result = ['currency_list' => $this->user_currency_list(), 'list' => $list, 'list2' => $list2, 'currency_id' => $currency_id, 'type' => $type_get, 'type_list' => $type_list];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    // 实名认证审核
    public function member_verify(Request $request)
    {
        if ($request->isAjax()) {
            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');

            $where = [];
            $member_id = $request->param('member_id', 0, 'trim');
            if (!empty($member_id)) $where['vf.member_id'] = $member_id;
            $where['vf.verify_state'] = 2;

            $field = "m.member_id,m.email,m.phone,vf.name,vf.idcard,vf.pic1,vf.pic2,vf.pic3,vf.addtime,
            vf.passport_img,vf.license_img,vf.verify_state,vf.country_code,vf.sex,vf.nation_id,vf.cardtype";
            $list = VerifyFile::where($where)
                ->alias("vf")
                ->field($field)
                ->join("member m", "m.member_id=vf.member_id", 'left')
                ->order(['addtime' => 'desc'])->page($page, $limit)->select();
            foreach ($list as &$item) {
                if ($item['email']) {
                    $item['phone'] = $item['email'];
                }

                $nation['nation_name'] = '无';
                $country['cn_name'] = '无';
                if (!empty($item['nation_id'])) {
                    $nation = Db::name('Nation')->field('nation_name')->where(['nation_id' => $item['nation_id']])->find();
                }
                if (!empty($item['country_code'])) {
                    $country = Db::name('CountriesCode')->field('tc_name,cn_name')->where(['phone_code' => $item['country_code']])->find();
                }

                $item['addtime'] = date('Y-m-d H:i:s',$item['addtime']);
                $item['nation_id'] = isset($nation['nation_name']) ? $nation['nation_name'] : "";
                $item['country_code'] = isset($country['cn_name']) ? $country['cn_name'] : "";
                $item['sex'] = str_replace(array(0, 1, 2), array('无', '男', '女'), $item['sex']);
                $item['cardtype'] = str_replace(array(0, 1, 2, 5), array('无', '身份证', '护照', '驾照'), $item['cardtype']);
                $item['verify_state'] = str_replace(array(0, 1, 2), array('未通过', '已通过', '审核中'), $item['verify_state']);

                $item['pic1'] = '<img src="' . $item['pic1'] . '" />';
                $item['pic2'] = '<img src="' . $item['pic2'] . '" />';
                $item['pic3'] = '<img src="' . $item['pic3'] . '" />';
            }

            $count = VerifyFile::where($where)
                ->alias("vf")
                ->join("member m", "m.member_id=vf.member_id", 'left')
                ->count();
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        return $this->fetch();
    }

    // 实名认证审核 - 审核通过/拒绝
    public function member_verify_adopt(Request $request)
    {
        $member_id = $request->param('member_id');
        $verify_state = $request->param('type', 1, 'intval');
        $info = VerifyFile::where(['member_id' => $member_id])->select();
        if (empty($info)) $this->ajaxReturn(['Code' => 0, 'Msg' => "操作失败"]);
        $r = $this->verify($verify_state, $info);
        $this->ajaxReturn(['Code' => $r['code'], 'Msg' => $r['message']]);
    }

    private function verify($verify_state, $info = array())
    {
        $r['code'] = 0;
        $r['message'] = "审核失败";
        if (!in_array($verify_state, [0, 1])) {
            $r['code'] = 0;
            $r['message'] = "参数错误";
            return $r;
        }
        try {
            Db::startTrans();
            foreach ($info as $key => $val) {
                $update_data1 = Db::name('verify_file')->where(['member_id' => $val['member_id']])->update(['verify_state' => $verify_state,'admin_id'=>$this->admin['id']]);
                if (!$update_data1) {
                    throw new Exception('审核失败');
                }
                if ($verify_state == 1) {
                    if ($val['sex'] == 2) {
                        $val['sex'] = 0;
                    }
                    $data = [
                        'name' => $val['name'],
                        'cardtype' => $val['cardtype'],
                        'idcard' => $val['idcard'],
                        'verify_time' => time(),
                        'gender' => $val['sex'],
                        'nation_id' => $val['nation_id'],
                        'country_id' => $val['country_id']
                    ];
                    $update_data2 = Db::name('member')->where(['member_id' => $val['member_id']])->update($data);
                    if (!$update_data2) {
                        throw new Exception('审核失败');
                    }

                    // 认证成功赠送代金券
                    $config = model('config')->byField();
                    if(!empty($config['is_gift_voucher'])) {
                        $voucher_config = Db::name('voucher_config')->where(['id'=>$config['voucher_register_id']])->find();
                        if($config['voucher_num'] > 0) {
                            $data = [];
                            for($i = 0; $i < $config['voucher_num']; $i++) {
                                $data[$i] = [
                                    'member_id' => $val['member_id'],
                                    'voucher_id' => $voucher_config['id'],
                                    'cny' => $voucher_config['cny'],
                                    'expire_time' => strtotime("+{$voucher_config['validity']} day"),
                                ];
                            }
                            $result = Db::name('voucher_member')->insertAll($data);
                            if(!$result) throw new Exception(lang('lan_network_busy_try_again'));
                        }
                    }
                }
            }
            Db::commit();
            $r['message'] = "审核成功";
            $r['code'] = 1;
        } catch (Exception $e) {
            Db::rollback();
            $r['message'] = $e->getMessage();
        }

        return $r;

    }
}
