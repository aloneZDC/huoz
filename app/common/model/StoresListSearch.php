<?php
//线下商家
namespace app\common\model;

class StoresListSearch {
    const RAiDUS = 30000000;

    static function get_my_near_stores($latitude,$longitude,$page=1,$rows=10,$main_project_id=0,$search='') {
        $r['code']=ERROR1;
        $r['message']=lang("no_data");
        $r['result']=null;
        if(!is_numeric($latitude) || !$longitude) return $r;

        $round = self::search_by_round($latitude,$longitude,self::RAiDUS);

        $where = [];
        $field = "stores_id,user_id,phone,stores_name,main_project_id,full_address,longitude,latitude,week_start,week_stop,hour_start,hour_stop,banner_image,logo,
        ROUND(
        6378.138 * 2 * ASIN(
            SQRT(
                POW(
                    SIN(
                        (
                            {$latitude} * PI() / 180 - latitude * PI() / 180
                        ) / 2
                    ),
                    2
                ) + COS( {$latitude} * PI() / 180) * COS( latitude * PI() / 180) * POW(
                    SIN(
                        (
                            {$longitude} * PI() / 180 - longitude * PI() / 180
                        ) / 2
                    ),
                    2
                )
            )
        ) * 1000
    ) AS distance";
        $where['latitude'] =  [ ['EGT',$round['minLat']],  ['ELT',$round['maxLat']], 'and']; //(`longitude` >= minLng) AND (`longitude` <= maxLng)
        $where['longitude'] = [ ['EGT',$round['minLng']], ['ELT',$round['maxLng']], 'and']; //(`longitude` >= minLng) AND (`longitude` <= maxLng)
        $where['status'] = 1;
        if($main_project_id) $where['main_project_id'] = $main_project_id;
        if($search) $where['stores_name'] = ['like','%'.$search.'%'];
        $list = StoresList::field($field)->where($where)->with(['project'])->page($page, $rows)->order('distance asc')->select();
        if($list) {
            foreach ($list as &$item) {
                $item['transfer_people'] = $item['transfer_num'] = 0;
                $transfer_total = StoresCardLog::where(['type'=>'transfer_out','user_id'=>$item['user_id']])->field('count(id) as transfer_people,sum(number) as transfer_num')->find();
                if($transfer_total)  {
                    $item['transfer_people'] = $transfer_total['transfer_people'] ? $transfer_total['transfer_people'] : 0;
                    $item['transfer_num'] = $transfer_total['transfer_num'] ? stores_fotmat_number($transfer_total['transfer_num']) : 0;
                }
                $item['transfer_currency_name'] = lang('uc_card');

                $item['project_name'] = isset($item['project']) ? $item['project']['cat_name']: '';
                $item['project_icon'] = isset($item['project']) ? $item['project']['icon']: '';
                $item['distance'] = $item['distance']>1000 ? keepPoint($item['distance']/1000,2).'km' : $item['distance'].'m';
                unset($item['project']);
            }

            $r['code'] = SUCCESS;
            $r['message'] = lang('data_success');
            $r['result'] = $list;
        }
        return $r;
    }

    //根据两个经纬度获取距离（米）
    static function getDistance($lat1, $lng1, $lat2, $lng2){
        $dx = $lng1 - $lng2; // 经度差值
        $dy = $lat1 - $lat2; // 纬度差值
        $b = ($lat1 + $lat2) / 2.0; // 平均纬度
        $Lx = deg2rad($dx) * 6367000.0* cos(deg2rad($b)); // 东西距离
        $Ly = 6367000.0 * deg2rad($dy); // 南北距离
        return round(sqrt($Lx * $Lx + $Ly * $Ly));//'米'; // 用平面的矩形对角距离公式计算总距离
    }

    /**
     * @param $latitude 纬度
     * @param $longitude 经度
     * @param $raidus 半径范围(单位：米)
     * @return array
     */
    static function search_by_round($latitude,$longitude,$raidus=3000) {
        $PI = 3.14159265;
        $degree = (24901*1609)/360.0;
        $dpmLat = 1/$degree;
        $radiusLat = $dpmLat*$raidus;
        $minLat = $latitude - $radiusLat;
        $maxLat = $latitude + $radiusLat;
        $mpdLng = $degree*cos($latitude * ($PI/180));
        $dpmLng = 1 / $mpdLng;
        $radiusLng = $dpmLng*$raidus;
        $minLng = $longitude - $radiusLng;
        $maxLng = $longitude + $radiusLng;
        return ['minLat'=>$minLat, 'maxLat'=>$maxLat, 'minLng'=>$minLng, 'maxLng'=>$maxLng];
    }
}