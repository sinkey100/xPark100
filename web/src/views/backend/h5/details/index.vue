<template>
    <div class="default-main ba-table-box">

        <TableHeader
            :buttons="['comSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('spend.data.quick Search Fields') })"
        ></TableHeader>

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

defineOptions({
    name: 'h5/details',
})

const {t} = useI18n()
const tableRef = ref()

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/h5.Details/'),
    {
        pk: 'id',
        column: [
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
                    multiple:true
                }
            },
            {label: '应用', prop: 'app_name', align: 'center', sortable: false, operator: false,},
            {label: 'App新增', prop: 'app_new_users', align: 'center', operator: false, sortable: false},
            {label: 'App活跃', prop: 'app_active_users', align: 'center', operator: false, sortable: false},
            {label: 'H5新增', prop: 'h5_new_users', align: 'center', operator: false, sortable: false},
            {label: 'H5活跃', prop: 'h5_active_users', align: 'center', operator: false, sortable: false},
            {label: '总收入', prop: 'total_revenue', align: 'center', operator: false, sortable: false},
            {label: '总支出', prop: 'total_spend', align: 'center', operator: false, sortable: false},
            {label: 'Native收入', prop: 'native_revenue', align: 'center', operator: false, sortable: false},
            {label: 'H5收入', prop: 'h5_revenue', align: 'center', operator: false, sortable: false},
            {label: 'ROI', prop: 'roi', align: 'center', operator: false, sortable: false},
            {label: 'H5 ARPU', prop: 'h5_arpu', align: 'center', operator: false, sortable: false, width: 115},
            {label: 'ARPU', prop: 'app_arpu', align: 'center', operator: false, sortable: false, width: 115},
            {label: 'HB开启率', prop: 'hb_open_rate', align: 'center', operator: false, sortable: false, width: 115},
            {label: 'Native收入比', prop: 'native_rate', align: 'center', operator: false, sortable: false, width: 115},
        ],
        dblClickNotEditColumn: [undefined],
    }
)

provide('baTable', baTable)
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

.btn-export {
    position: absolute;
    right: 20px;
    top: 13px;
}

:deep(.table-search) {
    display: none;
}

:deep(.el-table) {
    tbody {
        tr:last-child {
            background: #eaeef0 !important;
        }
    }

    .el-table__cell div {
        box-sizing: border-box;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
}

</style>
