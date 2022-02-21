<?php
//传统矿机 用户汇总

namespace app\common\model;

use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class CommonMiningMember extends Base
{
    /**
     * 综合明细
     * @param int $member_id 用户ID
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function complex_detail($member_id)
    {
        // 满存算力用户汇总
        $common_member = self::where(['member_id' => $member_id])->find();

        // 独享矿机推荐奖
        $alone_recommend = AloneMiningMember::where(['member_id' => $member_id])->field('total_child1,total_child2,total_child3')->find();

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'alone_recommend' => keepPoint($alone_recommend['total_child1'] + $alone_recommend['total_child2'] + $alone_recommend['total_child3'], 6), // 独享矿机推荐奖
            'common_recommend' => keepPoint($common_member['total_child1'] + $common_member['total_child2'] + $common_member['total_child3'], 6), // 满存算力推荐奖
            'total_child7' => empty($common_member['total_child7']) ? '0.000000' : $common_member['total_child7'], // 全球加权
            'total_child9' => empty($common_member['total_child9']) ? '0.000000' : $common_member['total_child9'], // 合伙股东奖励
            'total_child10' => empty($common_member['total_child10']) ? '0.000000' : $common_member['total_child10'], // 合伙股东技术服务费奖励
        ];
        return $r;
    }

    // 加权分红统计
    static function globalIncome($member_id)
    {
        $r = ['code' => SUCCESS, 'message' => lang('data_success'), 'result' => null];
        return $r;

        $config = CommonMiningConfig::get_key_value();
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $config['default_lock_currency_id']);
        $CommonMiningMember = CommonMiningMember::where(['member_id' => $member_id])->find();
        $total_lock_num = CommonMiningPay::where(['member_id' => $member_id])->field('sum(total_lock_num) as total_lock_num,sum(total_lock_yu) as total_lock_yu')->find(); // 总锁仓数量
        $Currency = Currency::where(['currency_id' => ['in', [$config['pay_currency_id'], $config['release_currency_id']]]])->column('currency_name', 'currency_id');

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'global_lock' => $currency_user ? $currency_user['global_lock'] : 0, // 加权未解冻 75% USDT
            'total_child8' => $CommonMiningMember ? $CommonMiningMember['total_child8'] : 0, // 加权已解冻 75%
            'global_currency' => $Currency[$config['pay_currency_id']],

            'common_lock_num' => $total_lock_num['total_lock_num'] ? $total_lock_num['total_lock_num'] : 0, // 矿机产出 剩余 75% FIL
            'total_child6' => keepPoint($total_lock_num['total_lock_num'] - $total_lock_num['total_lock_yu'], 6), // 矿机产出 已经 75%
            'common_lock_currency' => $Currency[$config['release_currency_id']],
        ];
        return $r;
    }

    // 团队奖励统计
    static function teamRewards($member_id)
    {
        $one_team_count = CommonMiningConfig::getValue('one_team_count', 10);
        $miningMember = self::where(['member_id' => $member_id])->find();
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'one_team_config' => $one_team_count,
            'one_team_count' => $miningMember['one_team_count'] ? $miningMember['one_team_count'] : 0,
            'total_child' => keepPoint($miningMember['total_child1'] + $miningMember['total_child2'] + $miningMember['total_child3'], 6), // 推荐奖励123代
            'global_nul' => keepPoint($miningMember['total_child7'] + $miningMember['total_child8'], 6), // 加权奖励 25% + 已释放
        ];
        return $r;
    }

    static function member_summary($member_id)
    {
        $currency_id = CommonMiningConfig::getValue('release_currency_id', 0);
        $total = CommonMiningRelease::where(['member_id' => $member_id, 'currency_id' => $currency_id])->sum('num');
        $yestdoy = CommonMiningRelease::where(['member_id' => $member_id, 'currency_id' => $currency_id, 'release_time' => todayBeginTimestamp()])->sum('num');

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'total' => keepPoint($total, 6),
            'yestoday' => keepPoint($yestdoy, 6),
        ];
        return $r;
    }

    public static function add_item($member_id, $pay_tnum = 0, $price_usdt = 0)
    {
        try {
            $info = self::where(['member_id' => $member_id])->find();
            if ($info) {
                $flag = true;
                if ($pay_tnum > 0 || $price_usdt > 0) {
                    $flag = self::where(['member_id' => $member_id])->update([
                        'pay_num' => ['inc', $price_usdt],
                        'pay_tnum' => ['inc', $pay_tnum],
                        'last_pay_num' => date('Y-m-d') == date('Y-m-d', $info['last_pay_num_time']) ? ['inc', $price_usdt] : $price_usdt,
                        'last_pay_num_time' => time(),
                    ]);
                }
            } else {
                $flag = self::insertGetId([
                    'member_id' => $member_id,
                    'pay_num' => $price_usdt,
                    'pay_tnum' => $pay_tnum,
                    'last_pay_num' => $price_usdt,
                    'last_pay_num_time' => time(),
                    'add_time' => time(),
                ]);
            }

            return $flag;
        } catch (Exception $e) {
            return false;
        }
    }

    // 增加直推业绩
    public static function add_one_team_num($member_id, $pay_num = 0)
    {
        $pid_member = Member::where(['member_id' => $member_id])->field('pid')->find();
        if (empty($pid_member) || $pid_member['pid'] <= 0) return true;

        $pid_common_mining = self::where(['member_id' => $pid_member['pid']])->find();
        if (empty($pid_common_mining)) return true;

        // 统计直推数据
        $common_member = self::alias('a')
            ->join("member_bind b", "a.member_id = b.child_id AND b.`level` = 1")
            ->where('b.member_id = ' . $pid_member['pid'] . ' AND a.pay_tnum > 0')->field('COUNT(*) user_count,SUM(a.pay_tnum) tnum_total')->find();

        return self::where(['id' => $pid_common_mining['id'], 'one_team_total' => $pid_common_mining['one_team_total']])->update([
            'one_team_total' => ['inc', $pay_num], // 直推业绩
            'one_team_count' => empty($common_member['user_count']) ? 0 : $common_member['user_count'], //有效直推个数
            'one_team_tnum' => empty($common_member['tnum_total']) ? 0 : $common_member['tnum_total'], //总直推T数
        ]);
    }

    // 增加团队业绩 - 入金
    public static function add_parent_team_num($child_member_id, $pay_num)
    {
        return self::execute('update ' . config("database.prefix") . 'common_mining_member a,' . config("database.prefix") . 'member_bind b
            set a.team_num = a.team_num + ' . $pay_num . ' where a.member_id = b.member_id and b.child_id=' . $child_member_id . ';');
    }

    /**
     * 增加团队业绩 - T数
     * @param int $child_member_id 用户ID
     * @param int $tnum T数
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    public static function add_parent_team_tnum($child_member_id, $tnum)
    {
        return self::execute('update ' . config("database.prefix") . 'common_mining_member a,' . config("database.prefix") . 'member_bind b
            set a.team_tnum = a.team_tnum + ' . $tnum . ' where a.member_id = b.member_id and b.child_id=' . $child_member_id . ';');
    }

    /**
     * 合伙股东 - 满足条件的用户
     * @param $common_mining_config
     * @return mixed
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function partner_member($common_mining_config)
    {
        $partner_member = self::query('SELECT DISTINCT * FROM (
(SELECT * FROM `' . config("database.prefix") . 'common_mining_member` WHERE  `pay_tnum` >= ' . $common_mining_config['partner_self_tnum'] . ' ORDER BY `last_tnum_time` ASC LIMIT ' . $common_mining_config['partner_self_ranking'] . ')
UNION
(SELECT * FROM `' . config("database.prefix") . 'common_mining_member` WHERE  `one_team_count` >= ' . $common_mining_config['partner_team_direct'] . '  AND `team_tnum` >= ' . $common_mining_config['partner_team_tnum'] . ' ORDER BY `last_tnum_time` ASC LIMIT ' . $common_mining_config['partner_team_ranking'] . ')
) AS t ORDER BY member_id ASC;');
        return $partner_member;
    }

    // 获取最大等级是level的部门数量
    static function getTeamLevelCount($pid_member_id, $level)
    {
        // 获取直推中的最大区业绩
        $child_max_num = self::query('select count(*) as count from ' . config("database.prefix") . 'common_mining_member where member_id in(
            select child_id from ' . config("database.prefix") . 'member_bind where member_id=' . $pid_member_id . ' and level=1
        ) and (level>=' . $level . ' or team_max_level>=' . $level . ' );');

        if ($child_max_num && isset($child_max_num[0])) {
            return $child_max_num[0]['count'];
        } else {
            // 没有业绩
            return 0;
        }
    }

    // 获取直推入金人数
    static function getTeamRecommandMember($pid_member_id)
    {
        // 获取直推中的最大区业绩
        $child_max_num = self::query('select count(*) as count from ' . config("database.prefix") . 'common_mining_member where member_id in(
            select child_id from ' . config("database.prefix") . 'member_bind where member_id=' . $pid_member_id . ' and level=1
        )');

        if ($child_max_num && isset($child_max_num[0])) {
            return $child_max_num[0]['count'];
        } else {
            // 没有业绩
            return 0;
        }
    }

    // 更改团队中的最大等级
    static function updateTeamMaxLevel($child_member_id, $level)
    {
        return self::execute('update ' . config("database.prefix") . 'common_mining_member a,' . config("database.prefix") . 'member_bind b
            set a.team_max_level=' . $level . ' where a.member_id = b.member_id and a.team_max_level<' . $level . ' and  b.child_id=' . $child_member_id . ';');
    }

    // 我的等级
    static function my_level($member_id)
    {
        $r['code'] = SUCCESS;
        $r['message'] = lang('success');

        $common_mining = CommonMiningMember::where(['member_id' => $member_id])->find();
        $level_name = 'T0';
        if ($common_mining && $common_mining['level'] > 0) {
            $level_config = CommonMiningLevelConfig::where(['level_id' => $common_mining['level']])->find();
            if (!empty($level_config)) $level_name = $level_config['level_name'];
        }

        $r['result'] = [
            'level' => $common_mining ? $common_mining['level'] : 0,
            'level_name' => $level_name,
            'team_total' => $common_mining ? $common_mining['team_total'] : 0,
            'pay_num' => $common_mining ? $common_mining['pay_num'] : 0,
        ];
        return $r;
    }

    // 我的直推入金人数
    static function myOneTeamCount($member_id)
    {
        $where = [
            'a.pay_num' => ['gt', 0], //20200225 无入金也有奖励
            'b.member_id' => $member_id,
            'b.level' => 1,
        ];

        $total = self::alias('a')->where($where)
            ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
            ->count();
        return intval($total);
    }

    // 我的团队
    static function myTeam($member_id, $page = 1, $page_size = 10)
    {
        $config = CommonMiningConfig::get_key_value();

        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = [
            'head' => '',
            'nick' => '',
            'level' => 'F0',
            'total' => 0,
            'recommand' => [
                'num' => 0, //累计可用
                'lock_num' => 0, //锁仓剩余
                'percent' => $config['lock_release_percent'], //锁仓释放比例
            ],
            'team' => [
                'num' => 0,
            ],
            'list' => [],
        ];

        $member_info = Member::where('member_id', $member_id)->field('head,nick')->find();
        if ($member_info) {
            $r['result']['head'] = empty($member_info['head']) ? model('member')->default_head : $member_info['head'];
            $r['result']['nick'] = $member_info['nick'];
        }


        $member = CommonMiningMember::where(['member_id' => $member_id])->find();
        if ($member) {
            $r['result']['level'] = 'F' . $member['level'];
            $r['result']['recommand']['num'] = keepPoint($member['total_child1'] + $member['total_child2'] + $member['total_child3'] + $member['total_child5'], 6);
            $r['result']['team']['num'] = 0;
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id, $config['default_lock_currency_id']);
        if ($currency_user) {
            $r['result']['recommand']['lock_num'] = $currency_user['common_lock_num'];
        }

        // 入金才显示
        $list = self::alias('b')->where(['a.pid' => $member_id, 'b.pay_num' => ['gt', 0]])->field('a.member_id,a.ename,a.phone,a.email,a.reg_time,b.level,b.pay_num')
            ->join(config("database.prefix") . "member a", "a.member_id=b.member_id", "LEFT")
            ->page($page, $page_size)->select();

        // 只要注册就显示
//        $list = Member::alias('a')->where(['a.pid'=>$member_id])->field('a.phone,a.email,a.reg_time,b.level,b.pay_num')
//            ->join(config("database.prefix") . "common_mining_member b", "a.member_id=b.member_id", "LEFT")
//            ->page($page, $page_size)->select();
        if (!empty($list)) {
            $all_levels = CommonMiningLevelConfig::getAllLevel();
            if ($all_levels) $all_levels = array_column($all_levels, null, 'level_id');
            foreach ($list as $key => &$value) {
                $value['reg_time'] = date('m-d H:i', $value['reg_time']);
                if (!$value['level']) $value['level'] = 0;
                if (!$value['pay_num']) $value['pay_num'] = 0;
                $value['currency_name'] = 'USDT';

                $value['level_name'] = isset($all_levels[$value['level']]) ? $all_levels[$value['level']]['level_name'] : 'F0';
                if (empty($value['phone'])) $value['phone'] = $value['email'];
                unset($value['email']);

                // 统计直推人数
                $value['direct_people'] = MemberBind::where(['member_id' => $value['member_id'], 'level' => 1])->count();
            }
        } else {
            $list = [];
        }

//        $total = Member::where(['pid'=>$member_id])->count();
        $total = self::alias('b')->where(['a.pid' => $member_id, 'b.pay_num' => ['gt', 0]])->field('a.ename,a.phone,a.email,a.reg_time,b.level,b.pay_num')
            ->join(config("database.prefix") . "member a", "a.member_id=b.member_id", "LEFT")
            ->count();

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result']['list'] = $list;
        $r['result']['total'] = $total;
        return $r;
    }


    public function users()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
}
