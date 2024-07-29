<?php

namespace app\admin\controller\examples;

use Throwable;
use app\common\controller\Backend;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 导出测试管理
 */
class Export extends Backend
{
    /**
     * Export模型对象
     * @var object
     * @phpstan-var \app\admin\model\examples\Export
     */
    protected object $model;

    protected string|array $quickSearchField = ['id'];

    protected string|array $defaultSortField = 'weigh,desc';

    protected string|array $preExcludeFields = ['create_time', 'update_time'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\examples\Export;
    }

    /**
     * 导出
     * @return void
     * @throws Throwable
     */
    public function export(): void
    {
        $list = $this->model->select()->toArray();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setCellValue([1, 1], 'ID');
        $sheet->setCellValue([2, 1], '名字');
        $sheet->setCellValue([3, 1], '性别');
        $sheet->setCellValue([4, 1], '日期');

        $h = 2;
        foreach ($list as $v) {
            $sheet->setCellValue([1, $h], $v['id']);
            $sheet->setCellValue([2, $h], $v['name']);
            $sheet->setCellValue([3, $h], $v['status'] == 1 ? '男' : '女');
            $sheet->setCellValue([4, $h], date('Y-m-d H:i:s', $v['create_time']));
            $h++;
        }

        $writer = new Xlsx($spreadsheet);
        $file   = time() . '.xlsx';
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Access-Control-Expose-Headers:Content-Disposition');
        header('Content-Disposition: attachment;filename=' . $file);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
    }

}