<?php
// 应用公共文件

use think\App;
use ba\Filesystem;
use think\Response;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Event;
use think\facade\Config;
use voku\helper\AntiXSS;
use app\admin\model\Config as configModel;
use think\exception\HttpResponseException;
use Symfony\Component\HttpFoundation\IpUtils;

if (!function_exists('__')) {

    /**
     * 语言翻译
     * @param string $name 被翻译字符
     * @param array $vars 替换字符数组
     * @param string $lang 翻译语言
     * @return mixed
     */
    function __(string $name, array $vars = [], string $lang = ''): mixed
    {
        if (is_numeric($name) || !$name) {
            return $name;
        }
        return Lang::get($name, $vars, $lang);
    }
}

if (!function_exists('filter')) {

    /**
     * 输入过滤
     * 富文本反XSS请使用 clean_xss，也就不需要及不能再 filter 了
     * @param string $string 要过滤的字符串
     * @return string
     */
    function filter(string $string): string
    {
        // 去除字符串两端空格（对防代码注入有一定作用）
        $string = trim($string);

        // 过滤html和php标签
        $string = strip_tags($string);

        // 特殊字符转实体
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
    }
}

if (!function_exists('clean_xss')) {

    /**
     * 清理XSS
     * 通常只用于富文本，比 filter 慢
     * @param string $string
     * @return string
     */
    function clean_xss(string $string): string
    {
        return (new AntiXSS())->xss_clean($string);
    }
}

if (!function_exists('htmlspecialchars_decode_improve')) {
    /**
     * html解码增强
     * 被 filter函数 内的 htmlspecialchars 编码的字符串，需要用此函数才能完全解码
     * @param string $string
     * @param int $flags
     * @return string
     */
    function htmlspecialchars_decode_improve(string $string, int $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401): string
    {
        return htmlspecialchars_decode($string, $flags);
    }
}

if (!function_exists('get_sys_config')) {

    /**
     * 获取站点的系统配置，不传递参数则获取所有配置项
     * @param string $name 变量名
     * @param string $group 变量分组，传递此参数来获取某个分组的所有配置项
     * @param bool $concise 是否开启简洁模式，简洁模式下，获取多项配置时只返回配置的键值对
     * @return mixed
     * @throws Throwable
     */
    function get_sys_config(string $name = '', string $group = '', bool $concise = true): mixed
    {
        if ($name) {
            // 直接使用->value('value')不能使用到模型的类型格式化
            $config = configModel::cache($name, null, configModel::$cacheTag)->where('name', $name)->find();
            if ($config) $config = $config['value'];
        } else {
            if ($group) {
                $temp = configModel::cache('group' . $group, null, configModel::$cacheTag)->where('group', $group)->select()->toArray();
            } else {
                $temp = configModel::cache('sys_config_all', null, configModel::$cacheTag)->order('weigh desc')->select()->toArray();
            }
            if ($concise) {
                $config = [];
                foreach ($temp as $item) {
                    $config[$item['name']] = $item['value'];
                }
            } else {
                $config = $temp;
            }
        }
        return $config;
    }
}

if (!function_exists('get_route_remark')) {

    /**
     * 获取当前路由后台菜单规则的备注信息
     * @return string
     */
    function get_route_remark(): string
    {
        $controllerName = request()->controller(true);
        $actionName     = request()->action(true);
        $path           = str_replace('.', '/', $controllerName);

        $remark = Db::name('admin_rule')
            ->where('name', $path)
            ->whereOr('name', $path . '/' . $actionName)
            ->value('remark');
        return __((string)$remark);
    }
}

if (!function_exists('full_url')) {

    /**
     * 获取资源完整url地址；若安装了云存储或 config/buildadmin.php 配置了CdnUrl，则自动使用对应的CdnUrl
     * @param string $relativeUrl 资源相对地址 不传入则获取域名
     * @param string|bool $domain 是否携带域名 或者直接传入域名
     * @param string $default 默认值
     * @return string
     */
    function full_url(string $relativeUrl = '', string|bool $domain = true, string $default = ''): string
    {
        // 存储/上传资料配置
        Event::trigger('uploadConfigInit', App::getInstance());

        $cdnUrl = Config::get('buildadmin.cdn_url');
        if (!$cdnUrl) $cdnUrl = request()->upload['cdn'] ?? '//' . request()->host();
        if ($domain === true) {
            $domain = $cdnUrl;
        } elseif ($domain === false) {
            $domain = '';
        }

        $relativeUrl = $relativeUrl ?: $default;
        if (!$relativeUrl) return $domain;

        $regex = "/^((?:[a-z]+:)?\/\/|data:image\/)(.*)/i";
        if (preg_match('/^http(s)?:\/\//', $relativeUrl) || preg_match($regex, $relativeUrl) || $domain === false) {
            return $relativeUrl;
        }
        return $domain . $relativeUrl;
    }
}

if (!function_exists('encrypt_password')) {

    /**
     * 加密密码
     */
    function encrypt_password($password, $salt = '', $encrypt = 'md5')
    {
        return $encrypt($encrypt($password) . $salt);
    }
}

if (!function_exists('str_attr_to_array')) {

    /**
     * 将字符串属性列表转为数组
     * @param string $attr 属性，一行一个，无需引号，比如：class=input-class
     * @return array
     */
    function str_attr_to_array(string $attr): array
    {
        if (!$attr) return [];
        $attr     = explode("\n", trim(str_replace("\r\n", "\n", $attr)));
        $attrTemp = [];
        foreach ($attr as $item) {
            $item = explode('=', $item);
            if (isset($item[0]) && isset($item[1])) {
                $attrVal = $item[1];
                if ($item[1] === 'false' || $item[1] === 'true') {
                    $attrVal = !($item[1] === 'false');
                } elseif (is_numeric($item[1])) {
                    $attrVal = (float)$item[1];
                }
                if (strpos($item[0], '.')) {
                    $attrKey = explode('.', $item[0]);
                    if (isset($attrKey[0]) && isset($attrKey[1])) {
                        $attrTemp[$attrKey[0]][$attrKey[1]] = $attrVal;
                        continue;
                    }
                }
                $attrTemp[$item[0]] = $attrVal;
            }
        }
        return $attrTemp;
    }
}

if (!function_exists('action_in_arr')) {

    /**
     * 检测一个方法是否在传递的数组内
     * @param array $arr
     * @return bool
     */
    function action_in_arr(array $arr = []): bool
    {
        $arr = is_array($arr) ? $arr : explode(',', $arr);
        if (!$arr) {
            return false;
        }
        $arr = array_map('strtolower', $arr);
        if (in_array(strtolower(request()->action()), $arr) || in_array('*', $arr)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('build_suffix_svg')) {

    /**
     * 构建文件后缀的svg图片
     * @param string $suffix 文件后缀
     * @param ?string $background 背景颜色，如：rgb(255,255,255)
     * @return string
     */
    function build_suffix_svg(string $suffix = 'file', string $background = null): string
    {
        $suffix = mb_substr(strtoupper($suffix), 0, 4);
        $total  = unpack('L', hash('adler32', $suffix, true))[1];
        $hue    = $total % 360;
        [$r, $g, $b] = hsv2rgb($hue / 360, 0.3, 0.9);

        $background = $background ?: "rgb($r,$g,$b)";

        return '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve">
            <path style="fill:#E2E5E7;" d="M128,0c-17.6,0-32,14.4-32,32v448c0,17.6,14.4,32,32,32h320c17.6,0,32-14.4,32-32V128L352,0H128z"/>
            <path style="fill:#B0B7BD;" d="M384,128h96L352,0v96C352,113.6,366.4,128,384,128z"/>
            <polygon style="fill:#CAD1D8;" points="480,224 384,128 480,128 "/>
            <path style="fill:' . $background . ';" d="M416,416c0,8.8-7.2,16-16,16H48c-8.8,0-16-7.2-16-16V256c0-8.8,7.2-16,16-16h352c8.8,0,16,7.2,16,16 V416z"/>
            <path style="fill:#CAD1D8;" d="M400,432H96v16h304c8.8,0,16-7.2,16-16v-16C416,424.8,408.8,432,400,432z"/>
            <g><text><tspan x="220" y="380" font-size="124" font-family="Verdana, Helvetica, Arial, sans-serif" fill="white" text-anchor="middle">' . $suffix . '</tspan></text></g>
        </svg>';
    }
}

if (!function_exists('get_area')) {

    /**
     * 获取省份地区数据
     * @throws Throwable
     */
    function get_area(): array
    {
        $province = request()->get('province', '');
        $city     = request()->get('city', '');
        $where    = ['pid' => 0, 'level' => 1];
        if ($province !== '') {
            $where['pid']   = $province;
            $where['level'] = 2;
            if ($city !== '') {
                $where['pid']   = $city;
                $where['level'] = 3;
            }
        }
        return Db::name('area')
            ->where($where)
            ->field('id as value,name as label')
            ->select()
            ->toArray();
    }
}

if (!function_exists('hsv2rgb')) {
    function hsv2rgb($h, $s, $v): array
    {
        $r = $g = $b = 0;

        $i = floor($h * 6);
        $f = $h * 6 - $i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $t = $v * (1 - (1 - $f) * $s);

        switch ($i % 6) {
            case 0:
                $r = $v;
                $g = $t;
                $b = $p;
                break;
            case 1:
                $r = $q;
                $g = $v;
                $b = $p;
                break;
            case 2:
                $r = $p;
                $g = $v;
                $b = $t;
                break;
            case 3:
                $r = $p;
                $g = $q;
                $b = $v;
                break;
            case 4:
                $r = $t;
                $g = $p;
                $b = $v;
                break;
            case 5:
                $r = $v;
                $g = $p;
                $b = $q;
                break;
        }

        return [
            floor($r * 255),
            floor($g * 255),
            floor($b * 255)
        ];
    }
}

if (!function_exists('ip_check')) {

    /**
     * IP检查
     * @throws Throwable
     */
    function ip_check($ip = null): void
    {
        $ip       = is_null($ip) ? request()->ip() : $ip;
        $noAccess = get_sys_config('no_access_ip');
        $noAccess = !$noAccess ? [] : array_filter(explode("\n", str_replace("\r\n", "\n", $noAccess)));
        if ($noAccess && IpUtils::checkIp($ip, $noAccess)) {
            $response = Response::create(['msg' => 'No permission request'], 'json', 403);
            throw new HttpResponseException($response);
        }
    }
}

if (!function_exists('set_timezone')) {

    /**
     * 设置时区
     * @throws Throwable
     */
    function set_timezone($timezone = null): void
    {
        $defaultTimezone = Config::get('app.default_timezone');
        $timezone        = is_null($timezone) ? get_sys_config('time_zone') : $timezone;
        if ($timezone && $defaultTimezone != $timezone) {
            Config::set([
                'app.default_timezone' => $timezone
            ]);
            date_default_timezone_set($timezone);
        }
    }
}

if (!function_exists('get_upload_config')) {

    /**
     * 获取上传配置
     * @return array
     */
    function get_upload_config(): array
    {
        // 存储/上传资料配置
        Event::trigger('uploadConfigInit', App::getInstance());

        $uploadConfig            = Config::get('upload');
        $uploadConfig['maxsize'] = Filesystem::fileUnitToByte($uploadConfig['maxsize']);

        $upload = request()->upload;
        if (!$upload) {
            $uploadConfig['mode'] = 'local';
            return $uploadConfig;
        }
        unset($upload['cdn']);
        return array_merge($upload, $uploadConfig);
    }
}

if (!function_exists('get_auth_token')) {

    /**
     * 获取鉴权 token
     * @param array $names
     * @return string
     */
    function get_auth_token(array $names = ['ba', 'token']): string
    {
        $separators = [
            'header' => ['', '-'], // batoken、ba-token【ba_token 不在 header 的接受列表内因为兼容性不高，改用 http_ba_token】
            'param'  => ['', '-', '_'], // batoken、ba-token、ba_token
            'server' => ['_'], // http_ba_token
        ];

        $tokens  = [];
        $request = request();
        foreach ($separators as $fun => $sps) {
            foreach ($sps as $sp) {
                $tokens[] = $request->$fun(($fun == 'server' ? 'http_' : '') . implode($sp, $names));
            }
        }
        $tokens = array_filter($tokens);
        return array_values($tokens)[0] ?? '';
    }
}

if (!function_exists('get_country_data')) {
    function get_country_data(): array
    {
        return [
            ["code" => "US", "name" => "美国", "level" => "T1"],
            ["code" => "GB", "name" => "英国", "level" => "T1"],
            ["code" => "PH", "name" => "菲律宾", "level" => "T3"],
            ["code" => "CA", "name" => "加拿大", "level" => "T1"],
            ["code" => "BR", "name" => "巴西", "level" => "T3"],
            ["code" => "DE", "name" => "德国", "level" => "T1"],
            ["code" => "FR", "name" => "法国", "level" => "T1"],
            ["code" => "AU", "name" => "澳大利亚", "level" => "T1"],
            ["code" => "ID", "name" => "印尼", "level" => "T3"],
            ["code" => "RU", "name" => "俄罗斯", "level" => "T2"],
            ["code" => "PK", "name" => "巴基斯坦", "level" => "T3"],
            ["code" => "ZA", "name" => "南非", "level" => "T2"],
            ["code" => "AE", "name" => "阿联酋", "level" => "T3"],
            ["code" => "NZ", "name" => "新西兰", "level" => "T1"],
            ["code" => "IT", "name" => "意大利", "level" => "T2"],
            ["code" => "MY", "name" => "马来西亚", "level" => "T3"],
            ["code" => "SA", "name" => "沙特阿拉伯", "level" => "T2"],
            ["code" => "ES", "name" => "西班牙", "level" => "T2"],
            ["code" => "VN", "name" => "越南", "level" => "T3"],
            ["code" => "AT", "name" => "奥地利", "level" => "T1"],
            ["code" => "AF", "name" => "阿富汗", "level" => "T3"],
            ["code" => "AX", "name" => "奥兰", "level" => "T3"],
            ["code" => "AL", "name" => "阿尔巴尼亚", "level" => "T3"],
            ["code" => "DZ", "name" => "阿尔及利亚", "level" => "T3"],
            ["code" => "AS", "name" => "美属萨摩亚", "level" => "T3"],
            ["code" => "AD", "name" => "安道尔", "level" => "T3"],
            ["code" => "AO", "name" => "安哥拉", "level" => "T3"],
            ["code" => "AI", "name" => "安圭拉", "level" => "T3"],
            ["code" => "AQ", "name" => "南极洲", "level" => "T3"],
            ["code" => "AG", "name" => "安提瓜和巴布达", "level" => "T3"],
            ["code" => "AR", "name" => "阿根廷", "level" => "T3"],
            ["code" => "AM", "name" => "亚美尼亚", "level" => "T3"],
            ["code" => "AW", "name" => "阿鲁巴", "level" => "T3"],
            ["code" => "AZ", "name" => "阿塞拜疆", "level" => "T3"],
            ["code" => "BS", "name" => "巴哈马", "level" => "T3"],
            ["code" => "BH", "name" => "巴林", "level" => "T3"],
            ["code" => "BD", "name" => "孟加拉国", "level" => "T3"],
            ["code" => "BB", "name" => "巴巴多斯", "level" => "T3"],
            ["code" => "BY", "name" => "白俄罗斯", "level" => "T3"],
            ["code" => "BE", "name" => "比利时", "level" => "T2"],
            ["code" => "BZ", "name" => "伯利兹", "level" => "T3"],
            ["code" => "BJ", "name" => "贝宁", "level" => "T3"],
            ["code" => "BM", "name" => "百慕大", "level" => "T3"],
            ["code" => "BT", "name" => "不丹", "level" => "T3"],
            ["code" => "BO", "name" => "玻利维亚", "level" => "T3"],
            ["code" => "BQ", "name" => "荷兰加勒比区", "level" => "T3"],
            ["code" => "BA", "name" => "波斯尼亚和黑塞哥维那", "level" => "T3"],
            ["code" => "BW", "name" => "博茨瓦纳", "level" => "T3"],
            ["code" => "BV", "name" => "布韦岛", "level" => "T3"],
            ["code" => "IO", "name" => "英属印度洋领地", "level" => "T3"],
            ["code" => "BN", "name" => "文莱", "level" => "T3"],
            ["code" => "BG", "name" => "保加利亚", "level" => "T3"],
            ["code" => "BF", "name" => "布基纳法索", "level" => "T3"],
            ["code" => "BI", "name" => "布隆迪", "level" => "T3"],
            ["code" => "CV", "name" => "佛得角", "level" => "T3"],
            ["code" => "KH", "name" => "柬埔寨", "level" => "T3"],
            ["code" => "CM", "name" => "喀麦隆", "level" => "T3"],
            ["code" => "KY", "name" => "开曼群岛", "level" => "T3"],
            ["code" => "CF", "name" => "中非", "level" => "T3"],
            ["code" => "TD", "name" => "乍得", "level" => "T3"],
            ["code" => "CL", "name" => "智利", "level" => "T3"],
            ["code" => "CN", "name" => "中国", "level" => "T1"],
            ["code" => "CX", "name" => "圣诞岛", "level" => "T3"],
            ["code" => "CC", "name" => "科科斯（基林）群岛", "level" => "T3"],
            ["code" => "CO", "name" => "哥伦比亚", "level" => "T3"],
            ["code" => "KM", "name" => "科摩罗", "level" => "T3"],
            ["code" => "CG", "name" => "刚果（布）", "level" => "T3"],
            ["code" => "CD", "name" => "刚果（金）", "level" => "T3"],
            ["code" => "CK", "name" => "库克群岛", "level" => "T3"],
            ["code" => "CR", "name" => "哥斯达黎加", "level" => "T3"],
            ["code" => "CI", "name" => "科特迪瓦", "level" => "T3"],
            ["code" => "HR", "name" => "克罗地亚", "level" => "T3"],
            ["code" => "CU", "name" => "古巴", "level" => "T3"],
            ["code" => "CW", "name" => "库拉索", "level" => "T3"],
            ["code" => "CY", "name" => "塞浦路斯", "level" => "T3"],
            ["code" => "CZ", "name" => "捷克", "level" => "T3"],
            ["code" => "DK", "name" => "丹麦", "level" => "T1"],
            ["code" => "DJ", "name" => "吉布提", "level" => "T3"],
            ["code" => "DM", "name" => "多米尼克", "level" => "T3"],
            ["code" => "DO", "name" => "多米尼加", "level" => "T3"],
            ["code" => "EC", "name" => "厄瓜多尔", "level" => "T3"],
            ["code" => "EG", "name" => "埃及", "level" => "T3"],
            ["code" => "SV", "name" => "萨尔瓦多", "level" => "T3"],
            ["code" => "GQ", "name" => "赤道几内亚", "level" => "T3"],
            ["code" => "ER", "name" => "厄立特里亚", "level" => "T3"],
            ["code" => "EE", "name" => "爱沙尼亚", "level" => "T3"],
            ["code" => "SZ", "name" => "斯威士兰", "level" => "T3"],
            ["code" => "ET", "name" => "埃塞俄比亚", "level" => "T3"],
            ["code" => "FK", "name" => "福克兰群岛", "level" => "T3"],
            ["code" => "FO", "name" => "法罗群岛", "level" => "T3"],
            ["code" => "FJ", "name" => "斐济", "level" => "T3"],
            ["code" => "FI", "name" => "芬兰", "level" => "T2"],
            ["code" => "GF", "name" => "法属圭亚那", "level" => "T3"],
            ["code" => "PF", "name" => "法属波利尼西亚", "level" => "T3"],
            ["code" => "TF", "name" => "法属南方和南极洲领地", "level" => "T3"],
            ["code" => "GA", "name" => "加蓬", "level" => "T3"],
            ["code" => "GM", "name" => "冈比亚", "level" => "T3"],
            ["code" => "GE", "name" => "格鲁吉亚", "level" => "T3"],
            ["code" => "GH", "name" => "加纳", "level" => "T3"],
            ["code" => "GI", "name" => "直布罗陀", "level" => "T3"],
            ["code" => "GR", "name" => "希腊", "level" => "T3"],
            ["code" => "GL", "name" => "格陵兰", "level" => "T3"],
            ["code" => "GD", "name" => "格林纳达", "level" => "T3"],
            ["code" => "GP", "name" => "瓜德罗普", "level" => "T3"],
            ["code" => "GU", "name" => "关岛", "level" => "T3"],
            ["code" => "GT", "name" => "危地马拉", "level" => "T3"],
            ["code" => "GG", "name" => "根西", "level" => "T3"],
            ["code" => "GN", "name" => "几内亚", "level" => "T3"],
            ["code" => "GW", "name" => "几内亚比绍", "level" => "T3"],
            ["code" => "GY", "name" => "圭亚那", "level" => "T3"],
            ["code" => "HT", "name" => "海地", "level" => "T3"],
            ["code" => "HM", "name" => "赫德岛和麦克唐纳群岛", "level" => "T3"],
            ["code" => "VA", "name" => "梵蒂冈", "level" => "T3"],
            ["code" => "HN", "name" => "洪都拉斯", "level" => "T3"],
            ["code" => "HK", "name" => "香港", "level" => "T1"],
            ["code" => "HU", "name" => "匈牙利", "level" => "T3"],
            ["code" => "IS", "name" => "冰岛", "level" => "T3"],
            ["code" => "IN", "name" => "印度", "level" => "T3"],
            ["code" => "IR", "name" => "伊朗", "level" => "T3"],
            ["code" => "IQ", "name" => "伊拉克", "level" => "T3"],
            ["code" => "IE", "name" => "爱尔兰", "level" => "T3"],
            ["code" => "IM", "name" => "马恩岛", "level" => "T3"],
            ["code" => "IL", "name" => "以色列", "level" => "T3"],
            ["code" => "JM", "name" => "牙买加", "level" => "T3"],
            ["code" => "JP", "name" => "日本", "level" => "T1"],
            ["code" => "JE", "name" => "泽西", "level" => "T3"],
            ["code" => "JO", "name" => "约旦", "level" => "T3"],
            ["code" => "KZ", "name" => "哈萨克斯坦", "level" => "T3"],
            ["code" => "KE", "name" => "肯尼亚", "level" => "T3"],
            ["code" => "KI", "name" => "基里巴斯", "level" => "T3"],
            ["code" => "KP", "name" => "朝鲜", "level" => "T3"],
            ["code" => "KR", "name" => "韩国", "level" => "T2"],
            ["code" => "KW", "name" => "科威特", "level" => "T3"],
            ["code" => "KG", "name" => "吉尔吉斯斯坦", "level" => "T3"],
            ["code" => "LA", "name" => "老挝", "level" => "T3"],
            ["code" => "LV", "name" => "拉脱维亚", "level" => "T3"],
            ["code" => "LB", "name" => "黎巴嫩", "level" => "T3"],
            ["code" => "LS", "name" => "莱索托", "level" => "T3"],
            ["code" => "LR", "name" => "利比里亚", "level" => "T3"],
            ["code" => "LY", "name" => "利比亚", "level" => "T3"],
            ["code" => "LI", "name" => "列支敦士登", "level" => "T3"],
            ["code" => "LT", "name" => "立陶宛", "level" => "T3"],
            ["code" => "LU", "name" => "卢森堡", "level" => "T2"],
            ["code" => "MO", "name" => "澳门", "level" => "T3"],
            ["code" => "MG", "name" => "马达加斯加", "level" => "T3"],
            ["code" => "MW", "name" => "马拉维", "level" => "T3"],
            ["code" => "MV", "name" => "马尔代夫", "level" => "T3"],
            ["code" => "ML", "name" => "马里", "level" => "T3"],
            ["code" => "MT", "name" => "马耳他", "level" => "T3"],
            ["code" => "MH", "name" => "马绍尔群岛", "level" => "T3"],
            ["code" => "MQ", "name" => "马提尼克", "level" => "T3"],
            ["code" => "MR", "name" => "毛里塔尼亚", "level" => "T3"],
            ["code" => "MU", "name" => "毛里求斯", "level" => "T3"],
            ["code" => "YT", "name" => "马约特", "level" => "T3"],
            ["code" => "MX", "name" => "墨西哥", "level" => "T3"],
            ["code" => "FM", "name" => "密克罗尼西亚联邦", "level" => "T3"],
            ["code" => "MD", "name" => "摩尔多瓦", "level" => "T3"],
            ["code" => "MC", "name" => "摩纳哥", "level" => "T3"],
            ["code" => "MN", "name" => "蒙古国", "level" => "T3"],
            ["code" => "ME", "name" => "黑山", "level" => "T3"],
            ["code" => "MS", "name" => "蒙特塞拉特", "level" => "T3"],
            ["code" => "MA", "name" => "摩洛哥", "level" => "T3"],
            ["code" => "MZ", "name" => "莫桑比克", "level" => "T3"],
            ["code" => "MM", "name" => "缅甸", "level" => "T3"],
            ["code" => "NA", "name" => "纳米比亚", "level" => "T3"],
            ["code" => "NR", "name" => "瑙鲁", "level" => "T3"],
            ["code" => "NP", "name" => "尼泊尔", "level" => "T3"],
            ["code" => "NL", "name" => "荷兰", "level" => "T2"],
            ["code" => "NC", "name" => "新喀里多尼亚", "level" => "T3"],
            ["code" => "NI", "name" => "尼加拉瓜", "level" => "T3"],
            ["code" => "NE", "name" => "尼日尔", "level" => "T3"],
            ["code" => "NG", "name" => "尼日利亚", "level" => "T3"],
            ["code" => "NU", "name" => "纽埃", "level" => "T3"],
            ["code" => "NF", "name" => "诺福克岛", "level" => "T3"],
            ["code" => "MK", "name" => "北马其顿", "level" => "T3"],
            ["code" => "CH", "name" => "瑞士", "level" => "T1"],
            ["code" => "EH", "name" => "西撒哈拉", "level" => "T3"],
            ["code" => "KN", "name" => "圣基茨和尼维斯", "level" => "T3"],
            ["code" => "LC", "name" => "圣卢西亚", "level" => "T3"],
            ["code" => "LK", "name" => "斯里兰卡", "level" => "T3"],
            ["code" => "MF", "name" => "法属圣马丁", "level" => "T3"],
            ["code" => "NO", "name" => "挪威", "level" => "T1"],
            ["code" => "OM", "name" => "阿曼", "level" => "T2"],
            ["code" => "PA", "name" => "巴拿马", "level" => "T2"],
            ["code" => "PE", "name" => "秘鲁", "level" => "T2"],
            ["code" => "PG", "name" => "巴布亚新几内亚", "level" => "T3"],
            ["code" => "PL", "name" => "波兰", "level" => "T1"],
            ["code" => "PM", "name" => "圣皮埃尔和密克隆", "level" => "T3"],
            ["code" => "PR", "name" => "波多黎各", "level" => "T3"],
            ["code" => "PS", "name" => "巴勒斯坦", "level" => "T3"],
            ["code" => "PT", "name" => "葡萄牙", "level" => "T1"],
            ["code" => "PW", "name" => "帕劳", "level" => "T3"],
            ["code" => "PY", "name" => "巴拉圭", "level" => "T3"],
            ["code" => "QA", "name" => "卡塔尔", "level" => "T2"],
            ["code" => "RE", "name" => "留尼汪", "level" => "T3"],
            ["code" => "RO", "name" => "罗马尼亚", "level" => "T2"],
            ["code" => "RS", "name" => "塞尔维亚", "level" => "T2"],
            ["code" => "RW", "name" => "卢旺达", "level" => "T3"],
            ["code" => "SB", "name" => "所罗门群岛", "level" => "T3"],
            ["code" => "SC", "name" => "塞舌尔", "level" => "T3"],
            ["code" => "SD", "name" => "苏丹", "level" => "T3"],
            ["code" => "SE", "name" => "瑞典", "level" => "T1"],
            ["code" => "SG", "name" => "新加坡", "level" => "T1"],
            ["code" => "SI", "name" => "斯洛文尼亚", "level" => "T2"],
            ["code" => "SK", "name" => "斯洛伐克", "level" => "T2"],
            ["code" => "SL", "name" => "塞拉利昂", "level" => "T3"],
            ["code" => "SN", "name" => "塞内加尔", "level" => "T3"],
            ["code" => "SO", "name" => "索马里", "level" => "T3"],
            ["code" => "SR", "name" => "苏里南", "level" => "T3"],
            ["code" => "SS", "name" => "南苏丹", "level" => "T3"],
            ["code" => "ST", "name" => "圣多美和普林西比", "level" => "T3"],
            ["code" => "SX", "name" => "荷属圣马丁", "level" => "T3"],
            ["code" => "SY", "name" => "叙利亚", "level" => "T3"],
            ["code" => "TC", "name" => "特克斯和凯科斯群岛", "level" => "T3"],
            ["code" => "TG", "name" => "多哥", "level" => "T3"],
            ["code" => "TH", "name" => "泰国", "level" => "T2"],
            ["code" => "TJ", "name" => "塔吉克斯坦", "level" => "T3"],
            ["code" => "TL", "name" => "东帝汶", "level" => "T3"],
            ["code" => "TM", "name" => "土库曼斯坦", "level" => "T3"],
            ["code" => "TN", "name" => "突尼斯", "level" => "T2"],
            ["code" => "TR", "name" => "土耳其", "level" => "T2"],
            ["code" => "TT", "name" => "特立尼达和多巴哥", "level" => "T3"],
            ["code" => "TW", "name" => "台湾", "level" => "T1"],
            ["code" => "TZ", "name" => "坦桑尼亚", "level" => "T3"],
            ["code" => "UA", "name" => "乌克兰", "level" => "T2"],
            ["code" => "UG", "name" => "乌干达", "level" => "T3"],
            ["code" => "UY", "name" => "乌拉圭", "level" => "T2"],
            ["code" => "UZ", "name" => "乌兹别克斯坦", "level" => "T3"],
            ["code" => "VC", "name" => "圣文森特和格林纳丁斯", "level" => "T3"],
            ["code" => "VE", "name" => "委内瑞拉", "level" => "T3"],
            ["code" => "VG", "name" => "英属维尔京群岛", "level" => "T3"],
            ["code" => "VI", "name" => "美属维尔京群岛", "level" => "T3"],
            ["code" => "VU", "name" => "瓦努阿图", "level" => "T3"],
            ["code" => "WS", "name" => "萨摩亚", "level" => "T3"],
            ["code" => "XO", "name" => "科索沃", "level" => "T3"],
            ["code" => "YE", "name" => "也门", "level" => "T3"],
            ["code" => "ZM", "name" => "赞比亚", "level" => "T3"],
            ["code" => "ZW", "name" => "津巴布韦", "level" => "T3"]
        ];
    }
}

if (!function_exists('find_row_from_keyword')) {
    /**
     * 从一段文本中，根据字符串返回该行内容
     * @param string $content
     * @param string $keyword
     * @return string
     */
    function find_row_from_keyword(string $content, string $keyword): string
    {
        $rows    = explode("\n", $content);
        $message = '';
        foreach ($rows as $row) {
            if (str_contains($row, $keyword)) {
                $message = $row;
                break;
            }
        }
        return $message;
    }
}