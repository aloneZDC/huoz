<?php

namespace app\api\controller;

use app\common\model\ContractConfig;
use app\common\model\Currency;
use app\common\model\HongbaoConfig;
use app\common\model\RocketConfig;
use app\common\model\SpacePlanConfig;
use caption\UtilsCaption;
use think\Exception;
use think\Db;
use think\Log;

class Index extends Base
{
    private $code_secret="IO_EXCHANGE";
    protected $public_action = ['index', 'quotation','create_code','show_img','verify_code'];

    //首页
    public function index()
    {
        //Banner
        $flash = Db::name('Flash')->field('flash_id,pic,jump_url,bgcolor,bgcolor2')->order('sort asc')->where('type=2')->where('lang',$this->lang)->limit(8)->select();

        //资讯
//        $articles = Db::name('Article')->alias('a')->field('a.article_id,b.title')->join('__ARTICLE_' . strtoupper($this->lang) . '__ b', 'a.article_id=b.article_id', 'LEFT')->where('position_id=1')->order('add_time desc')->limit('0,1')->select();
//        if ($articles) {
//            $length = ($this->lang == 'en-us') ? 200 : 50;
//            foreach ($articles as $key => $value) {
//                $value['title'] = mb_substr((strip_tags(html_entity_decode($value['title']))), 0, $length, 'utf-8');
//                $articles[$key] = $value;
//            }
//        } else {
//            $articles = [];
//        }

        $pid = '';
        if ($this->checkLogin()) $pid = Db::name('member')->where(['member_id' => $this->member_id])->value('invit_code');


        //$hongbao_config = HongbaoConfig::get_key_value();
        $time = time();
        // $hongbao_open = $time>strtotime($hongbao_config['hongbao_auto_open']) ? 1 :0;
        // $flop_open = $time>strtotime($hongbao_config['flop_auto_open']) ? 1 :0;

        $contract_open_day = ContractConfig::get_value('contract_open_time','');
        $contract_open = $time>strtotime($contract_open_day) ? 1 :0;

        //$space_open_time = SpacePlanConfig::getValue('space_open_time',0);

        $dnc_currency_detail = Db::name('currency_introduce')->where('currency_id',40)->find();
        //头部链接
        $url = [
            'homepage' => $this->config['xrp_url'],
            'white_paper' => $this->config['xrp_white'],
            'block_query' => $this->config['xrp_block'],
//            'invit_url' => url('mobile/Invite/index', ['id' => $pid], false, true),
            'invit_url' => url('mobile/reg/mobile', ['invit_code' => $pid], false, true),
            'invit_code' => $pid,
            // 'hongbao_open_day' => $hongbao_config['hongbao_auto_open'],
            // 'hongbao_open' => $hongbao_open,
            // 'flop_open_day' => $hongbao_config['flop_auto_open'],
            // 'flop_open' => $flop_open,
            'contract_open_day' => $contract_open_day,
            'contract_open' => $contract_open,
            'websocket_url' => ContractConfig::get_value('contract_kline_websocket_url', ''),
            //'space_is_open' => time()-$space_open_time>=0 ? 1 : 0,
            'tft_currency_id' => Currency::TFT_ID,
            'public_chain_id' => Currency::PUBLIC_CHAIN_ID,
        ];

        $url['choose_url'] = [];
        if($dnc_currency_detail) {
            if($dnc_currency_detail['open_code_url']) $url['choose_url'][] = ['name' => 'DNC开源代码', 'url' => $dnc_currency_detail['open_code_url'] ];
            if($dnc_currency_detail['wallet_code_url']) $url['choose_url'][] = ['name' => 'DNC钱包开源代码', 'url' => $dnc_currency_detail['wallet_code_url']];
            if($dnc_currency_detail['browser_code_url']) $url['choose_url'][] = ['name' => 'DNC浏览器开源代码', 'url' => $dnc_currency_detail['browser_code_url']];
        }
        $exchange_fil = Db::name('common_mining_currency_price')->where(['mining_currency_id' => 81, 'status' => 1, 'platform' => 'Huobi'])->value('usdt');
        $lucky_warehouse = Db::name('rocket_goods')->sum('warehouse1');
        $market_warehouse = Db::name('rocket_goods')->sum('warehouse2');
        $tool_warehouse = Db::name('rocket_goods')->sum('warehouse3');
        $exchange_mtk = Db::name('mtk_currency_price')->where(['currency_id' => 93])->order('create_time desc')->value('price');
        $show_user_id = explode(',', RocketConfig::getValue('show_user_id'));
        $is_show = 0;
        if (in_array($this->member_id, $show_user_id)) {
            $is_show = 1;
        }
        $data = [
            'exchange_fil' => $exchange_fil,//fil价格
            'lucky_warehouse' => sprintf('%.2f', $lucky_warehouse),//幸运舱
            'market_warehouse' => sprintf('%.2f', $market_warehouse),//市值舱
            'tool_warehouse' => sprintf('%.2f', $tool_warehouse),//工具舱
            'exchange_mtk' => $exchange_mtk,//mtk
            'is_show' => $is_show//1显示市值舱、工具舱的用户 0不显示
        ];

        //客服、付款、收款链接
//        $asset_bank = [
//            'title' => lang('lan_interest_title'),
//            'info' => lang('lan_interest_info'),
//            'rate' => '18',
//            'support' => lang('lan_interest_support'),
//        ];
//
//        $boss_plan = [
//            'title' => lang('lan_boss_plan_title'),
//            'info' => lang('lan_boss_plan_info'),
//            'support' => lang('lan_boss_plan_support'),
//        ];
//
//        $innovation_zone = [
//            'title' => lang('lan_innovation_zone_title'),
//            'info' => lang('lan_innovation_zone_info'),
//            'support' => lang('lan_innovation_zone_support'),
//        ];

        $this->output(10000, lang('lan_operation_success'),compact('flash','url', 'data'));
    }

    public function quotation()
    {
        $quotation = $this->getQuotation();
        $this->output(10000, lang('lan_operation_success'), $quotation);
    }

    //行情模块未开发时的 替代方案
    private function getQuotation()
    {
        $cache_key = 'index_quotation';
        $result = cache($cache_key);
        if (!empty($result)) return $result;

        $usdt_price = $this->getUsdtCny();
        $xrp_usdt = $this->getCnyPrice('XRPUSDT');

        $new_price_status = $xrp_usdt['degree'] < 0 ? 0 : 1;
        $result[] = [
            'currency_mark' => 'XRP',
            'trade_currency_mark' => 'USDT',
            'new_price' => keepPoint($xrp_usdt['close'] / $usdt_price, 2),
            'new_price_cny' => keepPoint($xrp_usdt['close'], 2),
            'change_24' => keepPoint($xrp_usdt['degree'], 2),
            'new_price_status' => $new_price_status,
        ];

        $xrp_cny_price = $xrp_usdt['close'];
        foreach (['BTC', 'ETH'] as $value) {
            $data = $this->getCnyPrice($value . 'USDT');
            if (!empty($data)) {
                $new_price_status = $data['degree'] < 0 ? 0 : 1;

                $result[] = [
                    'currency_mark' => $value,
                    'trade_currency_mark' => 'XRP',
                    'new_price' => keepPoint($data['close'] / $xrp_cny_price, 6),
                    'new_price_cny' => keepPoint($data['close'], 2),
                    'change_24' => keepPoint($data['degree'], 2),
                    'new_price_status' => $new_price_status,
                ];
            }
        }
        cache($cache_key, $result, 60);

        return $result;
    }

    /**
     * 获取USDT的人民币价格
     * @return mixed
     */
    private function getUsdtCny()
    {
        $price = 0;

        $cny_price = $usdt_price = 0;
        $data = @file_get_contents("http://api.coindog.com/api/v1/tick/HUOBIPRO:BTCUSDT?unit=cny");
        if ($data) {
            $data = json_decode($data, true);
            if ($data['close']) {
                $cny_price = $data['close'];
            }
        }

        $data = @file_get_contents("http://api.coindog.com/api/v1/tick/HUOBIPRO:BTCUSDT?unit=usd");
        if ($data) {
            $data = json_decode($data, true);
            if ($data['close']) {
                $usdt_price = $data['close'];
            }
        }
        if ($cny_price > 0 && $usdt_price > 0) {
            $price = sprintf('%.4f', $cny_price / $usdt_price);
            cache('index_usdt_price', $price);
        }
        //获取失败时使用上次缓存
        if ($price <= 0) $price = floatval(cache('index_usdt_price'));

        return $price;
    }

    //获取火币网行情
    private function getCnyPrice($symbol = '')
    {
        $cache_key = 'index_' . $symbol;
        $data = @file_get_contents("http://api.coindog.com/api/v1/tick/HUOBIPRO:" . $symbol . "?unit=cny");
        if ($data) {
            $result = json_decode($data, true);
            if ($result['close']) {
                cache($cache_key, $result);
                $data = $result;
            }
        }

        //获取失败时使用上次缓存
        if (empty($data)) $data = cache($cache_key);
        return $data;
    }

    /**
     * 产生的随机字符串并和缓存里的不重复
     * @return string|
     * Create by: Red
     * Date: 2019/8/30 9:21
     */
    private function create_mrak()
    {
        $str = getNonceStr();
        $mark = cache("verificationcode_" . $str);
        if (!empty($mark)) {
            return $this->create_mrak();
        }
        return $str;
    }

    /**
     * 产生的随机字符串和现有缓存不重复,并保存到缓存里
     * @return string
     */
     function create_code()
    {
        $str=$this->create_mrak();
        $code = randNum(4);
        cache("verificationcode_" . $str, $code, 600);
       // $result['code'] = $code;
        $r['code']=SUCCESS;
        $r['message']=lang("lan_user_request_successful");
        $r['result'] = ['mark'=>$str,'value'=>md5($this->code_secret.$code)];
       return ajaxReturn($r);
    }

    /**
     * 显示图片验证码
     * @return string|void
     * Create by: Red
     * Date: 2019/8/30 9:34
     */
    function show_img($mark1=null)
    {
        $mark = input("mark")?input("mark"):$mark1;
        if (!empty($mark)) {
            $v=cache("verificationcode_".$mark);
            if (!empty($v)) {
                //验证码图片存在的,直接从文件里拿
                $field=file_exists(WEB_PATH."/code_img/".$v.".png");//判断图片验证码是否存在
                if($field){
                    $fp=fopen(WEB_PATH."/code_img/".$v.".png", "rb"); //二进制方式打开文件
                    header("Content-type: image/png");
                    fpassthru($fp); // 输出至浏览器
                    die();
                }else{
                    //验证码图片不存在的,创新并保存起来
                    $rsi = new UtilsCaption();
                    $rsi->TFontSize = array(35, 35);
                    $rsi->Width = 100;
                    $rsi->Height = 50;
                    $rsi->EnterCode =$v;
                    $rsi->Draw();
                    die();
                  // return $this->show_img($mark);
                }
            }
        }
        return "";
    }

}
