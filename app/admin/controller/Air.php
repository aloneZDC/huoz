<?php


namespace app\admin\controller;


use app\common\model\AirIncomeLog;
use app\common\model\AirLadderLevel;
use app\common\model\DcLockLog;
use app\common\model\UserAirDiff;
use app\common\model\UserAirJackpot;
use app\common\model\UserAirLevel;
use app\common\model\UserAirRecommend;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Paginator;
use think\paginator\driver\Bootstrap;
use think\Request;

class Air extends Admin
{
    /**
     * @var UserAirLevel
     */
    protected $userAirLevel;

    /**
     * @var AirIncomeLog
     */
    protected $airIncomeLog;

    /**
     * @var AirLadderLevel
     */
    protected $airLadderLevel;

    /**
     * @var UserAirDiff
     */
    protected $userAirDiff;

    /**
     * @var UserAirRecommend
     */
    protected $userAirRecommend;

    /**
     * @var UserAirJackpot
     */
    protected $userAirJackpot;

    /**
     * @var DcLockLog
     */
    protected $dcLockLog;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->userAirLevel = new UserAirLevel();
        $this->airIncomeLog = new AirIncomeLog();
        $this->airLadderLevel = new AirLadderLevel();
        $this->userAirDiff = new UserAirDiff();
        $this->userAirRecommend = new UserAirRecommend();
        $this->userAirJackpot = new UserAirJackpot();
        $this->dcLockLog = new DcLockLog();
    }

    public function user(Request $request)
    {
        $userId = $request->get('user_id');
        $where = [];
        if ($userId) {
            $where['user_id'] = $userId;
        }
        $list = $this->userAirLevel->where($where)->with(['currency', 'user', 'level'])->order('id', 'desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    public function editUser(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();

            $flag = $this->userAirLevel->update($data);
            if (!$flag) {
                return successJson(ERROR1, '系统错误修改失败');
            }
            return successJson(SUCCESS, "修改成功");
        }

        $id = $request->param('id');
        $data = $this->userAirLevel->where('id', $id)->find();
        return $this->fetch(null, ['data' => $data]);
    }

    public function income(Request $request)
    {
        $userId = $request->get('user_id');
        $id = $request->get('id');
        $payUserId = $request->get('pay_user_id');
        $where = [];
        if ($userId) {
            $where['user_id'] = $userId;
        }
        if ($id) {
            $where['id'] = $id;
        }
        if ($payUserId) {
            $where['pay_user_id'] = $payUserId;
        }

        $list = $this->airIncomeLog->with(['giveCurrency', 'currency', 'payUser', 'user'])->where($where)->order('id', 'desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();

        $typeEnum = AirIncomeLog::TYPE_ZH_CN_ENUM;
        $assetsEnum = AirIncomeLog::ASSETS_ZH_CN_MAP;
        return $this->fetch(null, compact('list', 'page', 'count', 'typeEnum', 'assetsEnum'));
    }

    public function levels()
    {
        $list = $this->airLadderLevel->paginate();
        $page = $list->render();
        $count = $list->total();

        $enum = AirLadderLevel::STATUS_ENUM;
        return $this->fetch(null, compact('list', 'page', 'count', 'enum'));
    }

    public function editLevel(Request $request)
    {
        if ($request->isPost()) {
            $flag = $this->airLadderLevel->update($request->post());
            if (!$flag) {
                return successJson(ERROR1, '系统错误修改失败');
            }
            return successJson(SUCCESS, "修改成功");
        }
        $id = $request->param('id');
        $data = $this->airLadderLevel->getLevelById($id);
        return $this->fetch(null, ['data' => $data]);
    }

    /**
     * 级差
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function levelDiff(Request $request)
    {
        $userId = $request->param('user_id', null);
        $incomeId = $request->param('income_id', null);
        $where = [];
        if ($userId) {
            $where['reward_user_id'] = $userId;
        }

        if ($incomeId) {
            $where['income_id'] = $incomeId;
        }
        $list = $this->userAirDiff->with(['rewardUser', 'incomeUser', 'currency', 'level'])->order('id','desc')->where($where)->paginate();
        $page = $list->render();
        $count = $list->total();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 直推
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function recommend(Request $request)
    {
        $userId = $request->param('user_id');
        $incomeId = $request->param('income_id');
        $where = [];
        if ($userId) {
            $where['user_id'] = $userId;
        }

        if ($incomeId) {
            $where['income_id'] = $incomeId;
        }
        $list = $this->userAirRecommend->where($where)->with(['currency', 'user', 'recommendUser'])->order('id','desc')->paginate();
        $page = $list->render();
        $count = $list->total();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 周分红
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function jackpot(Request $request)
    {
        $userId = $request->param('user_id');
        $list = $this->userAirJackpot->where('user_id', $userId)->with(['currency', 'user', 'log'])->paginate();
        $page = $list->render();
        $count = $list->total();
        $enum = UserAirJackpot::INCOME_ENUM;
        return $this->fetch(null, compact('list', 'page', 'count', 'enum'));
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function dncLock(Request $request)
    {
        $userId = $request->get('user_id', null);
        $type = $request->get('type', 'all');
        $where = [];
        if ($userId) {
            $where['user_id'] = $userId;
        }

        if (in_array($type, DcLockLog::ALL_TYPE)) {
            $where['type'] = $type;
        }

        $list = $this->dcLockLog->with(['user', 'currency'])->where($where)->order('id', 'desc')->paginate();
        $page = $list->render();
        $count = $list->total();
        $enum = DcLockLog::TYPE_ENUM;

        return $this->fetch(null, compact('list', 'page', 'count', 'enum'));
    }

    /**
     * @param Request $request
     * @return mixed|void
     * @throws DbException
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws PDOException
     */
    public function config(Request $request)
    {
        if ($request->isPost()) {
            $res = [];
            $post = $request->post();
            foreach ($post as $key => $value) {
                $res[] = Db::name("Config")->where(['key' => $key])->update(['value' => $value]);
            }
            if (in_array(false, $res, true)) {
                return $this->error("部分配置修改失败");
            }
            return $this->success("配置修改成功");
        }
        $list = Db::name("Config")->select();
        foreach ($list as $k => $v) {
            $list[$v['key']] = $v['value'];
        }
        $this->assign('config', $list);
        return $this->fetch();
    }

    //汇总
    public function summary() {
        $online_start_time = strtotime('2020-05-07');

        $page = intval(input('page',1));
        $pageSize = 10;
        $stop_time = todayBeginTimestamp(); //结束时间
        $all_day = ($stop_time - $online_start_time)/86400;

        $real_stop_time = $stop_time - 86400 * $pageSize*($page-1);//开始时间
        $start_time = $real_stop_time - 86400 * $pageSize;//开始时间
        for ($time=$real_stop_time;$time>=$start_time;$time-=86400) {
            $cache_key = 'air_summary_'.$time;
            $data = cache($cache_key);
            if(empty($data)) {
                //总入金
                $income_total = AirIncomeLog::where(['add_time'=>['between',[$time,$time+86399]  ] ])->sum('number');
                $income = AirIncomeLog::where(['add_time'=>['between',[$time,$time+86399]  ] ])->field('id')->select();
                $income_ids = array_column($income,'id');
                //直推奖励
                $recommand_income = UserAirRecommend::where(['income_id'=>['in',$income_ids] ])->sum('award_number');
                //云梯奖励
                $air_income = UserAirDiff::where(['income_id'=>['in',$income_ids]])->sum('number');
                //云攒金入金
                $air_income_total = AirIncomeLog::where(['assets_type'=>'air_num','add_time'=>['between',[$time,$time+86399]  ] ])->sum('number');
                $air_income_total1 = AirIncomeLog::where(['assets_type'=>'combine','add_time'=>['between',[$time,$time+86399]  ] ])->sum('air_num');
                $data = [
                    'today' => date('Y-m-d',$time),
                    'income_total' => $income_total,
                    'air_income_total' => keepPoint($air_income_total+$air_income_total1,6),
                    'recommand_income' => $recommand_income,
                    'air_income' => $air_income,
                    'percent' => $income_total>0 ? keepPoint(($recommand_income+$air_income)/$income_total*100,2) : 0,
                ];
                //今天 昨天的不缓存
                if($time<=$stop_time-86400*2) cache($cache_key,$data);
            }
            $list[] = $data;
        }

        //入金总量
        $income_total = AirIncomeLog::sum('number');
        //直推奖励总量
        $recommand_income_all = UserAirRecommend::sum('award_number');
        //云梯奖励总量
        $air_income_all = UserAirDiff::sum('number');
        $precent_all = $income_total>0 ? keepPoint(($recommand_income_all+$air_income_all)/$income_total*100,2) : 0;

        //云攒金总入金
        $air_income_total = AirIncomeLog::where(['assets_type'=>'air_num'])->sum('number');
        $air_income_total1 = AirIncomeLog::where(['assets_type'=>'combine'])->sum('air_num');
        $air_income_total = keepPoint($air_income_total+$air_income_total1,6);

        $page = new Bootstrap($list,$pageSize,$page,$all_day,false,['path'=>url('')]);
        $page = $page->render();
        return $this->fetch(null,compact('list','page','income_total','recommand_income_all','air_income_all','precent_all','air_income_total'));
    }

    public function award_top() {
        set_time_limit(0);
        $list = Db::query("select a.user_id,sum(a.recommend_reward+a.level_diff_reward+a.jackpot_reward) as a_num,m.phone,m.email from ".config('database.prefix')."user_air_level as a 
        left join ".config('database.prefix')."member  as m on a.user_id=m.member_id
        GROUP BY a.user_id ORDER BY a_num desc limit 100");
        return $this->fetch(null,compact('list'));
    }

    //入金等级 奖励比例减少
    public function levels_dec() {
        //测试
//        $setting = [
//            '2020-05-29 13:01:00' => '80',
//            '2020-05-29 12:59:00' => '85',
//        ];
        $pwd = \request()->get('pwd');
        if($pwd!='ksdkskd') {
            die('deny');
        }

        //正式
        $setting = [
            '2020-06-15 18:00:00' => '80',
            '2020-05-31 18:00:00' => '85',
        ];

        //当前最高比例:
        $max = AirLadderLevel::max('reward_radio');

        $curTime = time();
        foreach ($setting as $setting_time=>$percent) {
            if($curTime>strtotime($setting_time)) {
                if($max>$percent){
                    AirLadderLevel::where("reward_radio>0")->update([
                        'reward_radio' => ['dec',5],
                    ]);
                    echo $setting_time." : dec 5".PHP_EOL;
                }
                break;
            }
        }
        $maxAfterChange = AirLadderLevel::max('reward_radio');
        echo "更改前最高比例：".$max." 更改后最高比例：".$maxAfterChange;
    }
}
