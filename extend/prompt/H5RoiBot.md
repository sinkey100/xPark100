# 常用封装方法

## 飞书机器人通知
在 /extend/sdk/FeishuBot.php ，有一个appMsg()方法，可以发送飞书机器人通知，通知的是一个表格形式。  
这个方法只需要传第一个参数，为表格结构的数组对象，第二个参数可以不填。

# 数据表定义
## 域名表
对应的模型是 \app\admin\model\xpark\Domain
```
-- auto-generated definition
create table ba_xpark_domain
(
    id              bigint auto_increment comment 'ID'
        primary key,
    domain          varchar charset utf8                   null comment '域名',
    channel         varchar charset utf8      default ''   null comment '通道:AdSense/Adx',
    is_app          tinyint                   default 0    null,
    is_hide         tinyint                   default 1    null,
    flag            varchar charset utf8      default ''   null comment '标记',
    rate            decimal(5, 2)             default 1.00 null comment '分成比例',
    ga              varchar charset utf8      default ''   null,
    app_id          bigint                                 null,
    channel_id      bigint                                 null,
    admin_id        bigint                                 null,
    sls_switch      tinyint(1)                default 1    null,
    tag             varchar(500) charset utf8 default ''   null,
    create_time     datetime                               null,
    update_time     datetime                               null,
    status          tinyint(1)                default 1    null,
    is_show         tinyint(1)                default 0    null comment '0=隐藏层 1=显示层',
)
    comment '域名' engine = InnoDB
                   collate = utf8_bin;
```


## 投放支出数据表
对应的模型是 \app\admin\model\spend\Data
```
-- auto-generated definition
create table ba_spend_data
(
    id            bigint auto_increment
        primary key,
    channel_name  varchar charset utf8                    not null comment '通道',
    is_app        tinyint                  default 0      not null comment '类型:0=网站,1=App',
    date          date                                    not null comment '数据日期',
    country_code  varchar charset utf8                    not null comment '地区',
    country_level varchar charset utf8     default ''     null,
    country_name  varchar charset utf8     default ''     null,
    spend         float                    default 0.0    null comment '投放金额',
    clicks        bigint                   default 0      null comment '点击',
    impressions   bigint                   default 0      null comment '展示',
    install       bigint                   default 0      null comment '安装',
    starts        int                      default 0      null,
    campaign_name varchar charset utf8                    null comment 'Campaign',
    cpc           decimal(10, 4)           default 0.0000 null comment 'CPC',
    cpi           float                                   null,
    cpm           float                    default 0.0    null comment 'CPM',
    status        tinyint                  default 1      not null comment '状态',
    app_id        bigint                                  null,
    channel_id    bigint                                  null,
    domain_id     bigint                                  null,
    conversion    int                      default 0      null,
    campaign_id   varchar(50) charset utf8 default ''     null
)
    comment '投放表' engine = InnoDB
                     collate = utf8_bin;

```

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
我想做一个飞书机器人通知，针对投放支出和广告收入做ROI趋势通知
这个脚本通过在php的cli脚本运行，代码需要写在 app\command\Bot\H5Roi.php

## 相关字段如下：
- ba_spend_data 表，spend  支出, country_code 地区
- ba_xpark_domain表， domain 域名
- ba_xpark_utc表，ad_unit_type 广告类型
- ba_xpark_utc表，clicks 点击数，impressions 填充数，requests 请求数
- ROI， 收入/支出
- ba_xpark_utc表， a_date 日期

## 机器人报警的表格如下面的形式 :

| 链接 | 地区 | 消耗 | 收入 | ROI | 同比 |
| -- | -- | -- | -- | -- | -- |
| domain | country_code | spend| ad_revenue | 101%  | 30% (↑3)

## 数据维度筛选
- 只看域名的投放数据，即is_app=0
- 表格显示的数据是查询昨天的数据
- 投放表的维度包含计划，只需要按照域名+地区的维度来分组并求和收入
- 同比是对比前一天上升或下降的比例，即昨天（基准数据）和前天的升降百分比
- 同比上升和下降需要从昨天开始连续往前检查最多7天，需在百分比后显示持续天数，比如（↓3），只需要检查持续下降或者持续上升，比如往前两天都是下降，往前的第三天是上升，只需要显示（↓2），该数据就不需要继续往前检查了
- 每一条数据都是域名+地区两个维度检测

## 特殊要求
- 同比上升文本显示绿色，下降文本显示红色，飞书表格消息中，同比的表格列的data_type是lark_md，表格数据中，文本颜色使用`<font color='red'></font>`这种方式