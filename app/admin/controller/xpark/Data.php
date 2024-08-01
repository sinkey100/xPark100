<?php

namespace app\admin\controller\xpark;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;
use app\common\controller\Backend;

/**
 * xPark数据
 */
class Data extends Backend
{
    /**
     * Data模型对象
     * @var object
     * @phpstan-var \app\admin\model\xpark\Data
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

//    protected array $withJoinTable = ['domain'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\xpark\Data();
    }

    protected function calcData()
    {
        $domain_filter = count($this->auth->domain_arr) > 0 ? $this->auth->domain_arr : false;

        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        $dimensions = $this->request->get('dimensions/a', []);
        $dimension  = [];
        foreach ($dimensions as $k => $v) {
            if ($k && $v == 'true') {
                $dimension[] = $k;
            }
        }


        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $field = array_merge($dimension, [
            'SUM(requests) AS requests',
            'SUM(fills) AS fills',
            'SUM(impressions) AS impressions',
            'SUM(clicks) AS clicks',
            'SUM(ad_revenue) AS ad_revenue',
        ]);

        $res = $this->model->field($field)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where);


        if ($domain_filter) {
            $res = $res->where('domain_id', 'in', $domain_filter);
        }

//        $res = $res->fetchSql(true)->select();
//        $this->error($res);

        $res = $res->order('id', 'desc')
            ->group(implode(',', $dimension));
        return [$res, $limit, $dimension];
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {

        [$res, $limit, $dimension] = $this->calcData();
        $res = $res->paginate($limit);
        $res->visible(['domain' => ['domain']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function export(): void
    {
        [$list, $limit, $dimension] = $this->calcData();
        $list = $list->select();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $cell = [
            '#'               => '#',
            'a_date'          => '日期',
            'sub_channel'     => '子渠道',
            'country_code'    => '地区',
            'ad_placement_id' => '广告单元',
            'ad_revenue'      => '收入',
            'impressions'     => '展示',
            'requests'        => '请求数',
            'clicks'          => '点击数',
            'click_rate'      => '点击率',
            'fills'           => '填充数',
            'fill_rate'       => '填充率',
            'unit_price'      => '单价',
            'ecpm'            => 'eCPM',
        ];

        foreach (['a_date', 'sub_channel', 'country_code', 'ad_placement_id'] as $v) {
            if (!in_array($v, $dimension)) unset($cell[$v]);
        }


        $i = 0;
        foreach ($cell as $k => $v) {
            $i++;
            $sheet->setCellValue([$i, 1], $v);
        }

        $h = 2;
        foreach ($list as $v) {
            $i = 0;
            foreach ($cell as $key => $value) {
                $i++;
                $value = str_replace(' 00:00:00', '', $v[$key] ?? $h - 1);
                $sheet->setCellValue([$i, $h], $value);
            }
            $h++;
        }

        $writer = new Xlsx($spreadsheet);
        $file   = time() . '.xlsx';
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: think-lang, server, ba_user_token, ba-user-token, ba_token, ba-token, batoken, Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With');
        header('Access-Control-Max-Age: 1800');
        header('Access-Control-Expose-Headers:Content-Disposition');
        header('Content-Disposition: attachment;filename=' . $file);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
    }


}