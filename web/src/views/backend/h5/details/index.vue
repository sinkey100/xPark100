<template>
    <div class="default-main ba-table-box">

        <TableHeader
            :buttons="['comSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('spend.data.quick Search Fields') })"
        >
            <el-form-item class="dimensions-form" :label-width="100" label="维度">
                <el-checkbox v-model="baTable.table.filter!.dimensions!.a_date" label="日期" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.app_id" label="应用" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.channel_id" label="通道" border/>
            </el-form-item>
        </TableHeader>

        <Table ref="tableRef"></Table>

    </div>
</template>

<script setup lang="ts">
import {onMounted, provide, reactive, ref} from 'vue'
import {useI18n} from 'vue-i18n'
import {baTableApi} from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import app_type from "/@/views/backend/xpark/apps/app_type";

defineOptions({
    name: 'h5/details',
})

const {t} = useI18n()
const tableRef = ref()
const dimensions = reactive({
    a_date: true,
    app_id: true,
    channel_id: false,
})

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/h5.Details/'),
    {
        pk: 'id',
        column: [
            {type: 'selection', align: 'center', operator: false},
            {
                label: '日期',
                prop: 'a_date',
                align: 'center',
                render: 'datetimeAndTotal',
                comSearchRender: 'date',
                operator: 'RANGE',
                sortable: false,
                width: 110,
                timeFormat: 'yyyy-mm-dd',
                fixed: true
            },
            {
                label: '应用',
                prop: 'app_id',
                align: 'center',
                sortable: false,
                show: false,
                minWidth: 120,
                operator: 'IN',
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/xpark.Apps/select',
                    field: 'app_name',
                    multiple: true
                }
            },
            {
                label: '通道',
                prop: 'channel_id',
                operator: 'eq',
                show: false,
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/xpark.Channel/index',
                    field: 'channel_alias',
                }
            },
            {
                label: '项目类型',
                show: false,
                prop: 'apps.app_type',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                replaceValue: app_type,
            },
            {
                label: '项目类型',
                prop: 'app_type',
                align: 'center',
                sortable: false,
                operator: false,
                render: 'tag',
                width: 100,
                replaceValue: app_type
            },
            {label: '应用', prop: 'app_name', align: 'center', sortable: false, operator: false, width: 120},
            {label: '通道', prop: 'channel_full', align: 'center', sortable: false, operator: false, width: 165,},
            {label: 'App新增', prop: 'app_new_users', align: 'center', operator: false, sortable: false, width: 100},
            {label: 'App活跃', prop: 'app_active_users', align: 'center', operator: false, sortable: false, width: 100},
            {label: 'CPI', prop: 'cpi', align: 'center', operator: false, sortable: false, width: 100},
            {label: 'H5新增', prop: 'h5_new_users', align: 'center', operator: false, sortable: false, width: 100},
            {label: 'H5活跃', prop: 'h5_active_users', align: 'center', operator: false, sortable: false, width: 100},
            {label: '总支出', prop: 'total_spend', align: 'center', operator: false, sortable: false, width: 100},
            {label: '总收入', prop: 'total_revenue', align: 'center', operator: false, sortable: false, width: 100},
            {label: '预估利润', prop: 'profit', align: 'center', operator: false, sortable: false, width: 100},
            {label: 'ROI', prop: 'roi', align: 'center', operator: false, sortable: false, width: 100},
            {label: 'Native收入', prop: 'native_revenue', align: 'center', operator: false, width: 100},
            {label: 'H5收入', prop: 'h5_revenue', align: 'center', operator: false, sortable: false, width: 100},
            {label: 'H5 ARPU', prop: 'h5_arpu', align: 'center', operator: false, sortable: false, width: 115},
            {label: 'ARPU', prop: 'app_arpu', align: 'center', operator: false, sortable: false, width: 115},
            {label: 'HB开启率', prop: 'hb_open_rate', align: 'center', operator: false, sortable: false, width: 115},
            {label: 'Native收入比', prop: 'native_rate', align: 'center', operator: false, sortable: false, width: 115},
        ],
        dblClickNotEditColumn: [undefined],
    }, {}, {
        getIndex: () => {
            baTable.table.column.forEach((item: any) => {
                item.show = !(["app_id", "channel_id", "apps.app_type"].includes(item.prop))
            })
        }
    }, {
        getIndex: ({res}) => {
            baTable.table.column.forEach((item: any) => {
                if (dimensions.channel_id && !dimensions.a_date && !dimensions.app_id) {
                    item.show = ["channel_full", "total_revenue", "native_revenue", "h5_revenue", "native_rate"].includes(item.prop)
                    if (item.prop == 'channel_full') item.width = 220
                    return;
                }

                if (["total_revenue", "native_revenue", "h5_revenue"].includes(item.prop)) item.width = 100
                if (item.prop == 'native_rate') item.width = 115
                if (item.prop == 'app_id') return;

                // 显示应用名称
                if (item.prop == 'app_name' || item.prop == 'app_type') {
                    item.show = baTable.table.filter!.dimensions['app_id'] == true;
                    return;
                }

                if (item.prop == 'channel_id') return;
                if (item.prop == 'channel_full') {
                    item.width = 165;
                    item.show = baTable.table.filter!.dimensions['channel_id'] == true;
                    return;
                }

                if (baTable.table.filter!.dimensions[item.prop] == undefined) return;
                item.show = baTable.table.filter!.dimensions[item.prop];
            })
        },
    }
)

provide('baTable', baTable)
baTable.table.filter!.dimensions = dimensions

onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.mount()
    // 默认查询昨天
    const date = new Date(new Date().setDate(new Date().getDate() - 1)).toISOString().split('T')[0];
    baTable.comSearch.form['a_date'] = [date, date];
    baTable.table.filter!.search?.push({
        field: 'a_date',
        val: `${date} 00:00:00,${date} 23:59:59`,
        operator: 'RANGE',
        render: 'datetime',
    });
    baTable.getIndex()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
        baTable.table.showComSearch = true
    })

})
</script>


<style scoped lang="scss">


:deep(.table-search) {
    display: none;
}

:deep(.el-table) {
    tbody {
        tr:last-child {
            background: #eaeef0 !important;
        }
    }

    .el-tag {
        background: transparent;
        border: 0;
        color: var(--el-table-text-color);
        padding: 0;
        font-size: 14px;
    }

    .el-table__cell div {
        box-sizing: border-box;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
}

.dimensions-form {
    margin-bottom: 0;
}

</style>
