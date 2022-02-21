<?php
//投票 俱乐部
namespace app\h5\controller;

use app\common\model\CurrencyUserTransfer;
use app\common\model\GameLockLog;
use app\common\model\StoresListSearch;
use app\common\model\UsersVotes;
use app\common\model\UsersVotesAward;
use app\common\model\UsersVotesConfig;
use app\common\model\UsersVotesPay;
use think\Db;
use think\Exception;
use think\Request;

class Test
{
    protected  $public_action = ['index'];
    public function index(){
        $arr = decryptionEncode('dLOMTxHM2LCRuZNFqpqKrgoT8sv3eBLdJiCTz7XBJU25pzBpac5HYskgMZejtSY63vufyRL5w5bm4S3L1FwJrw==');
        echo "<pre>";
        print_r($arr);
        die();
    }
}
