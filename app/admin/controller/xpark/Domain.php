<?php

namespace app\admin\controller\xpark;

use Throwable;
use app\admin\model\Admin;
use app\common\controller\Backend;
use app\admin\model\xpark\Domain as DomainModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use app\admin\model\xpark\Utc;
use app\admin\model\xpark\Apps;
use app\admin\model\xpark\Channel;
use Exception;

/**
 * 域名
 */
class Domain extends Backend
{
    /**
     * Domain模型对象
     * @var object
     * @phpstan-var DomainModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id', 'domain'];

    protected array $withJoinTable = [];


    public function initialize(): void
    {
        parent::initialize();
        $this->model = new DomainModel();
    }

    public function index(): void
    {
        $domain_filter = array_column(DomainModel::field('id')->where('admin_id', $this->auth->id)->select()->toArray(), 'id');
        if ($this->auth->id > 1 && count($domain_filter) == 0) $domain_filter = [1];

        $map = [];
        if ($this->auth->id == 27) {
            $domain_filter = [];
            $map[] = ['channel.private_switch' , '=', 0];
        }

//        if ($this->request->param('select')) {
//            $this->select();
//        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model->alias($alias)
            ->field(['*', 'domain.id', 'domain.status', 'apps.app_name', 'admin.nickname as admin_nickname'])
            ->join('xpark_apps apps', 'apps.id = domain.app_id', 'left')
            ->join('xpark_channel channel', 'channel.id = domain.channel_id', 'left')
            ->join('admin admin', 'admin.id = domain.admin_id', 'left')
            ->where($where)
            ->where($map);

        if ($domain_filter) {
            $res = $res->where('domain.id', 'in', $domain_filter);
        }

        $res = $res->order('domain.id', 'desc')
            ->paginate($limit);

        $res->visible(['admin' => ['nickname']]);

        $list = $res->items();
        foreach ($list as &$v) {
            $v['full_name']    = $v['domain'] . ' - ' . ($v['admin']['nickname'] ?? '/');
            $v['channel_full'] = ($v['channel'] ?? '') . '-' . ($v['flag'] ?? '');
        }

        $this->success('', [
            'list'   => $list,
            'total'  => $res->total(),
            'remark' => '',
        ]);
    }

    public function bill()
    {
        set_time_limit(0);
        $cut      = $this->request->post('cut/a', []);
        $domains  = $this->request->post('domains/a', []);
        $exchange = $this->request->post('exchange/a', []);
        $rate     = $this->request->post('rate/a', []);
        $month    = $this->request->post('month/s', '');
        $app_ids  = $this->request->post('app_ids/a', []);
        if (!$month) $this->error('请选择数据月份');
        $month = date("Y-m", strtotime($month));
        if (
            count($cut) != count($domains) ||
            count($exchange) != count($rate) ||
            count($cut) != count($exchange) ||
            count($cut) == 0 || count($app_ids) == 0
        ) {
            $this->error('数据不完整');
        }
        // 查找合作方
        $app = Apps::where('id', $app_ids[0])->find();
        if (!$app) $this->error('应用不存在');
        $user = Admin::where('id', $app->admin_id)->find();
        if (!$user) $this->error('合作方不存在');

        $spreadsheet = IOFactory::load(root_path() . 'extend/tpl/bill.xlsx');
        $sheet       = $spreadsheet->getActiveSheet();

        // 修改合作方
        $sheet->setCellValue('A6', '合作方：' . $user->nickname);
        $sheet->setCellValue('J8', $exchange[0] > 1 ? '结算人民币金额' : '结算美元金额');
        // 设置行数
        for ($i = 0; $i < count($domains) - 1; $i++) {
            $sheet->insertNewRowBefore(10, 1);
        }
        // 填写表格内容
        $total_revenue   = 0;
        $total_cut       = 0;
        $total_rate      = 0;
        $total_exchange  = 0;
        $first_row_index = 9;
        foreach ($domains as $index => $domain) {
            // 收入
            $revenue          = Utc::where('sub_channel', $domain)
                ->whereMonth('a_date', $month)
                ->where('app_id', 'in', $app_ids)
                ->sum('ad_revenue', 0);
            $cut_revenue      = $revenue * (100 - $cut[$index]) / 100;
            $rate_revenue     = $cut_revenue * $rate[$index] / 100;
            $exchange_revenue = $exchange[$index] > 1 ? $rate_revenue * $exchange[$index] : $rate_revenue;

            $total_revenue  += $revenue;
            $total_cut      += $cut_revenue;
            $total_rate     += $rate_revenue;
            $total_exchange += $exchange_revenue;

            $revenue          = sprintf("%.2f", $revenue);
            $cut_revenue      = sprintf("%.2f", $cut_revenue);
            $rate_revenue     = sprintf("%.2f", $rate_revenue);
            $exchange_revenue = sprintf("%.2f", $exchange_revenue);

            $sheet->setCellValue('A' . ($first_row_index + $index), $index + 1);
            $sheet->setCellValue('B' . ($first_row_index + $index), $domain);
            $sheet->setCellValue('C' . ($first_row_index + $index), $month);
            $sheet->setCellValue('D' . ($first_row_index + $index), $revenue);
            $sheet->setCellValue('E' . ($first_row_index + $index), $cut[$index] . '%');
            $sheet->setCellValue('F' . ($first_row_index + $index), $cut_revenue);
            $sheet->setCellValue('G' . ($first_row_index + $index), $rate[$index] . '%');
            $sheet->setCellValue('H' . ($first_row_index + $index), $rate_revenue);
            $sheet->setCellValue('I' . ($first_row_index + $index), $exchange[$index]);
            $sheet->setCellValue('J' . ($first_row_index + $index), $exchange_revenue);
        }
        // 数据汇总
        $total_revenue  = sprintf("%.2f", $total_revenue);
        $total_cut      = sprintf("%.2f", $total_cut);
        $total_rate     = sprintf("%.2f", $total_rate);
        $total_exchange = sprintf("%.2f", $total_exchange);
        $sheet->setCellValue('D' . ($first_row_index + count($domains)), $total_revenue);
        $sheet->setCellValue('F' . ($first_row_index + count($domains)), $total_cut);
        $sheet->setCellValue('H' . ($first_row_index + count($domains)), $total_rate);
        $sheet->setCellValue('J' . ($first_row_index + count($domains)), $total_exchange);

        // 合计数据
        $writer = new Xlsx($spreadsheet);
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: think-lang, server, ba_user_token, ba-user-token, ba_token, ba-token, batoken, Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With');
        header('Access-Control-Max-Age: 1800');
        header('Access-Control-Expose-Headers:Content-Disposition');
        header('Content-Disposition: attachment;filename=' . $month . '@' . urlencode($user->nickname) . ".xlsx");
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
    }

    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->excludeFields($data);
            if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                $data[$this->dataLimitField] = $this->auth->id;
            }

            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('add');
                        $validate->check($data);
                    }
                }

                $flag = $this->model->where('domain', $data['domain'])->find();
                if ($flag) throw new Exception('域名重复添加');

                $channel = Channel::where('id', $data['channel_id'])->find();
                if (!$channel) throw new Exception('通道不存在');

                $data['channel'] = $channel->channel_type;
                $data['flag']    = $channel->channel_account;

                $data['main_domain'] = implode('.', array_slice(explode('.', $data['domain']), -2));

                $result = $this->model->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Added successfully'));
            } else {
                $this->error(__('No rows were added'));
            }
        }

        $this->error(__('Parameter error'));
    }

    /**
     * 编辑
     * @throws Throwable
     */
    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $data[$pk] = $row[$pk];
                        $validate->check($data);
                    }
                }

                if (isset($data['channel_id'])) {
                    $channel = Channel::where('id', $data['channel_id'])->find();
                    if (!$channel) $this->error('通道不存在');
                    $data['channel'] = $channel->channel_type;
                    $data['flag']    = $channel->channel_account;
                }

                $result = $row->save($data);

                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        $this->success('', [
            'row' => $row
        ]);
    }

    public function append(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model->alias($alias)->whereNull('app_id')->order('domain.id', 'desc')->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => '',
        ]);
    }
}