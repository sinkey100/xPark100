<template>
    <div class="default-main ba-table-box">
        <TableHeader
            :buttons="['comSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('spend.data.quick Search Fields') })"
        >
            <el-form-item class="form-dimensions" :label-width="100" label="维度">
                <el-checkbox v-model="baTable.table.filter!.dimensions!.a_date" label="日期" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.domain_id" label="域名" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.country_code" label="地区" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.event_type" label="事件类型" border/>
            </el-form-item>
        </TableHeader>

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
</template>

<script setup lang="ts">
import {onMounted, provide, reactive, ref} from 'vue'
import {useI18n} from 'vue-i18n'
import {baTableApi} from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import baTableClass from '/@/utils/baTable'
import {SortInfo, TableProps, TableSort} from "tdesign-vue-next";
import {
    columns_country_code,
    columns_date,
    columns_domain,
    columns_event_type,
    columns_tag,
    default_columns
} from "/@/views/backend/h5/spend/columns";

defineOptions({
    name: 'h5/spend',
})

const {t} = useI18n()
const tableRef = ref()
const dimensions = reactive({
    a_date: true,
    domain_id: true,
    country_code: false,
    event_type: false,
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

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
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
                label: 'TAG标签',
                prop: 'domain.tag',
                operator: 'LIKE'
            },
            {
                label: '事件类型',
                prop: 'track.event_type',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                replaceValue: {click: 'click', show: 'show'},
            },
            {
                label: 'ROI',
                prop: 'ext.roi',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                replaceValue: {1: '回正', 0: '未回正'},
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
                dimensions.country_code = dimensions.event_type = false;
                baTable.table.filter!.dimensions = dimensions
            }
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
                columns.value[0].children.splice(index, 0, {...columns_tag});
                columns.value[0].children.splice(index, 0, {...columns_domain});
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
        if (val.descending) {
            tableData.value = [...tableData.value].sort((a, b) => parseFloat(b[sortBy]) - parseFloat(a[sortBy]));
        } else {
            tableData.value = [...tableData.value].sort((a, b) => parseFloat(a[sortBy]) - parseFloat(b[sortBy]));
        }
    }
};

provide('baTable', baTable)
baTable.table.filter!.dimensions = dimensions
onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.table.filter!.limit = 100;
    baTable.mount()

    baTable.getIndex()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
        baTable.table.showComSearch = true
    })

})
const DeepClone = <T>(obj: T): T => {
    if (obj === null || typeof obj !== 'object') {
        return obj;
    }
    // 如果是函数，则直接返回函数引用
    if (typeof obj === 'function') {
        return obj;
    }
    // 如果是数组，遍历每个元素进行深拷贝
    if (Array.isArray(obj)) {
        return obj.map(item => DeepClone(item)) as unknown as T;
    }
    // 处理普通对象
    const clone = {} as any;
    for (const key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
            clone[key] = DeepClone((obj as any)[key]);
        }
    }
    return clone as T;
}


</script>


<style scoped lang="scss">
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

    .sub_channel, .roi, .diff_gap, .per_display, .spend_conv_rate, .ad_cpc {
        border-right: 2px solid var(--td-component-border);
    }

}


</style>
