<?php
namespace app\index\controller;
class Home extends Base {
    public function left()
    {
        nav_active("22/2");

        return $this->fetch('public/left');
    }

}