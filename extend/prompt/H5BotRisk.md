# 常用封装方法

# 数据表定义

## 广告收入数据表
对应的模型是 \app\admin\model\xpark\Utc
```
-- auto-generated definition
create table ba_xpark_utc
(
    id              bigint auto_increment
        primary key,
    domain_id       bigint               default 0  not null comment '域名',
    channel         varchar charset utf8 default '' null,
    channel_full    varchar charset utf8 default '' null,
    a_date          datetime                        null comment '日期',
    country_code    varchar charset utf8 default '' null comment '地区',
    country_level   varchar charset utf8 default '' null,
    country_name    varchar charset utf8 default '' null,
    sub_channel     varchar charset utf8 default '' null comment '域名',
    ad_placement_id varchar charset utf8 default '' null comment '广告位',
    ad_unit_type    varchar charset utf8 default '' null comment '广告类型',
    requests        int                             null comment '请求数',
    fills           int                             null comment '填充数',
    impressions     int                  default 0  not null comment '展示',
    clicks          int                             null comment '点击数',
    ad_revenue      float                           null comment '收入',
    status          tinyint              default 0  null comment '0=done 1=ready',
    channel_type    tinyint              default 0  null comment '0=H5 1=APP',
    app_id          bigint                          null,
    channel_id      bigint                          null,
)
    comment 'xPark数据' engine = InnoDB
                        collate = utf8_bin;

```

# 需求概述
我想做一个飞书机器人通知，针对某些域名的广告收入报表指标做飞书机器人风控报警通知。  
这个脚本通过在php的cli脚本运行，代码需要写在 app\command\Bot\H5Risk.php  

## 相关字段如下：  
- sub_channel 域名
- channel_full 通道名
- ad_unit_type 广告类型
- clicks 点击数，impressions 填充数，requests 请求数
- 点击率：点击÷展示的百分比
- 填充率：填充÷请求的百分比
- eCPM: 收入÷展示*1000
- a_date 日期

## 机器人报警的表格如下面的形式 :

| 域名 | 地区 | 广告类型 | 填充率 | 点击率 | eCPM |
| -- | -- | -- | -- | -- | -- |
| sub_channel | country_code | ad_unit_type | 80（↑3） | 5% | 6.12  |

## 数据维度筛选
- 需要筛选app_id=29
- 广告类型只筛选 ads_interstitial、ads_anchor、banner 三种  
- 只针对当天的数据做筛选检查
- 使用Group by查询再sum指标，可以节省性能

## 数据指标筛选
- 查询时过滤收入小于1的数据
- 点击率，超出下面的这个范围上报 当低于风控值时显示蓝色，高于风控值时显示红色
- banner点击率风控值：4% < banner < 8%
- ads_anchor点击率风控值：20% < ads_anchor < 30%
- ads_interstitial点击率风控值：20% < ads_interstitial < 50%
- 点击率高于风控值显示红色，低于风控值显示蓝色
- 填充率指标不分广告类型，只要低于70%，就报警  
- 同一个域名的填充率和点击率，只要有一个触发指标，就在表格中显示一行，另一个没有触发指标，仅需要显示指标值即可
- 填充率需要从昨天开始连续往前检查最多7天，需在百分比后显示持续上升或下降趋势的天数，比如连续三天下降显示为（↓3），只需要检查持续下降或者持续上升，比如往前两天都是下降，往前的第三天是上升，只需要显示（↓2），该数据就不需要继续往前检查了

## 飞书机器人通知
- 在 `/extend/sdk/FeishuBot.php` ，有一个appMsg()方法，命名空间是： sdk\FeishuBot ，可以发送飞书机器人通知，通知的是一个表格形式。  
- 这个方法只需要传第一个参数，为表格结构的数组对象。
- 飞书表格的数据结构可以参考`/app/command/Bot/Hb.php`的`sendAlert()`方法
