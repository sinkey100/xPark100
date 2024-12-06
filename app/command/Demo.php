<?php

namespace app\command;

use app\admin\model\google\Account;
use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\XparkAdSense;
use app\command\Base;
use Google\AdsApi\AdManager\AdManagerServices;
use Google\AdsApi\AdManager\Util\v202308\StatementBuilder;
use Google\AdsApi\AdManager\v202308\ReportJobStatus;
use Google\Service\Adsense as GoogleAdSense;
use Google\Service\PeopleService;
use sdk\Google as GoogleSDK;
use think\console\Input;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use Exception;


use Google\AdsApi\AdManager\v202405\ServiceFactory;
use Google\AdsApi\AdManager\v202405\ReportJob;
use Google\AdsApi\AdManager\v202405\ReportQuery;
use Google\AdsApi\AdManager\v202405\DateRangeType;
use Google\AdsApi\AdManager\v202405\Column;
use Google\AdsApi\AdManager\v202405\ExportFormat;
use Google\AdsApi\AdManager\v202405\ReportService;
use Google\AdsApi\AdManager\v202405\Date;
use Google\AdsApi\AdManager\Util\v202405\ReportDownloader;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\Common\OAuth2TokenBuilder;

class Demo extends Base
{

    protected function configure()
    {
        $this->setName('Demo');
    }

    protected function execute(Input $input, Output $output): void
    {
        print_r(array_intersect(["2","6",7], [1,2,3,4,5]));
        exit;


        $this->runReport();
    }

    protected function createSession()
    {
        $account       = Account::find(10);
        $account_token = json_decode($account['raw'], true);
        $account_json  = json_decode($account['json_text'], true)['web'];

        $oAuth2Credential = (new OAuth2TokenBuilder())
            ->withClientId($account_json['client_id'])
            ->withClientSecret($account_json['client_secret'])
            ->withRefreshToken($account_token['refresh_token'])
            ->build();

        $session = (new AdManagerSessionBuilder())
            ->withNetworkCode('23203902381')
            ->withApplicationName('CYADX')
            ->withOAuth2Credential($oAuth2Credential)
            ->build();


        return $session;
    }

    protected function runReport(): void
    {
        $session     = $this->createSession();
        $reportQuery = new ReportQuery();

        $endDate = new Date();
        $endDate->setYear(date('Y'));
        $endDate->setMonth(date('m'));
        $endDate->setDay(date('d'));
        $startDate = new Date();
        $startDate->setYear(date('Y', strtotime('-2 days')));
        $startDate->setMonth(date('m', strtotime('-2 days')));
        $startDate->setDay(date('d', strtotime('-2 days')));

        $reportQuery->setStartDate($startDate);
        $reportQuery->setEndDate($endDate);

        $reportQuery->setTimeZoneType('ASIA_HONG_KONG'); // 设置时区为+8时区
        $reportQuery->setReportCurrency('USD'); // 设置货币为美元

        $reportQuery->setDimensions([
            'DATE', 'COUNTRY_CODE', 'SITE_NAME'
        ]);
        $reportQuery->setColumns([
            'AD_EXCHANGE_ACTIVE_VIEW_VIEWABLE_IMPRESSIONS', // 展示
            'AD_EXCHANGE_LINE_ITEM_LEVEL_CLICKS',   // 点击
            'AD_EXCHANGE_LINE_ITEM_LEVEL_CTR',  // 点击率
            'AD_EXCHANGE_LINE_ITEM_LEVEL_REVENUE',  // 收入
            'AD_EXCHANGE_LINE_ITEM_LEVEL_AVERAGE_ECPM',  // eCPM
            'AD_EXCHANGE_TOTAL_REQUESTS',  // 请求
            'AD_EXCHANGE_MATCH_RATE',  // 填充
            'AD_EXCHANGE_COST_PER_CLICK',  // 单价
        ]);

//        // 设置筛选条件（过滤条件）
//        $statementBuilder = (new StatementBuilder())
//            ->where('SITE_NAME IN (:siteA, :siteB)')
//            ->withBindVariableValue('siteA', 'iosballpuzzle.cocogames.cc')
//            ->withBindVariableValue('siteB', 'iflowermatch.cocogames.cc');
//        $reportQuery->setStatement($statementBuilder->toStatement());

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
        if ($status == ReportJobStatus::COMPLETED) {
            $reportDownloader = new ReportDownloader($reportService, $reportJob->getId());
            $filePath         = './2.csv.gz';
            $reportDownloader->downloadReport(ExportFormat::CSV_DUMP, $filePath);

            $handle = gzopen($filePath, 'r');
            if (!$handle) throw new Exception('打开报告失败');
            $content = stream_get_contents($handle);
            [$fields, $csvData] = $this->csv2json($content);
            $fields = [
                'Column.AD_EXCHANGE_LINE_ITEM_LEVEL_REVENUE',
                'Column.AD_EXCHANGE_LINE_ITEM_LEVEL_AVERAGE_ECPM',
                'Column.AD_EXCHANGE_COST_PER_CLICK'
            ];
            foreach ($csvData as &$v){
                foreach($fields as $field){
                    $v[$field] = $v[$field] / 1000000;
                }
            }
            echo json_encode($csvData);
        }
    }


}
