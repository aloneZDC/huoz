<?php
namespace app\mobile\controller;

class Index extends Base
{
    public function index()
    {
        return 'welcome to rbz';
    }

    public function set_languages()
    {
        $lang = input('lang');
        if(!empty($lang)) cookie('think_language',$lang);
        $this->output(10000,'');
    }
}
