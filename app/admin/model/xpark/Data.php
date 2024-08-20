<?php

namespace app\admin\model\xpark;

use think\Model;

/**
 * Data
 */
class Data extends Model
{
    // 表名
    protected $name = 'xpark_data';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    protected $append = [
        'click_rate',
        'fill_rate',
        'unit_price',
        'ecpm',
    ];


    public function domain(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\xpark\Domain::class, 'domain_id', 'id');
    }

//    protected function getClickRateAttr($value, $data): string
//    {
//        // 点击率：  点击/展示
//        $rate = $data['clicks'] / (!empty($data['impressions']) ? $data['impressions'] : 1);
//        return number_format($rate * 100, 2) . '%';
//    }
//
//    protected function getFillRateAttr($value, $data): string
//    {
//        // 填充率：  展示/请求
//        $rate = $data['fills'] / (!empty($data['requests']) ? $data['requests'] : 1);
//        return number_format($rate * 100, 2) . '%';
//    }
//
//    protected function getUnitPriceAttr($value, $data): string
//    {
//        // 单价：  总收入/点击次数
//        return round($data['ad_revenue'] / (!empty($data['clicks']) ? $data['clicks'] : 1), 2);
//    }
//
//    protected function getEcpmAttr($value, $data): string
//    {
//        // ECPM = 收入/网页展示次数×1000
//        return round($data['ad_revenue'] / (!empty($data['impressions']) ? $data['impressions'] : 1) * 1000, 3);
//    }

    protected function _getCountryCodeAttr($value): string
    {
        if(!isset($value)) return '';
        $arr = [
            "AF"=> "🇦🇫",
            "AL"=> "🇦🇱",
            "DZ"=> "🇩🇿",
            "AS"=> "🇦🇸",
            "AD"=> "🇦🇩",
            "AO"=> "🇦🇴",
            "AI"=> "🇦🇮",
            "AQ"=> "🇦🇶",
            "AG"=> "🇦🇬",
            "AR"=> "🇦🇷",
            "AM"=> "🇦🇲",
            "AW"=> "🇦🇼",
            "AU"=> "🇦🇺",
            "AT"=> "🇦🇹",
            "AZ"=> "🇦🇿",
            "BS"=> "🇧🇸",
            "BH"=> "🇧🇭",
            "BD"=> "🇧🇩",
            "BB"=> "🇧🇧",
            "BY"=> "🇧🇾",
            "BE"=> "🇧🇪",
            "BZ"=> "🇧🇿",
            "BJ"=> "🇧🇯",
            "BM"=> "🇧🇲",
            "BT"=> "🇧🇹",
            "BO"=> "🇧🇴",
            "BA"=> "🇧🇦",
            "BW"=> "🇧🇼",
            "BR"=> "🇧🇷",
            "IO"=> "🇮🇴",
            "VG"=> "🇻🇬",
            "BN"=> "🇧🇳",
            "BG"=> "🇧🇬",
            "BF"=> "🇧🇫",
            "BI"=> "🇧🇮",
            "KH"=> "🇰🇭",
            "CM"=> "🇨🇲",
            "CA"=> "🇨🇦",
            "CV"=> "🇨🇻",
            "KY"=> "🇰🇾",
            "CF"=> "🇨🇫",
            "TD"=> "🇹🇩",
            "CL"=> "🇨🇱",
            "CN"=> "🇨🇳",
            "CX"=> "🇨🇽",
            "CC"=> "🇨🇨",
            "CO"=> "🇨🇴",
            "KM"=> "🇰🇲",
            "CD"=> "🇨🇩",
            "CG"=> "🇨🇬",
            "CK"=> "🇨🇰",
            "CR"=> "🇨🇷",
            "HR"=> "🇭🇷",
            "CU"=> "🇨🇺",
            "CW"=> "🇨🇼",
            "CY"=> "🇨🇾",
            "CZ"=> "🇨🇿",
            "DK"=> "🇩🇰",
            "DJ"=> "🇩🇯",
            "DM"=> "🇩🇲",
            "DO"=> "🇩🇴",
            "EC"=> "🇪🇨",
            "EG"=> "🇪🇬",
            "SV"=> "🇸🇻",
            "GQ"=> "🇬🇶",
            "ER"=> "🇪🇷",
            "EE"=> "🇪🇪",
            "SZ"=> "🇸🇿",
            "ET"=> "🇪🇹",
            "FK"=> "🇫🇰",
            "FO"=> "🇫🇴",
            "FJ"=> "🇫🇯",
            "FI"=> "🇫🇮",
            "FR"=> "🇫🇷",
            "GF"=> "🇬🇫",
            "PF"=> "🇵🇫",
            "GA"=> "🇬🇦",
            "GM"=> "🇬🇲",
            "GE"=> "🇬🇪",
            "DE"=> "🇩🇪",
            "GH"=> "🇬🇭",
            "GI"=> "🇬🇮",
            "GR"=> "🇬🇷",
            "GL"=> "🇬🇱",
            "GD"=> "🇬🇩",
            "GP"=> "🇬🇵",
            "GU"=> "🇬🇺",
            "GT"=> "🇬🇹",
            "GN"=> "🇬🇳",
            "GW"=> "🇬🇼",
            "GY"=> "🇬🇾",
            "HT"=> "🇭🇹",
            "HN"=> "🇭🇳",
            "HK"=> "🇭🇰",
            "HU"=> "🇭🇺",
            "IS"=> "🇮🇸",
            "IN"=> "🇮🇳",
            "ID"=> "🇮🇩",
            "IR"=> "🇮🇷",
            "IQ"=> "🇮🇶",
            "IE"=> "🇮🇪",
            "IM"=> "🇮🇲",
            "IL"=> "🇮🇱",
            "IT"=> "🇮🇹",
            "JM"=> "🇯🇲",
            "JP"=> "🇯🇵",
            "JO"=> "🇯🇴",
            "KZ"=> "🇰🇿",
            "KE"=> "🇰🇪",
            "KI"=> "🇰🇮",
            "KW"=> "🇰🇼",
            "KG"=> "🇰🇬",
            "LA"=> "🇱🇦",
            "LV"=> "🇱🇻",
            "LB"=> "🇱🇧",
            "LS"=> "🇱🇸",
            "LR"=> "🇱🇷",
            "LY"=> "🇱🇾",
            "LI"=> "🇱🇮",
            "LT"=> "🇱🇹",
            "LU"=> "🇱🇺",
            "MO"=> "🇲🇴",
            "MG"=> "🇲🇬",
            "MW"=> "🇲🇼",
            "MY"=> "🇲🇾",
            "MV"=> "🇲🇻",
            "ML"=> "🇲🇱",
            "MT"=> "🇲🇹",
            "MH"=> "🇲🇭",
            "MQ"=> "🇲🇶",
            "MR"=> "🇲🇷",
            "MU"=> "🇲🇺",
            "YT"=> "🇾🇹",
            "MX"=> "🇲🇽",
            "FM"=> "🇫🇲",
            "MD"=> "🇲🇩",
            "MC"=> "🇲🇨",
            "MN"=> "🇲🇳",
            "ME"=> "🇲🇪",
            "MS"=> "🇲🇸",
            "MA"=> "🇲🇦",
            "MZ"=> "🇲🇿",
            "MM"=> "🇲🇲",
            "NA"=> "🇳🇦",
            "NR"=> "🇳🇷",
            "NP"=> "🇳🇵",
            "NL"=> "🇳🇱",
            "NC"=> "🇳🇨",
            "NZ"=> "🇳🇿",
            "NI"=> "🇳🇮",
            "NE"=> "🇳🇪",
            "NG"=> "🇳🇬",
            "NU"=> "🇳🇺",
            "NF"=> "🇳🇫",
            "KP"=> "🇰🇵",
            "MP"=> "🇲🇵",
            "NO"=> "🇳🇴",
            "OM"=> "🇴🇲",
            "PK"=> "🇵🇰",
            "PW"=> "🇵🇼",
            "PS"=> "🇵🇸",
            "PA"=> "🇵🇦",
            "PG"=> "🇵🇬",
            "PY"=> "🇵🇾",
            "PE"=> "🇵🇪",
            "PH"=> "🇵🇭",
            "PL"=> "🇵🇱",
            "PT"=> "🇵🇹",
            "PR"=> "🇵🇷",
            "QA"=> "🇶🇦",
            "RO"=> "🇷🇴",
            "RU"=> "🇷🇺",
            "RW"=> "🇷🇼",
            "RE"=> "🇷🇪",
            "WS"=> "🇼🇸",
            "SM"=> "🇸🇲",
            "SA"=> "🇸🇦",
            "SN"=> "🇸🇳",
            "RS"=> "🇷🇸",
            "SC"=> "🇸🇨",
            "SL"=> "🇸🇱",
            "SG"=> "🇸🇬",
            "SX"=> "🇸🇽",
            "SK"=> "🇸🇰",
            "SI"=> "🇸🇮",
            "SB"=> "🇸🇧",
            "SO"=> "🇸🇴",
            "ZA"=> "🇿🇦",
            "KR"=> "🇰🇷",
            "SS"=> "🇸🇸",
            "ES"=> "🇪🇸",
            "LK"=> "🇱🇰",
            "BL"=> "🇧🇱",
            "SH"=> "🇸🇭",
            "KN"=> "🇰🇳",
            "LC"=> "🇱🇨",
            "MF"=> "🇲🇫",
            "PM"=> "🇵🇲",
            "VC"=> "🇻🇨",
            "SD"=> "🇸🇩",
            "SR"=> "🇸🇷",
            "SE"=> "🇸🇪",
            "CH"=> "🇨🇭",
            "SY"=> "🇸🇾",
            "ST"=> "🇸🇹",
            "TW"=> "🇹🇼",
            "TJ"=> "🇹🇯",
            "TZ"=> "🇹🇿",
            "TH"=> "🇹🇭",
            "TL"=> "🇹🇱",
            "TG"=> "🇹🇬",
            "TK"=> "🇹🇰",
            "TO"=> "🇹🇴",
            "TT"=> "🇹🇹",
            "TN"=> "🇹🇳",
            "TR"=> "🇹🇷",
            "TM"=> "🇹🇲",
            "TC"=> "🇹🇨",
            "TV"=> "🇹🇻",
            "UG"=> "🇺🇬",
            "UA"=> "🇺🇦",
            "AE"=> "🇦🇪",
            "GB"=> "🇬🇧",
            "US"=> "🇺🇸",
            "UY"=> "🇺🇾",
            "UZ"=> "🇺🇿",
            "VU"=> "🇻🇺",
            "VA"=> "🇻🇦",
            "VE"=> "🇻🇪",
            "VN"=> "🇻🇳",
            "WF"=> "🇼🇫",
            "EH"=> "🇪🇭",
            "YE"=> "🇾🇪",
            "ZM"=> "🇿🇲",
            "ZW"=> "🇿🇼"
        ];
        return (isset($arr[$value]) ? $arr[$value] . ' ' : '') . $value;
    }
}