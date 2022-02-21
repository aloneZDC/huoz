<?php


namespace app\admin\controller;


use app\common\model\AccountBook;
use app\common\model\MoneyInterestConfig;
use think\Db;
use think\Exception;
use think\Request;

class MoneyInterest extends Admin
{
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }


    public function index(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        $currency_id = input('currency_id');

        $phone = input('phone');
        $daochu = input('daochu');

        if (!empty($member_id)) {
            $where['c.member_id'] = $member_id;
        }
        if (!empty($currency_id)) $where['a.currency_id'] = $currency_id;
        if (!empty($phone)) {
            if (checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        $status = input('status', '');
        if ($status != '') {
            $status = intval($status);
            $where['a.status'] = $status;
        }

        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        if ($daochu == 2) {
            $list = Db::name('money_interest')
                ->alias('a')
                ->field($field)
                ->join('yang_currency b', 'a.currency_id = b.currency_id')
                ->join('yang_member c', 'a.member_id = c.member_id ')
                ->where($where)
                ->order("a.id", "desc")
                ->select();
            $statusList = ['0' => '生息中', '1' => '已生息', '2' => '已撤销'];
            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value['phone'] = !empty($value['phone']) ? $value['phone'] : $value['email'];
                    $value['rate'] = $value['rate'] . "%";
                    $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                    $value['end_time'] = date("Y-m-d H:i:s", $value['end_time']);
                    $value['status'] = $statusList[$value['status']];
                }
            }
            $xlsCell = array(
                array('id', '列表ID'),
                array('member_id', '会员ID'),
                array('name', '姓名'),
                array('phone', '账户'),
                array('currency_name', '币种'),
                array('months', '月份'),
                array('num', '数量'),
                array('rate', '年收益率'),
                array('day_num', '每日生息量'),
                array('add_time', '添加时间'),
                array('end_time', '到期时间'),
                array('status', '状态')

            );
            $this->exportExcel("持币生息记录", $xlsCell, $list);
            die();
        }
        /*$count = Db::name('money_interest')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->count();// 查询满足要求的总记录数
        $Page = new Page($count, 25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('currency_id' => $currency_id, 'phone' => $phone, 'member_id' => $member_id, 'status' => $status));

        $show = $Page->show();// 分页显示输出*/
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = Db::name('money_interest')
            ->alias('a')
            ->field($field)
            ->join('yang_currency b', 'a.currency_id = b.currency_id')
            ->join('yang_member c', 'a.member_id = c.member_id ')
            ->where($where)
            ->order("a.id", "desc")
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        //积分类型
        $currency = Db::name('Currency')->field('currency_name,currency_id')->select();
        /* $this->assign('currency', $currency);
         $this->assign('list', $list);

         $this->assign('member_id', $member_id);
         $this->assign('currency_id', $currency_id);
         $this->assign('phone', $phone);
         $this->assign('status', $status);
         $this->assign('page', $show);// 赋值分页输出*/
        return $this->fetch('', [
            'currency' => $currency,
            'currency_id' => $currency_id,
            'list' => $list,
            'member_id' => $member_id,
            'phone' => $phone,
            'status' => $status,
            'page' => $page,
            'empty' => '暂无数据'
        ]);
    }


    //生息记录
    public function interest()
    {
        $member_id = I('member_id');
        $currency_id = I('currency_id');

        $phone = I('phone');
        $interest_id = I('interest_id');
        if (!empty($interest_id)) $where['a.interest_id'] = $interest_id;
        if (!empty($member_id)) $where['c.member_id'] = $member_id;
        if (!empty($currency_id)) $where['a.currency_id'] = $currency_id;
        if (!empty($phone)) {
            if (checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        $status = I('status', '');
        if ($status != '') {
            $status = intval($status);
            $where['d.status'] = $status;
        }

        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $count = M('money_interest_daily')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->join('LEFT JOIN yang_money_interest as d on a.interest_id = d.id ')
            ->where($where)
            ->count();// 查询满足要求的总记录数
        $Page = new Page($count, 25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('currency_id' => $currency_id, 'phone' => $phone, 'member_id' => $member_id, 'status' => $status));

        $show = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('money_interest_daily')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->join('LEFT JOIN yang_money_interest as d on a.interest_id = d.id ')
            ->where($where)
            ->order(" a.id desc ")
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        //积分类型
        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency', $currency);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        $this->display();
    }

    //分红记录
    public function dividend()
    {
        $member_id = I('member_id');
        $currency_id = I('currency_id');

        $phone = I('phone');
        $interest_id = I('interest_id');
        if (!empty($interest_id)) $where['a.interest_id'] = $interest_id;
        if (!empty($member_id)) $where['c.member_id'] = $member_id;
        if (!empty($currency_id)) $where['a.currency_id'] = $currency_id;
        if (!empty($phone)) {
            if (checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        $status = I('status', '');
        if ($status != '') {
            $status = intval($status);
            $where['a.status'] = $status;
        }

        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $count = M('money_interest_dividend')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->count();// 查询满足要求的总记录数
        $Page = new Page($count, 25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('currency_id' => $currency_id, 'phone' => $phone, 'member_id' => $member_id, 'status' => $status));

        $show = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('money_interest_dividend')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->order(" a.id desc ")
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        //积分类型
        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency', $currency);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        $this->display();
    }

    //分红记录
    public function dividend_day()
    {
        /*$count = M('money_interest_dividend_day')->count();// 查询满足要求的总记录数
        $Page = new Page($count, 25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        setPageParameter($Page, array());

        $show = $Page->show();// 分页显示输出
        $list = M('money_interest_dividend_day')->order("id desc ")->limit($Page->firstRow . ',' . $Page->listRows)->select();*/
        $list = Db::name('money_interest_dividend_day')->order('id desc')->paginate();
        $page = $list->render();
        $currency = Db::name('Currency')->field('currency_name,currency_id')->select();
        $currency_list = [];

        foreach ($currency as $key => $value) {
            $currency_list[$value['currency_id']] = $value['currency_name'];
        }

        foreach ($list as $key => $value) {
            $price_list = json_decode($value['price_list'], true);
            $value['price_list'] = '';
            foreach ($price_list as $currency_id => $price) {
                if (isset($currency_list[$currency_id])) {
                    $value['price_list'] .= $currency_list[$currency_id] . ' = ' . $price . ' KOK<br>';
                }
            }
            $list[$key] = $value;
        }

        $kok_total = 0;
        $kok_money = keepPoint($this->config['kok_interest_dividend'], 6);
        $price_ll = '';
        //获取持币生息中的数量,计算分红
        $total_interest = Db::name('money_interest')->field('sum(num) as sum_num,currency_id')->where('status=0')->group('currency_id')->select();
        if ($total_interest) {
            foreach ($total_interest as $value) {
                //获取对应的KOK价格
                $price = $this->getBk($value['currency_id']);
                if (!$price) $price = 0;

                $kok_total += keepPoint($price * $value['sum_num'], 6);
                $price_ll .= $currency_list[$value['currency_id']] . ' = ' . $price . " KOK<br>";
            }
            //每份可得的分红
            if ($kok_total > 0) {
                $one_dividend = keepPoint($kok_money / $kok_total, 6);
            } else {
                $one_dividend = 0;
            }
        }
        if (!isset($one_dividend)) {
            $one_dividend = 0;
        }
        $dividend_day = ['kok_total' => $kok_total, 'kok_money' => $kok_money, 'one_dividend' => $one_dividend, 'price_ll' => $price_ll];
        return $this->fetch('', compact('list', 'page', 'dividend_day'));
        /*$this->assign('dividend_day', ['kok_total' => $kok_total, 'kok_money' => $kok_money, 'one_dividend' => $one_dividend, 'price_ll' => $price_ll]);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        $this->display();*/
    }

    //配置列表
    public function setting(Request $request)
    {
        $where = [];

        $currency_id = intval(input('currency_id'));
        if (!empty($currency_id)) $where['a.currency_id'] = $currency_id;

        /*$count      = Db::name('money_interest_config')->alias('a')->where($where)->count();// 查询满足要求的总记录数
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        setPageParameter($Page, array('currency_id'=>$currency_id));*/
        // ->join('yang_currency b', 'a.currency_id = b.currency_id')
        // ->join('yang_member c', 'a.member_id = c.member_id ')
        $list = Db::name('money_interest_config')
            ->field('a.*,b.currency_name')
            ->alias('a')
            ->where($where)
            ->order('a.id', 'desc')
            ->join('yang_currency b', 'a.currency_id=b.currency_id')
            ->paginate(null, false, ['query' => $request->get()]);
        $currency = Db::name('currency')->field('currency_name,currency_id')->select();
        $page = $list->render();
        $types = MoneyInterestConfig::TYPE_ENUM;
        /*$list = M('money_interest_config')->alias('a')->field('a.*,b.currency_name')->where($where)->join('left join __CURRENCY__ b on a.currency_id=b.currency_id')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency', $currency);
        $this->assign('list', $list);
        $show = $Page->show();
        $this->assign('page', $show);
        $this->display();*/
        return $this->fetch('', compact('list', 'currency', 'page', 'types'));
    }

    //添加配置
    public function add_setting(Request $request)
    {
        $id = input('id', 0, 'intval');
        if ($request->isPost()) {
            $model = Db::name('money_interest_config');
            $days = input("post.months", 0, 'intval');
            $type = input("post.type", 0, 'intval');
            $day_rate = input("post.day_rate", 0);
            $rate = input('rate', 0, 'floatval');
            if (empty($type)) {
                $this->error("请选择类型");
            }

            if (MoneyInterestConfig::TYPE_DAY == $type) {
                $days = 0;
                $rate = 0;
                if (empty($day_rate)) {
                    $this->error("请填写日利率");
                }
            } else {
                if ($days <= 0) $this->error('月份不能为空');
                if ($rate <= 0) $this->error('年收益不能为空');
                $day_rate = 0;
            }
            $data = [
                'currency_id' => input('currency_id', 0, 'intval'),
                'months' => $days,
                'min_num' => input('min_num', '', 'floatval'),
                'max_num' => input('max_num', '', 'floatval'),
                'rate' => $rate,
                'add_time' => time(),
                'cn_title' => input("cn_title"),
                'en_title' => input("en_title"),
                'cn_characteristic' => input("cn_characteristic"),
                'en_characteristic' => input("en_characteristic"),
                'cn_details' => input("cn_details"),
                'en_details' => input("en_details"),
                "type" => $type,
                "day_rate" => $day_rate
            ];

            if (strlen($data['cn_title']) <= 0) $this->error('中文标题不能为空');
            if (strlen($data['en_title']) <= 0) $this->error('英文标题不能为空');
            if ($data['type'] <= 0) $this->error('类型不能为空');
            if ($data['min_num'] < 0) $this->error('最低转入不能为空');
            if ($data['max_num'] < 0) $this->error('最高转入不能为空');
            if (strlen($data['cn_characteristic']) <= 0) $this->error('中文产品特点介绍不能为空');
            if (strlen($data['en_characteristic']) <= 0) $this->error('英文产品特点介绍不能为空');
            if ($data['cn_details'] <= 0) $this->error('中文详情文章ID能为空');
            if ($data['en_details'] <= 0) $this->error('英文详情文章ID不能为空');

            if (empty($id)) {
                $log = $model->where(['currency_id' => $data['currency_id'], 'months' => $data['months'], 'type' => $data['type']])->find();
                if ($log) $this->error('该币种-月份-类型已存在');

                $result = $model->insert($data);
            } else {
                $result = $model->where(['id' => $id])->update($data);
            }

            if ($result !== false) {
                $this->success(lang('lan_operation_success'));
            } else {
                $this->error(lang('lan_network_busy_try_again'));
            }
        } else {
            $info = [];
            if (!empty($id)) {
                $info = Db::name('money_interest_config')->where(['id' => $id])->find();
            } else {
                // fix PHP7 warning
                $info = [
                    "id" => null,
                    'currency_id' => null,
                    'months' => null,
                    'min_num' => null,
                    'max_num' => null,
                    'rate' => null,
                    'add_time' => null,
                    'cn_title' => null,
                    'en_title' => null,
                    'cn_characteristic' => null,
                    'en_characteristic' => null,
                    'cn_details' => null,
                    'en_details' => null,
                    'type' => null,
                    'days' => null,
                    'day_rate' => null
                ];
                /*$info = Db::name('money_interest_config')->find();
                foreach ($info as $key => &$val) {
                    $val = null;
                }*/
            }
            $types = MoneyInterestConfig::TYPE_ENUM;
            return $this->fetch('', [
                'list' => $info,
                'types' => $types,
                'currency' => Db::name('currency')->select()
            ]);
        }
    }

    public function del_setting()
    {
        $id = input('id', 0, 'intval');
        if (empty($id)) return successJson(0, lang('lan_Illegal_operation'));
        $model = Db::name('money_interest_config');
        $info = $model->where(['id' => $id])->find();
        if (empty($info)) return successJson(0, lang('lan_Illegal_operation'));

        $result = $model->where(['id' => $id])->delete();
        if (!$result) {
            return successJson(0, lang('lan_network_busy_try_again'));
        } else {
            return successJson(1, lang('lan_operation_success'));
        }
    }

    //根据币种获取对应KOK价格
    private function getBk($currency_id)
    {
        if ($currency_id == 9) {
            return 1;
        } else {
            return Db::name('trade')->where(['currency_id' => $currency_id, 'currency_trade_id' => 9])->order('add_time desc')->value('price');
        }
    }

    /**
     * 取消生息
     * Created by Red.
     * Date: 2019/1/17 16:34
     */
    function cancel()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $id = I("id");
        if (!empty($id)) {
            Db::startTrans();
            try {
                $find = Db::name("money_interest")->where(['id' => $id, 'status' => 0])->find();
                if (!empty($find)) {
                    $update = Db::name("money_interest")->where(['id' => $id])->update(['status' => 2]);
                    if ($update) {
                        //退回可用的帐本记录
                        $accountbookD = new AccountBook();
                        $da['member_id'] = $find['member_id'];
                        $da['currency_id'] = $find['currency_id'];
                        $da['type'] = 23;
                        $da['content'] = "lan_withdrawal_currency";
                        $da['number_type'] = 1;
                        $da['number'] = $find['num'];
                        $da['third_id'] = $find['id'];
                        $accountbook = $accountbookD->addLog($da);
                        if (!$accountbook) {
                            throw new Exception(lang("lan_operation_failure"));
                        }
                        $currencyUser = Db::name("currency_user")->where(['member_id' => $find['member_id'], 'currency_id' => $find['currency_id']])->find();
                        if ($currencyUser) {
                            $currencyUser['num'] += $find['num'];
                            $save = Db::name("currency_user")->where(['member_id' => $find['member_id'], 'currency_id' => $find['currency_id']])->update(['num' => $currencyUser['num']]);
                            if ($save) {
                                $r['code'] = SUCCESS;
                                $r['message'] = "撤消成功";
                            } else {
                                throw new \Exception("保存数据异常");
                            }
                        } else {
                            throw new \Exception("查询数据异常");
                        }
                    }

                } else {
                    $r['message'] = "该记录已不可再操作";
                }
                Db::commit();
            } catch (\Exception $exception) {
                Db::rollback();
                $r['message'] = $exception->getMessage();
            }

        }
        return successJson($r);
    }
}