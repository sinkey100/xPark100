<?php

namespace sdk;

use Exception;
use Aliyun_Log_Exception;
use think\facade\Env;
use Aliyun_Log_Client;
use Aliyun_Log_Models_LogStoreSqlRequest;

class SLS
{

    protected Aliyun_Log_Client $client;
    protected string            $endpoint;
    protected string            $accessKeyId;
    protected string            $accessKey;
    protected string            $project;
    protected string            $logstore;

    public function __construct()
    {
        $this->endpoint    = Env::get('ALIYUN.SLS_HOST');
        $this->accessKeyId = Env::get('ALIYUN.ACCESS_KEY_ID');
        $this->accessKey   = Env::get('ALIYUN.ACCESS_KEY');
        $this->project     = Env::get('ALIYUN.SLS_PROJECT');
        $this->logstore    = Env::get('ALIYUN.SLS_LOGSTORE_PROCESSED');

        $this->client = new Aliyun_Log_Client($this->endpoint, $this->accessKeyId, $this->accessKey, '');
    }

    /**
     * @throws Exception
     */
//    public function getLogsWithPowerSql(int $from, int $to, string $query): array
//    {
//        $request = new Aliyun_Log_Models_LogStoreSqlRequest(
//            $this->project, $this->logstore,
//            $from, $to, '', $query, true
//        );
//
//        try {
//            $response = $this->client->executeLogStoreSql($request);
//
//            // [71] => Aliyun_Log_Models_QueriedLog Object
//            //        (
//            //            [time:Aliyun_Log_Models_QueriedLog:private] => 1734855240
//            //            [source:Aliyun_Log_Models_QueriedLog:private] =>
//            //            [contents:Aliyun_Log_Models_QueriedLog:private] => Array
//            //                (
//            //                    [attribute.country_id] => US
//            //                    [hour] => 2024-12-24 15:00:00
//            //                    [_col2] => 84
//            //                )
//            //
//            //        )
//            return $response->getLogs();
//
//        } catch (Aliyun_Log_Exception|Exception $ex) {
//            throw new Exception($ex->getMessage());
//        }
//
//    }

    public function getLogsWithPowerSql(int $from, int $to, string $query, int $line = 500): array
    {
        $allLogs = []; // 用于存储所有数据
        $offset = 0;   // 起始偏移量

        while (true) {
            // 在查询语句中添加 LIMIT offset, line
            $pagedQuery = $query . " LIMIT $offset, $line";

            // 创建请求对象
            $request = new Aliyun_Log_Models_LogStoreSqlRequest(
                $this->project, $this->logstore,
                $from, $to, '', $pagedQuery, true
            );

            try {
                // 执行查询
                $response = $this->client->executeLogStoreSql($request);

                // 获取当前页的数据
                $logs = $response->getLogs();

                // 如果没有数据，说明已经读取完毕，跳出循环
                if (empty($logs)) {
                    break;
                }

                // 将当前页的数据合并到结果中
                $allLogs = array_merge($allLogs, $logs);

                // 更新 offset
                $offset += $line;

            } catch (Aliyun_Log_Exception|Exception $ex) {
                throw new Exception($ex->getMessage());
            }
        }

        return $allLogs; // 返回所有数据
    }


}