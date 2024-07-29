<?php

namespace app\command;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\console\Command;
use think\facade\Env;

class Base extends Command
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function http(string $method, string $url, array $options = []): array
    {
        $client = new Client([
            'verify'    => false
        ]);
        $result = $client->request($method, $url, $options);
        if ($result->getStatusCode() != 200){
            throw new Exception('请求失败');
        }
        return json_decode($result->getBody()->getContents(), true);
    }


    protected function csv_to_json($csv_string): array
    {
        // 将CSV字符串按行分割
        $lines = explode("\n", $csv_string);

        // 使用第一行作为标题
        $header = str_getcsv(array_shift($lines));

        // 读取剩余行并转换为关联数组
        $data = [];
        foreach ($lines as $line) {
            if (trim($line) == '') {
                continue;
            }
            $row    = str_getcsv($line);
            $data[] = array_combine($header, $row);
        }

        // 将数据转换为JSON对象
        // $json_data = json_encode($data, JSON_PRETTY_PRINT);

        return [$header, $data];
    }

}
