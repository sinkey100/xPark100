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
    public function getLogsWithPowerSql(int $from, int $to, string $query): array
    {
        $request = new Aliyun_Log_Models_LogStoreSqlRequest(
            $this->project, $this->logstore,
            $from, $to, '', $query, true
        );

        try {
            $response = $this->client->executeLogStoreSql($request);

            // [71] => Aliyun_Log_Models_QueriedLog Object
            //        (
            //            [time:Aliyun_Log_Models_QueriedLog:private] => 1734855240
            //            [source:Aliyun_Log_Models_QueriedLog:private] =>
            //            [contents:Aliyun_Log_Models_QueriedLog:private] => Array
            //                (
            //                    [attribute.country_id] => US
            //                    [hour] => 2024-12-24 15:00:00
            //                    [_col2] => 84
            //                )
            //
            //        )
            return $response->getLogs();

        } catch (Aliyun_Log_Exception|Exception $ex) {
            throw new Exception($ex->getMessage());
        }

    }


}