<template>
    <div class="default-main ba-table-box">

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh','comSearch']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('xpark.analysis.quick Search Fields') })"
        ></TableHeader>


        <t-table
            row-key="key"
            :bordered="true"
            :resizable="true"
            :data="tableData"
            :loading="isLoading"
            :columns="columns"
            :hover="true"
            :stripe="true"
        ></t-table>

    </div>
</template>

<script setup lang="ts">
import {onMounted, provide, computed, ref} from 'vue'
import {useI18n} from 'vue-i18n'
import {baTableApi} from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'xpark/analysis',
})

const {t} = useI18n()
const tableRef = ref()


const tableData = ref([]);
const columns = ref([]);
const isLoading = ref(false);

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/xpark.Analysis/'),
    {
        pk: 'id',
        column: [
            {
                label: '时间区间',
                prop: 'a_date',
                align: 'center',
                render: 'month',
                comSearchRender: 'date',
                operator: 'RANGE',
                timeFormat: 'yyyy-mm',
            },
            {
                label: '应用',
                prop: 'app_id',
                align: 'center',
                operator: 'IN',
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/xpark.Apps/select',
                    field: 'app_name',
                    multiple: true
                }
            },

        ],
        dblClickNotEditColumn: [undefined],
    },
    {}, {
        getIndex: () => {
            tableData.value = [];
            isLoading.value = true
        }
    }, {
        getIndex: ({res}) => {
            tableData.value = res.data.list;
            columns.value = res.data.columns;
            isLoading.value = false;
        },
    }
)

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
:deep(.t-table--bordered) {
    --td-component-border: #f6f6f6;
}

:deep(.t-table) {
    --td-text-color-placeholder: var(--el-text-color-primary);
    --td-text-color-primary: #444;

    thead {
        th {
            font-weight: 700
        }

    }

    tr {
        --td-bg-color-secondarycontainer: var(--el-fill-color-lighter);
        --td-bg-color-secondarycontainer-hover: #f5f7fa;
        --td-bg-color-container-hover: #f5f7fa;
    }

    th, td {
        --td-comp-paddingTB-m: 8px;
    }

    .t-table__th-col-revenue, th[data-colkey^="r_"] {
        background-color: var(--el-color-primary-light-8);
    }

    .t-table__th-col-clear, th[data-colkey^="c_"] {
        background-color: var(--el-color-danger-light-8);
    }

    .t-table__th-col-hold, th[data-colkey^="h_"] {
        background-color: var(--el-color-warning-light-8);
    }
}

:deep(.table-search) {
    display: none;
}


</style>
