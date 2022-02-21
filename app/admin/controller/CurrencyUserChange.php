<?php
namespace app\admin\controller;
use think\Db;
use think\paginator\driver\Bootstrap;
use think\Request;

class CurrencyUserChange extends Admin
{
    //太空计划
    public function index(Request $request)
    {
        $where = [];
        $currency_id = $request->get('currency_id');
        if ($currency_id) $where['a.currency_id'] = $currency_id;

        $list = Db::name('currency_user_change')->alias('a')->field('a.*,c.currency_name')
            ->join(config("database.prefix").'currency c','a.currency_id = c.currency_id',"LEFT")
        ->where($where)->order('a.id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = \app\common\model\Currency::where('is_line',1)->select();
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    //团队每日充提记录
    public function team_summary() {
        $online_start_time = strtotime('2020-11-05');

        $page = intval(input('page',1));
        $pageSize = 10;
        $stop_time = todayBeginTimestamp(); //结束时间
        $all_day = ($stop_time - $online_start_time)/86400;

        $team_user = Db::name('wallet_summary_address')->column('member_id');

        //每日汇总缓存
        $real_stop_time = $stop_time - 86400 * $pageSize*($page-1);//开始时间
        $start_time = $real_stop_time - 86400 * $pageSize;//开始时间
        for ($time=$real_stop_time;$time>=$start_time;$time-=86400) {
            $cache_key = 'team_summary_'.$time;
            $data = cache($cache_key);
            if(empty($data)) {
                if(!empty($team_user)) {
                    $team_user_data = [];
                    foreach ($team_user as $team_user_id) {
                        $time_end = $time+86400;
                        $tibi_total = Db::query("select sum(num) as num,sum(actual) as actual from yang_tibi where 
`transfer_type` = '1' and `status` in (-2,-1,0,1) and add_time between {$time} and {$time_end} and currency_id = ".\app\common\model\Currency::ERC20_ID." and (from_member_id in(
    select child_id from yang_member_bind where member_id={$team_user_id}
)  or from_member_id={$team_user_id})");
                        $tibi_audit_total = Db::query("select sum(num) as num,sum(actual) as actual from yang_tibi where 
`transfer_type` = '1' and `status` = 1 and add_time between {$time} and {$time_end} and currency_id = ".\app\common\model\Currency::ERC20_ID." and (from_member_id in(
    select child_id from yang_member_bind where member_id={$team_user_id}
)  or from_member_id={$team_user_id})");

                        $chongbi_total = Db::query("select sum(num) as num,sum(actual) as actual from yang_tibi where 
`status` = 3 and add_time between {$time} and {$time_end} and currency_id = ".\app\common\model\Currency::ERC20_ID." and (to_member_id in(
    select child_id from yang_member_bind where member_id={$team_user_id}
)  or to_member_id={$team_user_id})");
                        $team_user_data[] = [
                            'member_id' => $team_user_id,
                            'chongbi_total' => isset($chongbi_total[0]) && $chongbi_total[0]['actual'] ? $chongbi_total[0]['actual'] : 0,
                            'tibi_total' => isset($tibi_total[0]) && $tibi_total[0]['actual'] ? $tibi_total[0]['actual'] : 0,
                            'tibi_audit_total' => isset($tibi_audit_total[0]) && $tibi_audit_total[0]['actual'] ? $tibi_audit_total[0]['actual'] : 0,
                        ];
                    }
                }

                $data = [
                    'today' => date('Y-m-d',$time),
                    'data' => $team_user_data,
                ];
                //今天 昨天的不缓存
                if($time<=$stop_time-86400*2) cache($cache_key,$data);
            }
            $list[] = $data;
        }

        //分页
        $page = new Bootstrap($list,$pageSize,$page,$all_day,false,['path'=>url('')]);
        $page = $page->render();

        //总汇总
        if($team_user) {
            $team_user_total = [];
            foreach ($team_user as $team_user_id) {
                $tibi_total = Db::query("select sum(num) as num,sum(actual) as actual from yang_tibi where 
`transfer_type` = '1' and `status` in (-2,-1,0,1) and currency_id = " . \app\common\model\Currency::ERC20_ID . " and (from_member_id in(
    select child_id from yang_member_bind where member_id={$team_user_id}
) or from_member_id={$team_user_id})");
                $tibi_audit_total = Db::query("select sum(num) as num,sum(actual) as actual from yang_tibi where 
`transfer_type` = '1' and `status` = 1 and currency_id = " . \app\common\model\Currency::ERC20_ID . " and (from_member_id in(
    select child_id from yang_member_bind where member_id={$team_user_id}
)  or from_member_id={$team_user_id})");

                $chongbi_total = Db::query("select sum(num) as num,sum(actual) as actual from yang_tibi where 
`status` = 3 and currency_id = " . \app\common\model\Currency::ERC20_ID . " and (to_member_id in(
    select child_id from yang_member_bind where member_id={$team_user_id}
)  or to_member_id={$team_user_id})");
                $team_user_total[] = [
                    'member_id' => $team_user_id,
                    'chongbi_total' => isset($chongbi_total[0]) && $chongbi_total[0]['actual'] ? $chongbi_total[0]['actual'] : 0,
                    'tibi_total' => isset($tibi_total[0]) && $tibi_total[0]['actual'] ? $tibi_total[0]['actual'] : 0,
                    'tibi_audit_total' => isset($tibi_audit_total[0]) && $tibi_audit_total[0]['actual'] ? $tibi_audit_total[0]['actual'] : 0,
                ];
            }
        }
        return $this->fetch(null,compact('list','page','team_user_total'));
    }
}
