<template>
    <div class="default-main ba-table-box">

        <TableHeader
            :buttons="['comSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('spend.data.quick Search Fields') })"
        ></TableHeader>
        <el-alert v-if="baTable.table.data!.length > 0" title="由于数据拉取存在延迟，当日数据仅供参考" type="warning"
                  :closable="false"/>

        <Table ref="tableRef"></Table>

    </div>
</template>

<script setup lang="ts">
import {onMounted, provide, ref} from 'vue'
import {useI18n} from 'vue-i18n'
import {baTableApi} from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'h5/cp',
})

const {t} = useI18n()
const tableRef = ref()

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/h5.Cp/'),
    {
        pk: 'id',
        column: [
            {type: 'selection', align: 'center', operator: false},
            {
                label: t('h5.cp.date'),
                prop: 'date',
                align: 'center',
                render: 'datetimeAndTotal',
                comSearchRender: 'date',
                operator: 'RANGE',
                sortable: false,
                timeFormat: 'yyyy-mm-dd',
                fixed: true
            },
            {
                label: t('h5.cp.app_id'),
                prop: 'app_id',
                align: 'center',
                sortable: false,
                show: false,
                operator: 'eq',
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/xpark.Apps/select',
                    field: 'app_name',
                }
            },
            {
                label: t('h5.cp.app'),
                prop: 'app_name',
                align: 'center',
                sortable: false,
                operator: false,
            },
            {label: t('h5.cp.spend'), prop: 'spend', align: 'center', operator: false, sortable: false},
            {label: t('h5.cp.revenue'), prop: 'revenue', align: 'center', operator: false, sortable: false},
            {label: t('h5.cp.profit'), prop: 'profit', align: 'center', operator: false, sortable: false},
            {label: t('h5.cp.share'), prop: 'share', align: 'center', operator: false, sortable: false},
        ],
        dblClickNotEditColumn: [undefined],
    }
)

baTable.table.rowClass = ({row, rowIndex,}: { row: any, rowIndex: number }) => {
    return row.date.substring(0, 10) == new Date().toISOString().split('T')[0] ? 'row-success' : '';
}

provide('baTable', baTable)


onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.mount()
    baTable.getIndex()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
        baTable.table.showComSearch = true
    })
})
</script>


<style scoped lang="scss">


:deep(.table-search), :deep(.table-header) {
    display: none;
}

:deep(.el-table) {
    tbody {
        tr:last-child {
            background: #eaeef0 !important;
        }
    }

    .el-table__row--striped td {
        background: transparent !important;
    }

    .el-table__cell div {
        box-sizing: border-box;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hover-row td {
        background: transparent !important;
    }
}

:deep(.row-success) {
    background-color: #fff8ee !important;
}
</style>
