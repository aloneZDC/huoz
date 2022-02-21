<?php


namespace app\common\model;


use think\Model;

/**
 * Class AccountBookType
 * @package app\common\model
 */
class AccountBookType extends Model
{
    /**
     * @var string
     */
    protected $table = "yang_accountbook_type";

    //---------游戏-start------------------------
    /**
     * 游戏准备
     */
    const GAME_READY = 401;

    /**
     * 游戏赢
     */
    const GAME_WIN_MONEY = 402;

    /**
     * 游戏取消准备
     */
    const GAME_CANCEL_READY = 403;

    /**
     * 游戏赠送
     */
    const GAME_GIVE_REWARD = 404;

    /**
     * VIP房间抽水
     */
    const VIP_ROOM_DRAW = 405;

    /**
     * 房间解散
     */
    const ROOM_DISBAND = 406;
    //---------游戏-end--------------------------

    //---------游戏-start------------------------
    /**
     * 合约下单
     */
    const CONTRACT_ORDER = 1100;

    /**
     * 合约收益
     */
    const CONTRACT_INCOME = 1101;

    /**
     * 合约直推奖
     */
    const CONTRACT_ZHITUI = 1102;

    /**
     * 合约锁仓释放
     */
    const CONTRACT_LOCK_FREE = 1103;

    /**
     * 合约保险金
     */
    const CONTRACT_SAFE = 1104;

    /**
     * 时时合约下单
     */
    const CUT_CONTRACT_ORDER = 1105;

    /**
     * 时时合约收益
     */
    const CUT_CONTRACT_INCOME = 1106;

    /**
     * 永续合约下单
     */
    const FOREVER_CONTRACT_ORDER = 1107;

    /**
     * 永续合约收益
     */
    const FOREVER_CONTRACT_INCOME = 1108;

    /**
     * 永续合约撤销
     */
    const FOREVER_CONTRACT_CANCEL = 1109;

    /**
     * 永续合约撤销保险金退还
     */
    const FOREVER_CONTRACT_CANCEL_SAFE_RETURN = 1110;
    //---------游戏-end--------------------------

    /**
     * 共振扣除
     */
    const SUB_FOR_RESONANCE = 1300;

    /**
     * 共振获取
     */
    const ADD_FOR_RESONANCE = 1301;

    /**
     * 云梯直推奖
     */
    const AIR_LADDER_RELEASE = 1400;

    /**
     * 云梯级差奖
     */
    const AIR_DIFF_REWARD = 1401;

    /**
     * 云梯周分红
     */
    const AIR_JACKPOT_REWARD = 1402;

    /**
     * 云梯入金
     */
    const AIR_DIFF = 1403;

    /**
     * 云梯激活
     */
    const AIR_ACTIVE = 1404;

    /**
     * 为好友激活
     */
    const AIR_PAY_ACTIVE = 1405;

    /**
     * 为好友晋升
     */
    const AIR_PAY_DIFF = 1406;

    /**
     * DNC每日释放
     */
    const DNC_RELEASE = 1407;

    /**
     * DNC加速释放
     */
    const DNC_FAST_RELEASE = 1409;

    /**
     * DNC礼包
     */
    const DNC_GIFT = 1408;

}