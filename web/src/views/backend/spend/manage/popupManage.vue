<template>
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="parentBaTable.form.operate === 'Manage'"
        @close="parentBaTable.toggleForm"
        width="90%"
        @open="open"

    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                <b>投放操作</b>
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
                <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info"
                          show-icon/>
            <TableHeader
                :buttons="['refresh', 'comSearch', 'columnDisplay']"
                :quick-search-placeholder="t('Quick search placeholder', { fields: t('spend.manage.quick Search Fields') })"
            >
                <el-button v-blur class="table-header-operate" type="primary" @click="syncData">
                    <Icon color="#ffffff" name="fa fa-cloud-download"/>
                    <span class="table-header-operate-text">同步计划</span>
                </el-button>
                <div class="last-time">上次同步： {{ last_time }}</div>
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
                :sort="sort"
                :show-sort-column-bg-color="true"
                @page-change="onPageChange"
                @sort-change="sortChange"
            ></t-table>

            <PopupForm/>
        </el-scrollbar>
    </el-dialog>
</template>

<script setup lang="tsx">
import {inject, onMounted, provide, reactive, ref} from 'vue'
import {useI18n} from 'vue-i18n'
import {baTableApi} from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import baTableClass from '/@/utils/baTable'
import PopupForm from './popupForm.vue'
import {ElTag, ElButton, ElMessageBox} from 'element-plus';
import {TableProps} from "tdesign-vue-next";

defineOptions({
    name: 'spend/manage',
})

const {t} = useI18n()
const tableRef = ref()
const tableData = ref([]);
const last_time = ref('')
const columns = ref<TableProps['columns']>([
    {colKey: "domain_name", title: "域名", align: "center", sorter: true, width: 250},
    {colKey: "country_code", title: "地区", align: "center", sorter: true, width: 80},
    {colKey: "total_revenue", title: "收入", align: "center", sorter: true, width: 100},
    {colKey: "total_spend", title: "支出", align: "center", sorter: true, width: 100},
    {colKey: "roi", title: "ROI", align: "center", sorter: true, width: 100},
    {
        colKey: "smart_switch",
        title: "推广类型",
        align: "center",
        width: 100,
        cell: (h, {row}) => row.smart_switch == 1 ? 'Smart+' : '普通'
    },
    {colKey: "campaign_id", title: "推广计划ID", align: "center", sorter: true, width: 170},
    {colKey: "campaign_name", title: "推广计划名称", align: "center", sorter: true, width: 350},
    {colKey: "budget", title: "预算", align: "center", sorter: true, width: 100},
    {
        colKey: "status", title: "状态", align: "center", width: 100, cell: (h, {row}) => {
            return (
                <ElTag type={row.status === 1 ? 'success' : 'warning'}>
                    {row.status == 1 ? '投放中' : '已停止'}
                </ElTag>
            );
        }
    },
    {
        title: '操作', colKey: 'link', align: "center", width: 150, cell: (h, {row}) => (
            <div>
                <ElButton size="small" type="primary" plain onClick={() => budget(row)}>修改预算</ElButton>
                <ElButton size="small"
                          type={row.status == 1 ? 'danger' : 'success'}
                          onClick={() => change(row, row.status === 1 ? 0 : 1)}>
                    {row.status == 1 ? '停投' : '启投'}
                </ElButton>
            </div>
        ),
    },
])

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
const parentBaTable = inject('baTable') as baTableClass


/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/spend.Manage/'),
    {
        pk: 'id',
        column: [
            {
                label: '地区',
                prop: 'country_code',
                align: 'center',
                operator: 'eq',
            },
            {
                label: t('spend.manage.smart_switch'),
                prop: 'smart_switch',
                align: 'center',
                operator: 'eq',
                render: 'tag',
                sortable: false,
                replaceValue: {'0': '普通', '1': 'Smart+'}
            },
            {
                label: t('spend.manage.campaign_id'),
                prop: 'campaign_id',
                operator: 'LIKE',
                sortable: 'custom',
            },
            {
                label: t('spend.manage.campaign_name'),
                prop: 'campaign_name',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            {
                label: t('spend.manage.status'),
                prop: 'status',
                render: 'tag',
                operator: 'eq',
                replaceValue: {'0': t('spend.manage.status 0'), '1': t('spend.manage.status 1')},
            },
        ],
    },
    {}, {
        getIndex: () => {
            baTable.comSearch.form['domain_id'] = parentBaTable.form.extend!.domain_id;
            baTable.table.filter!.search?.push({
                field: 'domain_id',
                val: parentBaTable.form.extend!.domain_id,
                operator: 'eq',
            });
            tableData.value = [];
            isLoading.value = true;
        }
    }, {
        getIndex: ({res}) => {
            pagination.total = res.data.total;
            isLoading.value = false;
            last_time.value = res.data.last_time;
            tableData.value = res.data.list;
        },
    }
)

provide('baTable', baTable)

onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.mount()

})
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
        const stringSortKeys = ['campaign_name', 'domain_name', 'country_code'];
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
}

const syncData = () => {
    baTable.table.loading = true;
    baTable.api.postData('sync', {}).then(res => {
        baTable.getIndex();
    }).finally(() => {
        baTable.table.loading = false;
    })
}

const budget = (row: any) => {
    baTable.form.items = {...row};
    baTable.form.operate = 'Budget'
}

const change = (row: any, status: number) => {
    ElMessageBox.confirm(
        `是否确认${status == 1 ? '启投' : '停投'}此计划：\n ${row.campaign_name} `,
        row.domain_name,
        {
            confirmButtonText: '确认',
            cancelButtonText: '取消',
            type: 'warning',
        }
    ).then(() => {
        isLoading.value = true;
        baTable.api.postData('switch', {
            id: row.id,
            status,
        }).then(res => {
            if (res.code === 1) row!.status = res.data.status;
        }).finally(() => {
            isLoading.value = false;
        })
    })

}
const open = () => {
    baTable.comSearch.form = [];
    baTable.table.filter!.search = [];
    baTable.table.filter!.limit = 100;

    baTable.getIndex()?.then(() => {
        baTable.table.showComSearch = true;
        baTable.form.extend = parentBaTable.form.extend
        baTable.initSort()
        baTable.dragSort()
    })
}

</script>

<style scoped lang="scss">
.last-time {
    color: #888;
    margin-left: 20px;
}

:deep(.ba-operate-dialog) {
    .el-dialog__body {
        height: 250px;
    }
}

:deep(.t-table) {
    .el-button {
        padding: 4px 5px;
        border-radius: 2px;

        &.el-button--primary {
            font-weight: 400;
        }
    }
}

</style>
