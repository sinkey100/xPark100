export const default_columns = [
    {
        colKey: "base", className: "base", title: "基本", align: "center", children: [
            {colKey: "spend_total", className: "spend_total", title: "消耗", align: "center", sorter:true, width: 100},
            {colKey: "ad_revenue", className: "ad_revenue", title: "预计收入", align: "center", sorter:true, width: 110},
            {
                colKey: "roi", title: "ROI", align: "center", width: 100, sorter:true,
                className: ({row}: { row: any }) => {
                    const roi = String(row.roi);
                    if(!roi.includes('%')) return 'roi';
                    return parseFloat(roi.replace('%', '')) >= 100 ? 'td-text-green roi' : 'td-text-red roi';
                },
            }
        ]
    },
    {
        colKey: "diff", className: "diff", title: "事件对比", align: "center", children: [
            {colKey: "valid_events", className: "diff_valid_events", title: "有效事件", align: "center", sorter:true, width: 110},
            {colKey: "spend_conversion", className: "diff_spend_conversion", title: "转化", align: "center", sorter:true, width: 100},
            {colKey: "ad_clicks", className: "diff_clicks", title: "广告点击", align: "center", sorter:true, width: 110},
            {colKey: "gap", className: "diff_gap", title: "点击GAP", align: "center", sorter:true, width: 115},

        ]
    },
    {
        colKey: "user", className: "user", title: "用户", align: "center", children: [
            {colKey: "new_users", className: "new_users", title: "新增", align: "center", sorter:true, width: 90},
            {colKey: "active_users", className: "active_users", title: "活跃", align: "center", sorter:true, width: 90},
            {colKey: "total_time", className: "total_time", title: "时长", align: "center", width: 100},
            {colKey: "per_display", className: "per_display", title: "人均展示", align: "center", sorter:true, width: 110},
        ]
    },
    {
        colKey: "spend", className: "spend", title: "投放", align: "center", children: [
            {colKey: "spend_impressions", className: "spend_impressions", title: "展示", align: "center", sorter:true, width: 100},
            {colKey: "spend_clicks", className: "spend_clicks", title: "点击", align: "center", sorter:true, width: 100},
            {colKey: "spend_ctr", className: "spend_ctr", title: "CTR", align: "center", sorter:true, width: 100},
            {colKey: "spend_conv_rate", className: "spend_conv_rate", title: "转化率", align: "center", sorter:true, width: 110},
        ]
    },
    {
        colKey: "ad", className: "ad", title: "广告", align: "center", children: [
            {colKey: "ad_requests", className: "ad_requests", title: "请求", align: "center", sorter:true, width: 100},
            {colKey: "ad_impressions", className: "ad_impressions", title: "展示", align: "center", sorter:true, width: 100},
            {colKey: "ad_fills", className: "ad_fills", title: "填充", align: "center", sorter:true, width: 100},
            {colKey: "ad_fill_rate", className: "ad_fill_rate", title: "填充率", align: "center", sorter:true, width: 110},
            {colKey: "ad_clicks", className: "ad_clicks", title: "点击", align: "center", sorter:true, width: 100},
            {colKey: "ad_ctr", className: "ad_ctr", title: "CTR", align: "center", sorter:true, width: 100},
            {colKey: "ad_ecpm", className: "ad_ecpm", title: "eCPM", align: "center", sorter:true, width: 110},
            {colKey: "ad_cpc", className: "ad_cpc", title: "CPC", align: "center", sorter:true, width: 100},
        ]
    },
    {
        colKey: "event", className: "event", title: "事件", align: "center", children: [
            {colKey: "valid_events", className: "valid_events", title: "有效事件",  sorter:true,align: "center", width: 110},
            {colKey: "invalid_events", className: "invalid_events", title: "无效事件", sorter:true, align: "center", width: 110},
            {colKey: "anchored_count", className: "anchored_count", title: "anchored", sorter:true, align: "center", width: 115},
            {colKey: "banner_count", className: "banner_count", title: "banner",  sorter:true,align: "center", width: 100},
            {colKey: "fullscreen_count", className: "fullscreen_count", title: "fullscreen", sorter:true, align: "center", width: 115},
        ]
    },


]

export const columns_date = {colKey: "a_date", className: "a_date", title: "日期", align: "center", fixed: "left", width: 120}
export const columns_domain = {colKey: "sub_channel", className: "sub_channel", title: "域名", align: "center", sorter:true, fixed: "left", width: 200}
export const columns_tag = {colKey: "tag", className: "tag", title: "TAG标签", align: "center", width: 150}
export const columns_country_code = {colKey: "country_code", className: "country_code", title: "地区", align: "center", sorter:true, width: 80}
export const columns_event_type = {colKey: "event_type", className: "event_type", title: "事件类型", align: "center", width: 100}
export const columns_channel = {colKey: "channel_alias", className: "channel_alias", title: "通道", align: "center", sorter:true, width: 150}
