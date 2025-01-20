export const default_columns = [
    {colKey: "channel_flag", title: "账号", align: "center", width: 180, fixed: "left"},
    {colKey: "h5_advertise_spend", title: "H5投放支出", align: "center", width: 110},
    {colKey: "h5_advertise_revenue", title: "H5投放收入", align: "center", width: 110},
    {colKey: "h5_advertise_roi", title: "H5投放ROI", align: "center", width: 110},
    {colKey: "h5_advertise_active", title: "H5投放活跃", align: "center", width: 110},
    {colKey: "hb_show_active", title: "游戏中心活跃", align: "center", width: 120},
    {colKey: "hb_show_new", title: "游戏中心新增", align: "center", width: 120},
    {colKey: "hb_show_revenue", title: "游戏中心收入", align: "center", width: 120},
    {colKey: "hb_hide_revenue", title: "HB收入", align: "center", width: 100},
    {
        colKey: "dimensions_spend", title: "支出维度", align: "center", width: "auto", children: [
            {colKey: "dimensions_spend_model", title: "支出维度标准模型", align: "center", ellipsis: true, width: 150},
            {
                colKey: "dimensions_spend_gap",
                title: "支出维度差值",
                align: "center",
                ellipsis: true,
                width: 150,
                className: ({row}: { row: any }) => {
                    if (row.dimensions_spend_gap > 0) return 'td-green';
                    else if (row.dimensions_spend_gap < 0) return 'td-red';
                },
            }
        ]
    },
    {
        colKey: "dimensions_revenue", title: "收入维度", align: "center", width: "auto", children: [
            {
                colKey: "dimensions_revenue_model",
                title: "收入维度标准模型",
                align: "center",
                ellipsis: true,
                width: 150
            },
            {
                colKey: "dimensions_revenue_gap", title: "收入维度差值", align: "center", ellipsis: true, width: 150,
                className: ({row}: { row: any }) => {
                    if (row.dimensions_revenue_gap > 0) return 'td-green';
                    else if (row.dimensions_revenue_gap < 0) return 'td-red';
                },
            }
        ]
    },
    {colKey: "hb_hide_active", title: "HB活跃", align: "center", width: 180},
    {
        colKey: "dimensions_user", title: "用户维度", align: "center", width: "auto", children: [
            {colKey: "dimensions_user_model", title: "用户维度标准模型", align: "center", ellipsis: true, width: 150},
            {
                colKey: "dimensions_user_gap", title: "用户维度差值", align: "center", ellipsis: true, width: 150,
                className: ({row}: { row: any }) => {
                    if (row.dimensions_user_gap > 0) return 'td-green';
                    else if (row.dimensions_user_gap < 0) return 'td-red';
                },
            }
        ]
    }
]

export const columns_date = {colKey: "date", title: "日期", align: "center", width: 120, fixed: "left"}
export const columns_app_id = {colKey: "app_name", title: "项目", align: "center", width: 180, fixed: "left"}
export const columns_domain_id = {
    colKey: "hb_domain_name",
    title: "HB链接",
    align: "center",
    width: 220,
    ellipsis: true
}
