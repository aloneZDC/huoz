<?php
namespace app\index\controller;

class Login extends Base
{
	protected $public_action = ['index'];
	public function index(){
		return $this->fetch();
	}
}