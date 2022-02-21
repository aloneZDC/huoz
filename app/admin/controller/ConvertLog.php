<?php


namespace app\admin\controller;


use think\Exception;
use think\exception\DbException;
use think\Request;
use app\common\model\ConvertLog as ConvertLogModel;

class ConvertLog extends Admin
{
    /**
     * @var ConvertLogModel
     */
    protected $convertLog;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->convertLog = new ConvertLogModel();
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    public function index(Request $request)
    {
        $list = $this->convertLog->with(['currency', 'toCurrency'])->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $this->convertLog->count('id');
        return $this->fetch(null, ['list' => $list, 'page' => $page, 'count' => $count]);
    }

}