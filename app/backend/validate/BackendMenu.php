<?php


namespace app\backend\validate;
use think\Validate;

class BackendMenu extends Validate
{
    protected $rule = [
        'name' => 'require',
        'cat_id' => 'require',
        'url' => 'require',
        'controller' => 'require',
        'action' => 'require',
    ];
    protected $message = [
        'name.require' => '名称不能为空',
        'cat_id.require' => '分类不能为空',
        'url.require' => 'URL不能为空',
        'controller.require' => '控制器不能为空',
        'action.require' => '需验证的方法不能为空',
    ];

    protected $scene = [
        'add' => ['name','cat_id','url','controller','action'],
        'edit' => ['name','cat_id','url','controller','action'],
    ];
}
