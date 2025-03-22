# 常用封装方法

## HTTP Request请求
\app\command\Base 中，封装了GuzzleHttp的方法，方法名为 http()

## 飞书机器人通知
在 \sdk\FeishuBot ，有一个appMsg()方法，可以发送飞书机器人通知，通知的是一个表格形式。  
这个方法只需要传第一个参数，为表格结构的数组对象，第二个参数可以不填。

# 数据表定义
## 应用数据表
对应的模型是 \app\admin\model\xpark\Apps

```
-- auto-generated definition
create table ba_xpark_apps
(
    id            bigint auto_increment comment 'ID'
        primary key,
    app_name      varchar charset utf8      default '' null comment '应用名',
    remarks       varchar charset utf8      default '' null comment '备注',
    appstore_name varchar charset utf8      default '' null,
    pkg_name      varchar charset utf8      default '' null,
    createtime    int                                  null comment '创建时间',
    updatetime    int                                  null comment '修改时间',
    admin_id      bigint                               null,
    cp_admin_id   bigint                               null,
    status        tinyint(1)                default 0  null,
    appstore_url  varchar(200) charset utf8 default '' null,
    app_type      tinyint(1)                default 0  null comment '0=5B项目,1=合作项目,2=H5项目,3=OEM项目'
)
    comment '应用管理' engine = InnoDB
                       collate = utf8_bin;

```
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
我想做一个飞书机器人通知，针对应用下的某些域名的广告收入报表指标做飞书机器人风控报警通知。  
这个脚本通过在php的cli脚本运行，代码需要写在 app\command\Bot\Hb.php  

## 相关字段如下：  
1、 ba_xpark_apps表，app_name  应用名  
2、 ba_xpark_domain表， domain  域名  
3、 ba_xpark_domain表，is_show 显示层或者隐藏层
4、 ba_xpark_doman表， channel通道类型，flag通道标记， 两个字段拼起来为具体通道  
5、 ba_xpark_utc表，ad_unit_type 广告类型  
6、 ba_xpark_utc表，clicks 点击数，impressions 填充数，requests 请求数  
7、 点击率：点击÷展示的百分比  
8、 填充率：填充÷请求的百分比  
9、 ba_xpark_utc表， a_date 日期

## 机器人报警的表格如下面的形式 :

| 应用 | 域名 | 通道 | 广告类型 | 点击率 | 填充率 |
| -- | -- | -- | -- | -- | -- |
| app_name | domain | channel+flag | ad_unit_type| 22% | 33%  |

## 数据维度筛选
1、域名需要是隐藏层  
2、域名通道类型是AdSense  
3、广告类型只筛选 ads_interstitial、ads_anchor、banner 三种  
4、只针对当天的数据做筛选检查

## 数据指标筛选
1、域名的总收入大于1才报警
2、如果广告类型是banner，则点击率低于4%或者高于8%才报警  
3、如果广告类型是anchor，则点击率低于20%或者高于30%才报警  
4、如果广告类型是ads_interstitial，则点击率低于20%或者高于50%才报警  
5、填充率指标不分广告类型，只要低于90%，就报警  
6、对于点击率指标的表格字段，低于指标的，前面加上🟢，高于指标的，前面加上🔴，比如：🔴12%  
7、同一个域名的填充率和点击率，只要有一个触发指标，就在表格中显示一行，另一个没有触发指标，仅需要显示指标值即可  

## 特殊要求
需要检测指标存在的天数，点击率或填充率如果存在超过1天，需要在飞书机器人表格中显示存在天数，如点击率超过三天异常，表格中的指标显示为： 🟢2%(3)， 如填充率超过10天异常，表格中变成： 70%(10)
