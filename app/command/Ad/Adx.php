<?php

namespace app\command\Ad;

use app\admin\model\google\Account;
use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\command\Base;
use Google\AdsApi\AdManager\AdManagerServices;
use Google\AdsApi\AdManager\AdManagerSession;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\Util\v202408\ReportDownloader;
use Google\AdsApi\AdManager\Util\v202408\StatementBuilder;
use Google\AdsApi\AdManager\v202408\ReportJobStatus;
use Google\AdsApi\AdManager\v202408\Date;
use Google\AdsApi\AdManager\v202408\ExportFormat;
use Google\AdsApi\AdManager\v202408\ReportJob;
use Google\AdsApi\AdManager\v202408\ReportQuery;
use Google\AdsApi\AdManager\v202408\ReportService;
use Google\AdsApi\AdManager\v202408\ServiceFactory;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\AdsApi\AdManager\v202408\Dimension;
use Google\AdsApi\AdManager\v202408\Column;
use think\console\Input;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use Exception;

class Adx extends Base
{

    protected string $temp_file;

    protected function configure()
    {
        $this->setName('Adx');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->temp_file = root_path() . 'runtime/cache/adx_report.csv.gz';
        $this->log("\n\n======== Adx 开始拉取数据 ========", false);
        $this->log("任务开始，拉取 {$this->days} 天");

        Data::where('channel', 'Adx')->where('status', 1)->delete();

        $this->log('开始拉取 Adx 数据');
        try {
            $this->pull();
        } catch (Exception $e) {
            $this->log("[{$e->getLine()}|{$e->getFile()}]{$e->getMessage()}");
            print_r($e->getTraceAsString());
            $this->log('======== Adx 拉取数据失败 ========', false);
            return;
        }

        for ($i = 0; $i < $this->days; $i++) {
            Data::where('channel', 'Adx')->where('status', 0)->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            Data::where('channel', 'Adx')->where('status', 1)->where('a_date', date("Y-m-d", strtotime("-$i days")))->update([
                'status' => 0
            ]);
        }
        $this->log('历史数据已删除');

        $this->log('======== Adx 拉取数据完成 ========', false);
    }

    protected function createSession(): AdManagerSession
    {
        $account       = Account::find(10);
        $account_token = json_decode($account['raw'], true);
        $account_json  = json_decode($account['json_text'], true)['web'];

        $oAuth2Credential = (new OAuth2TokenBuilder())
            ->withClientId($account_json['client_id'])
            ->withClientSecret($account_json['client_secret'])
            ->withRefreshToken($account_token['refresh_token'])
            ->build();

        return (new AdManagerSessionBuilder())
            ->withNetworkCode('23203902381')
            ->withApplicationName('CYADX')
            ->withOAuth2Credential($oAuth2Credential)
            ->build();
    }

    /**
     * @throws DataNotFoundException
     * @throws \Google\Exception
     * @throws \Google\Service\Exception
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    protected function pull(): void
    {
        // 获取账号和域名
        $adx_domains = Domain::where('channel', 'Adx')->select()->toArray();
        $adx_domains = array_column($adx_domains, 'domain');

        $days = $this->days - 1;

        // 拉数据
        $session = $this->createSession();

        // 获取广告ID对应关系
        $serviceFactory   = new ServiceFactory();
        $inventoryService = $serviceFactory->createInventoryService($session);
        $statementBuilder = (new StatementBuilder())->orderBy('id ASC');
        $page             = $inventoryService->getAdUnitsByStatement($statementBuilder->toStatement());
        $adUnits          = [];
        if ($page->getResults() !== null) {
            foreach ($page->getResults() as $adUnit) {
                $adUnits[$adUnit->getId()] = $adUnit->getAdUnitCode();
            }
        }


        $reportQuery = new ReportQuery();

        $endDate = new Date();
        $endDate->setYear(date('Y'));
        $endDate->setMonth(date('m'));
        $endDate->setDay(date('d'));
        $startDate = new Date();
        $startDate->setYear(date('Y', strtotime("-{$days} days")));
        $startDate->setMonth(date('m', strtotime("-{$days} days")));
        $startDate->setDay(date('d', strtotime("-{$days} days")));

        $reportQuery->setStartDate($startDate);
        $reportQuery->setEndDate($endDate);

        $reportQuery->setTimeZoneType('ASIA_HONG_KONG'); // 设置时区为+8时区
        $reportQuery->setReportCurrency('USD'); // 设置货币为美元

        $reportQuery->setDimensions([
            Dimension::DATE,
            Dimension::COUNTRY_CODE,
            Dimension::PARENT_AD_UNIT_ID,
            Dimension::SITE_NAME,
        ]);
        $reportQuery->setColumns([
            Column::AD_EXCHANGE_ACTIVE_VIEW_VIEWABLE_IMPRESSIONS, // 展示
            Column::AD_EXCHANGE_LINE_ITEM_LEVEL_CLICKS,   // 点击
            Column::AD_EXCHANGE_LINE_ITEM_LEVEL_CTR,  // 点击率
            Column::AD_EXCHANGE_LINE_ITEM_LEVEL_REVENUE,  // 收入
            Column::AD_EXCHANGE_LINE_ITEM_LEVEL_AVERAGE_ECPM,  // eCPM
            Column::AD_EXCHANGE_TOTAL_REQUESTS,  // 请求
            Column::AD_EXCHANGE_MATCH_RATE,  // 填充
            Column::AD_EXCHANGE_COST_PER_CLICK  // 单价
        ]);

        $reportJob = new ReportJob();
        $reportJob->setReportQuery($reportQuery);
        // 提交报告
        $reportService = (new AdManagerServices())->get($session, ReportService::class);
        $reportJob     = $reportService->runReportJob($reportJob);

        do {
            sleep(10);
            $status = $reportService->getReportJobStatus($reportJob->getId());
        } while ($status == ReportJobStatus::IN_PROGRESS);

        // 4. 下载报告
        $saveData = [];
        if ($status == ReportJobStatus::COMPLETED) {
            $reportDownloader = new ReportDownloader($reportService, $reportJob->getId());
            $reportDownloader->downloadReport(ExportFormat::CSV_DUMP, $this->temp_file);

            $handle = gzopen($this->temp_file, 'r');
            if (!$handle) throw new Exception('打开报告失败');
            $content = stream_get_contents($handle);
            [$fields, $csvData] = csv2json($content);

            $amountFields = [
                'Column.AD_EXCHANGE_LINE_ITEM_LEVEL_REVENUE',
                'Column.AD_EXCHANGE_LINE_ITEM_LEVEL_AVERAGE_ECPM',
                'Column.AD_EXCHANGE_COST_PER_CLICK'
            ];

            foreach ($csvData as &$v) {
                if (!in_array($v['Dimension.SITE_NAME'], $adx_domains)) continue;

                foreach ($amountFields as $field) {
                    $v[$field] = $v[$field] / 1000000;
                }

                [$domain_id, $app_id] = $this->getDomainRow($v['Dimension.SITE_NAME'], $v['Dimension.DATE'], 'Adx');
                $channel_full = 'Adx-传游';

                $ad_path    = explode(',', $v['Dimension.PARENT_AD_UNIT_ID']);
                $ad_path    = array_values(array_filter($ad_path));
                $ad_unit_id = $ad_path[count($ad_path) - 1] ?? null;

                $saveData[] = [
                    'channel'         => 'Adx',
                    'channel_full'    => $channel_full,
                    'channel_id'      => $this->channelList[$channel_full]['id'] ?? 0,
                    'channel_type'    => ($this->channelList[$channel_full]['ad_type'] ?? 'H5') == 'H5' ? 0 : 1,
                    'sub_channel'     => $v['Dimension.SITE_NAME'],
                    'domain_id'       => $domain_id,
                    'app_id'          => $app_id,
                    'a_date'          => $v['Dimension.DATE'],
                    'country_code'    => $v['Dimension.COUNTRY_CODE'],
                    'ad_placement_id' => $adUnits[$ad_unit_id] ?? $ad_unit_id,
                    'requests'        => $v['Column.AD_EXCHANGE_TOTAL_REQUESTS'],
                    'fills'           => $v['Column.AD_EXCHANGE_MATCH_RATE'] * $v['Column.AD_EXCHANGE_TOTAL_REQUESTS'],
                    'impressions'     => $v['Column.AD_EXCHANGE_ACTIVE_VIEW_VIEWABLE_IMPRESSIONS'],
                    'clicks'          => $v['Column.AD_EXCHANGE_LINE_ITEM_LEVEL_CLICKS'],
                    'ad_revenue'      => $v['Column.AD_EXCHANGE_LINE_ITEM_LEVEL_REVENUE'],
                    'gross_revenue'   => $v['Column.AD_EXCHANGE_LINE_ITEM_LEVEL_REVENUE'],
                    'net_revenue'     => $v['Column.AD_EXCHANGE_LINE_ITEM_LEVEL_REVENUE'],
                    'raw_cpc'         => $v['Column.AD_EXCHANGE_COST_PER_CLICK'],
                    'raw_ctr'         => $v['Column.AD_EXCHANGE_LINE_ITEM_LEVEL_CTR'],
                    'raw_ecpm'        => $v['Column.AD_EXCHANGE_LINE_ITEM_LEVEL_AVERAGE_ECPM']
                ];
            }

            $this->saveData($saveData);
        }

    }

}
