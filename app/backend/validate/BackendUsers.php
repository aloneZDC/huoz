<?php

namespace app\backend\validate;

use think\Validate;

class BackendUsers extends Validate
{
    protected $rule = [
        'username' => 'require|unique:BackendUsers,username',
        'password' => 'require',
        'status' => 'require',
        'type' => 'require|in:1,2',
        'email' => 'require|email',
    ];
    protected $message = [
        'username.require' => '用户名不能为空',
        'username.unique' => '用户名已存在',
        'password.require' => '密码不能为空',
        'status.require' => '状态不能为空',
        'type.require' => '身份不能为空',
        'type.in' => '身份值类型错误!',
        'email.require' => '邮箱不能为空',
        'email.email' => '邮箱格式不正确',
    ];

    protected $scene = [
        'add' => ['username', 'password', 'status', 'type','email'],
        'edit' => ['status','email'],
    ];
}
