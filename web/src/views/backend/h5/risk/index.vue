<template>
    <div class="default-main ba-table-box">

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['comSearch']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('xpark.analysis.quick Search Fields') })"
        >
            <el-form-item :label-width="100" label="维度">
                <el-checkbox v-model="baTable.table.filter!.dimensions!.a_date" label="日期" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.app_id" label="应用" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.domain_id" label="域名" border/>
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
            @page-change="onPageChange"
        ></t-table>

    </div>
</template>

<script setup lang="ts">
import {onMounted, provide, computed, ref, reactive} from 'vue'
import {useI18n} from 'vue-i18n'
import {baTableApi} from '/@/api/common'
import TableHeader from '/src/components/table/header/index.vue'
import baTableClass from '/@/utils/baTable'
import {TableProps} from "tdesign-vue-next";

defineOptions({
    name: 'h5/risk',
})

const {t} = useI18n()
const tableRef = ref()
const dimensions = reactive({
    a_date: true,
    domain_id: false,
    app_id: false,
})

const tableData = ref([]);
const columns = ref([]);
const isLoading = ref(false);
const pagination = reactive({
    defaultCurrent: 1,
    defaultPageSize: 20,
    total: 0,
});
/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/h5.Risk/'),
    {
        pk: 'id',
        column: [
            {
                label: '日期',
                prop: 'a_date',
                render: 'datetime',
                comSearchRender: 'date',
                operator: 'RANGE',
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
            {
                label: '账号通道',
                prop: 'channel_id',
                align: 'center',
                operator: 'IN',
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/xpark.Channel/select',
                    field: 'channel_alias',
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
            pagination.total = res.data.total;
            isLoading.value = false;
        },
    }
)

const onPageChange: TableProps['onPageChange'] = async (pageInfo) => {
    baTable.table.filter!.page = pageInfo.current;
    baTable.table.filter!.limit = pageInfo.pageSize;
    baTable.getIndex();
};

provide('baTable', baTable)
baTable.table.filter!.dimensions = dimensions


onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.table.filter!.limit = 20;
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

    .t-table__th-col-revenue, th[data-colkey^="dimensions_spend"] {
        background-color: var(--el-color-primary-light-9);
    }

    .t-table__th-col-clear, th[data-colkey^="dimensions_revenue"] {
        background-color: var(--el-color-success-light-9);
    }

    .t-table__th-col-hold, th[data-colkey^="dimensions_user"] {
        background-color: var(--el-color-warning-light-8);
    }

    th[data-colkey="h5_advertise_spend"],
    th[data-colkey="h5_advertise_revenue"],
    th[data-colkey="h5_advertise_roi"],
    th[data-colkey="h5_advertise_active"],
    th[data-colkey="hb_show_active"],
    th[data-colkey="hb_show_new"],
    th[data-colkey="hb_show_revenue"] {
        color: #2ba471;
    }
}

:deep(.table-search) {
    display: none;
}


</style>
