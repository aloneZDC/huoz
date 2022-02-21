<?php
namespace app\backend\validate;
use think\Validate;

class BackendMenuCat extends Validate
{
    protected $rule = [
        'name' => 'require',
        'icon' => 'require',
    ];
    protected $message = [
        'name.require' => '名称不能为空',
        'icon.require' => '图标(fa-icon)不能为空',
    ];

    protected $scene = [
        'add' => ['name', 'icon'],
        'edit' => ['name', 'icon'],
    ];
}
