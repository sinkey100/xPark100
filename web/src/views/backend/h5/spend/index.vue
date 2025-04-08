<template>
    <div class="default-main ba-table-box">
        <TableHeader
            :buttons="['comSearch']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('spend.data.quick Search Fields') })"
        >
            <el-form-item class="form-dimensions" :label-width="100" label="维度">
                <el-checkbox v-model="baTable.table.filter!.dimensions!.a_date" label="日期" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.domain_id" label="域名" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.country_code" label="地区" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.channel_id" label="通道" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.event_type" label="事件类型" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.main_domain" label="主域名" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.account_name" label="投放账户" border/>
            </el-form-item>
            <el-popconfirm title="是否确认导出？" @confirm="derive">
                <template #reference>
                    <el-button class="table-header-operate btn-export" type="default">
                        <Icon style="color:#333!important;" name="el-icon-Download"/>
                    </el-button>
                </template>
            </el-popconfirm>
        </TableHeader>
        <div ref="tableRef">
            <t-table
                row-key="key"
                :bordered="true"
                :resizable="true"
                :data="tableData"
                :loading="isLoading"
                :columns="columns"
                :hover="true"

                :pagination="pagination"
                :foot-data="footData"
                :sort="sort"
                :show-sort-column-bg-color="true"
                @page-change="onPageChange"
                @sort-change="sortChange"
            ></t-table>

        </div>
        <PopupManage/>

    </div>
</template>

<script setup lang="tsx">
import {onMounted, provide, reactive, ref} from 'vue'
import {useI18n} from 'vue-i18n'
import {baTableApi} from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import baTableClass from '/@/utils/baTable'
import {TableProps} from "tdesign-vue-next";
import {
    columns_channel,
    columns_country_code,
    columns_date,
    columns_domain_days,
    columns_event_type,
    columns_tag,
    columns_main_domain,
    columns_account_name,
    default_columns, columns_more_roi
} from "/@/views/backend/h5/spend/columns";
import {ElButton, ElLoading} from 'element-plus'
import {exportToExcel} from "/@/utils/excel";
import {PrimaryTableCol} from "tdesign-vue-next/es/table/type";
import PopupManage from "/@/views/backend/spend/manage/popupManage.vue";


defineOptions({
    name: 'h5/spend',
})

const {t} = useI18n()
const tableRef = ref<HTMLElement | null>(null)
const dimensions = reactive({
    a_date: true,
    domain_id: true,
    country_code: false,
    channel_id: false,
    event_type: false,
    main_domain: false,
    account_name: false,
})

const tableData = ref([]);
const columns = ref();
const footData = ref();
const isLoading = ref(false);
const pagination = reactive({
    defaultCurrent: 1,
    defaultPageSize: 100,
    pageSizeOptions: [20, 50, 100, 500],
    total: 0,
})
const sort = ref<TableProps['sort']>({
    sortBy: '',
    descending: true,
})
const columns_domain = ref<PrimaryTableCol>({
    colKey: "sub_channel",
    className: "sub_channel",
    title: "域名",
    align: "center",
    sorter: true,
    fixed: "left",
    width: 200,
    cell: (h, {row}) => (
        <span ondblclick={() => showManager(row)}>{row.sub_channel}</span>
    ),
})

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const
    baTable = new baTableClass(
        new baTableApi('/admin/h5.Spend/'),
        {
            pk: 'id',
            column: [
                {
                    label: '日期',
                    prop: 'a_date',
                    render: 'datetimeAndTotal',
                    comSearchRender: 'date',
                    operator: 'RANGE',
                    timeFormat: 'yyyy-mm-dd',
                },
                {
                    label: '域名',
                    prop: 'domain_id',
                    operator: 'IN',
                    comSearchRender: 'remoteSelect',
                    remote: {
                        pk: 'id',
                        remoteUrl: 'admin/xpark.Domain/index',
                        field: 'domain',
                        multiple: true
                    }
                },
                {
                    label: '地区',
                    prop: 'country_code',
                    operator: 'IN',
                    comSearchRender: 'remoteSelect',
                    remote: {
                        pk: 'id',
                        remoteUrl: 'admin/xpark.Data/country',
                        multiple: true,
                        field: 'name'
                    }
                },
                {
                    label: '通道',
                    prop: 'channel_id',
                    operator: 'IN',
                    comSearchRender: 'remoteSelect',
                    remote: {
                        pk: 'id',
                        remoteUrl: 'admin/xpark.Channel/index',
                        field: 'channel_alias',
                    }
                },
                // {
                //     label: 'TAG标签',
                //     prop: 'domain.tag',
                //     operator: 'LIKE'
                // },
                {
                    label: '事件类型',
                    prop: 'track.event_type',
                    render: 'tag',
                    operator: 'eq',
                    replaceValue: {click: 'click', show: 'show'},
                },
                {
                    label: 'ROI',
                    prop: 'ext.roi',
                    render: 'tag',
                    operator: 'eq',
                    replaceValue: {1: '回正', 0: '未回正'},
                },
                {
                    label: '主域名',
                    prop: 'domain.main_domain',
                    operator: 'LIKE'
                },
                {
                    label: '投放账户',
                    prop: 'spend.account_name',
                    operator: 'LIKE'
                },
            ],
            dblClickNotEditColumn: [undefined],
        },
        {}, {
            getIndex: () => {
                // 默认查询昨天
                if (!baTable.comSearch.form['a_date']) {
                    const date = new Date(new Date().setDate(new Date().getDate())).toISOString().split('T')[0];
                    baTable.comSearch.form['a_date'] = [date, date];
                    baTable.table.filter!.search?.push({
                        field: 'a_date',
                        val: `${date} 00:00:00,${date} 23:59:59`,
                        operator: 'RANGE',
                        render: 'datetime',
                    });
                    dimensions.a_date = dimensions.domain_id = true;
                    dimensions.country_code = dimensions.event_type = dimensions.channel_id = false;
                    baTable.table.filter!.dimensions = dimensions
                }
                baTable.table.filter!.hideTimestamp = 0;
                columns.value = [];
                tableData.value = [];
                footData.value = [];
                isLoading.value = true;
            }
        }, {
            getIndex: ({res}) => {
                pagination.total = res.data.total;
                isLoading.value = false;
                // 动态修改表格列
                columns.value = DeepClone(default_columns);
                // 日期维度
                if (dimensions.a_date) {
                    columns.value[0].children.unshift({...columns_date});
                }
                // 域名维度
                if (dimensions.domain_id) {
                    const index = columns.value[0].children.findIndex((item: any) => item.colKey === 'spend_total');
                    // columns.value[0].children.splice(index, 0, {...columns_tag});
                    columns.value[0].children.splice(index, 0, {...columns_domain_days});
                    columns.value[0].children.splice(index, 0, {...columns_domain.value});
                }
                // 地区
                if (dimensions.country_code) {
                    const index = columns.value[0].children.findIndex((item: any) => item.colKey === 'spend_total');
                    columns.value[0].children.splice(index, 0, {...columns_country_code});
                }
                // 事件类型
                if (dimensions.event_type) {
                    columns.value[5].children.unshift({...columns_event_type});
                }
                // 主域名
                if (dimensions.main_domain) {
                    // 有域名就放在域名前面 没有就放在上线时间前
                    let index = columns.value[0].children.findIndex((item: any) => item.colKey === 'sub_channel');
                    if (index == -1) {
                        index = columns.value[0].children.findIndex((item: any) => item.colKey === 'spend_total');
                    }
                    columns.value[0].children.splice(index, 0, {...columns_main_domain});
                }
                // 投放账户
                if (dimensions.account_name) {
                    const index = columns.value[0].children.findIndex((item: any) => item.colKey === 'spend_total');
                    columns.value[0].children.splice(index, 0, {...columns_account_name});
                }
                // 通道
                if (dimensions.channel_id) {
                    let index = columns.value[0].children.findIndex((item: any) => item.colKey === 'tag');
                    if (index == -1) {
                        index = columns.value[0].children.findIndex((item: any) => item.colKey === 'spend_total');
                    }
                    columns.value[0].children.splice(index, 0, {...columns_channel});
                }
                // 更多ROI
                if (
                    dimensions.a_date == true && dimensions.channel_id == false && dimensions.event_type == false
                    && dimensions.main_domain == false && dimensions.account_name == false
                ) {
                    columns.value[0].children = columns.value[0].children.concat([...columns_more_roi]);
                    console.log(columns);
                }
                tableData.value = res.data.list;
                footData.value = res.data.foot;
            },
        }
    )

const onPageChange: TableProps['onPageChange'] = async (pageInfo) => {
    baTable.table.filter!.page = pageInfo.current;
    baTable.table.filter!.limit = pageInfo.pageSize;
    baTable.getIndex()
}

const sortChange: TableProps['onSortChange'] = (val: TableProps['sort']) => {
    if (val && !Array.isArray(val)) {
        sort.value = val;
        const sortBy = val.sortBy;
        const descendingFactor = val.descending ? -1 : 1;
        const stringSortKeys = ['sub_channel', 'country_code', 'channel_alias'];
        const comparator = (a: any, b: any) => {
            const aVal = a[sortBy];
            const bVal = b[sortBy];
            if (stringSortKeys.includes(sortBy)) {
                return descendingFactor * String(aVal).localeCompare(String(bVal));
            } else {
                return descendingFactor * (parseFloat(aVal) - parseFloat(bVal));
            }
        };
        tableData.value = [...tableData.value].sort(comparator);
    }
};

provide('baTable', baTable)
baTable.table.filter!.dimensions = dimensions
onMounted(() => {
    baTable.table.filter!.limit = 100;
    baTable.mount()

    baTable.getIndex()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
        baTable.table.showComSearch = true
    })

})

function DeepClone<T>(obj: T): T {
    if (obj === null || typeof obj !== 'object') {
        return obj;
    }
    if (typeof obj === 'function') {
        return obj;
    }
    if (Array.isArray(obj)) {
        return obj.map(item => DeepClone(item)) as unknown as T;
    }
    const clone = {} as any;
    for (const key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
            clone[key] = DeepClone((obj as any)[key]);
        }
    }
    return clone as T;
}

const getLeafColumns = (cols: any) => {
    let result: any = [];
    cols.forEach((col: any) => {
        if (col.children && Array.isArray(col.children)) {
            result = result.concat(getLeafColumns(col.children));
        } else {
            result.push(col);
        }
    })
    return result;
}

const derive = () => {
    const loadingInstance = ElLoading.service()
    const leafColumns = getLeafColumns(columns.value);
    const headerNames = leafColumns.map((col: any) => col.label || col.title);
    const dataKeys = leafColumns.map((col: any) => col.prop || col.colKey);

    const PAGE_SIZE = baTable.table.filter!.limit || 20;
    const TOTAL_ITEMS = pagination.total;
    const totalPages = Math.ceil(TOTAL_ITEMS / PAGE_SIZE); // 计算总页数

    async function fetchData() {
        let allData: any[] = [];
        for (let page = 1; page <= totalPages; page++) {
            baTable.table.filter!.page = page;
            baTable.table.filter!.hideTimestamp = 1;
            const res = await baTable.api.index(baTable.table.filter);
            const pageData = res.data.list || [];
            allData = allData.concat(pageData);
        }
        return allData;
    }

    fetchData().then(allData => {
        exportToExcel(headerNames, dataKeys, allData, 'H5投放分析');
        loadingInstance.close();
    });
}

const showManager = (row: anyObj) => {
    console.log(row);
    baTable.form.operate = 'Manage'
    baTable.form.items = {id: row.id}
    baTable.form.extend = row
}

onMounted(() => {
    if (tableRef.value) {
        tableRef.value.addEventListener('mousedown', (e: MouseEvent) => {
            if (e.detail > 1) {
                e.preventDefault() // 阻止双击选中
            }
        })
    }
})
</script>


<style scoped lang="scss">
:deep(.table-search) {
    display: none;
}

:deep(.t-table) {
    th.diff {
        background: #ffeeee;
    }

    th.user {
        background: #deefff;
    }

    th.spend {
        background: #ffe5d6;
    }

    th.ad {
        background: #e5f2e5;
    }

    th.event {
        background: #faf1ff;
    }

    .sub_channel, .roi3, .roi, .diff_gap, .per_display, .spend_conv_rate, .ad_cpc {
        border-right: 2px solid var(--td-component-border);
    }

    td.sub_channel {
        cursor: pointer;
        color: #04e;
    }

}
:deep(.el-dialog){
    .el-dialog__header{
        border-bottom: 0;
    }
    .table-header {
        border:0!important;
    }
    .el-scrollbar{
        margin:-10px -20px;
    }
}


</style>
