<?php
namespace app\backend\model;


use think\Db;
use think\Model;

class BackendMenu extends Model
{
    public function cat()
    {
        return $this->belongsTo('app\\backend\\model\\BackendMenuCat', 'cat_id', 'id')->field('id,name');
    }

    //根据用户ID获取菜单列表
    public static function getMenus($admin_id) {
        $cache_key = 'admin_menus_'.$admin_id;
//        $menu_list = cache($cache_key);
//        if(empty($menu_list)) {
            $admin_menus = self::getAdminMenu($admin_id);
            if(!$admin_menus) return [];

            $menus_cats = Db::name('backend_menu_cat')->field('id,name,icon')->where(['status' => 1])->order('sort_id asc,id asc')->select();
            if ($menus_cats) $menus_cats = array_column($menus_cats, null, 'id');

            $menus = self::field('id,cat_id,name,url,param')->where(['id'=>['in',$admin_menus['menu_ids']], 'status' => 1])->order('sort_id asc,id asc')->select();
            foreach ($menus as $menu) {
                if(isset($menus_cats[$menu['cat_id']])) {
                    if(!isset($menus_cats[$menu['cat_id']]['children'])) {
                        $menus_cats[$menu['cat_id']]['url'] = "";
                        $menus_cats[$menu['cat_id']]['children'] = [];
                        $menus_cats[$menu['cat_id']]['id'] *= 100000; //防止 weadmin 把cat_id 当成 id
                    }

                    $menu = $menu->toArray();
                    $menu['url'] = url($menu['url'],$menu['param']);
                    $menu['icon'] = "";
                    $menus_cats[$menu['cat_id']]['children'][] = $menu;
                }
            }

            //兼容weadmin
            foreach ($menus_cats as $key=>$cats){
                if(!isset($cats['children'])) unset($menus_cats[$key]);
            }

            $menu_list = $menus_cats;
            cache($cache_key,$menu_list,1);
//        }
        return $menu_list;
    }

    /**
     * 检测用户是否拥有权限
     * @param int $admin_id 管理员ID
     * @param string $controller 当前控制器
     * @param string $action 当前方法
     * @param string $param
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function checkCurrAuth($admin_id,$controller,$action,$param='') {
        //获取当前菜单是否存在
        $where = ['controller'=>$controller];
        if(!empty($param)) $where['param'] = $param;

        $currmenu = self::where($where)->find();
        if(empty($currmenu)) return false;

        //获取该用户拥有的权限
        $admin_menus = self::getAdminMenu($admin_id);
        if(!$admin_menus) false;

        //验证当前菜单是否有存在
        if(!in_array($currmenu['id'],$admin_menus['menu_ids'])) return false;

        //验证当前方法是否有权限
        if(!in_array($action,$admin_menus['users_menus'][$currmenu['id']])) return false;

        return true;
    }

    /**
     * 解析管理员权限
     * @param $admin_id
     * @return array|bool
     menu_id:add,edit,index\n
     menu_id:add,edit,index\n
     */
    public static function getAdminMenu($admin_id) {
        $users_menus = Db::name('backend_users')->where(['id' => $admin_id])->value('menus');
        if (empty($users_menus)) return false;

        $users_menu = [];
        $menu_ids = [];
        $users_menus = explode("\n", $users_menus);
        foreach ($users_menus as $menu) {
            $menu = explode(":",$menu);
            if(count($menu)==2) {
                $menu[0] = intval($menu[0]);

                $menu_ids[] = $menu[0];
                $users_menu[$menu[0]] = explode(',',trim($menu[1]));
            }
        }
        return ['users_menus'=>$users_menu,'menu_ids'=>$menu_ids];
    }

    public static function getAllMenus() {
        $menus_cats = Db::name('backend_menu_cat')->field('id,name,icon')->where(['status' => 1])->order('sort_id asc,id asc')->select();
        if ($menus_cats) $menus_cats = array_column($menus_cats, null, 'id');

        $menus = self::field('id,cat_id,name,param,action')->where(['status' => 1])->order('sort_id asc,id asc')->select();
        foreach ($menus as $menu) {
            if(isset($menus_cats[$menu['cat_id']])) {
                if(!isset($menus_cats[$menu['cat_id']]['children'])) {
                    $menus_cats[$menu['cat_id']]['children'] = [];
                }

                $menu = $menu->toArray();
                $actions = [];
                $action = explode("\n",$menu['action']);
                foreach ($action as $ac) {
                    $ac = explode(":",$ac);
                    if(count($ac)==2) {
                        $actions[$ac[0]] = $ac[1];
                    }
                }
                $menu['action'] = $actions;
                $menus_cats[$menu['cat_id']]['children'][] = $menu;
            }
        }
        return $menus_cats;
    }
}
