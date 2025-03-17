<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info"
                  show-icon/>

        <TableHeader
            :buttons="['refresh', 'comSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('spend.manage.quick Search Fields') })"
        >
            <el-button v-blur class="table-header-operate" type="primary" @click="syncData">
                <Icon color="#ffffff" name="fa fa-cloud-download"/>
                <span class="table-header-operate-text">同步数据</span>
            </el-button>
            <!--            <div class="last-time">上次同步时间： 2025-03-13 16:16:40</div>-->
        </TableHeader>

        <Table ref="tableRef"></Table>

        <PopupForm/>
    </div>
</template>

<script setup lang="ts">
import {onMounted, provide, ref} from 'vue'
import {useI18n} from 'vue-i18n'
import {baTableApi} from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import PopupForm from './popupForm.vue'

defineOptions({
    name: 'spend/manage',
})

const {t} = useI18n()
const tableRef = ref()

const optButtons: OptButton[] = [
    {
        render: 'tipButton',
        name: 'info',
        text: '修改预算',
        type: 'primary',
        icon: '',
        class: 'table-row-info',
        disabledTip: false,
        click: (row: TableRow) => {
            baTable.form.items = {...row};
            baTable.form.operate = 'Budget'
        },
    },
    {
        render: 'confirmButton',
        name: 'info',
        text: '停投',
        type: 'danger',
        icon: '',
        popconfirm: {
            confirmButtonText: '确认',
            cancelButtonText: '取消',
            confirmButtonType: 'warning',
            title: '是否确认停投此计划?',
        },
        display: (row: TableRow) => {
            return row.status;
        },
        click: (row: TableRow) => {
            baTable.table.loading = true;
            baTable.api.postData('switch', {
                id: row.id,
                status: 0,
            }).then(res => {
                if (res.code === 1) row!.status = res.data.status;
            }).finally(() => {
                baTable.table.loading = false;
            })
        }
    },
    {
        render: 'confirmButton',
        name: 'info',
        text: '启投',
        type: 'success',
        icon: '',
        popconfirm: {
            confirmButtonText: '确认',
            cancelButtonText: '取消',
            confirmButtonType: 'warning',
            title: '是否确认启投此计划?',
        },
        display: (row: TableRow) => {
            return !row.status;
        },
        click: (row: TableRow) => {
            baTable.table.loading = true;
            baTable.api.postData('switch', {
                id: row.id,
                status: 1,
            }).then(res => {
                if (res.code === 1) row!.status = res.data.status;
            }).finally(() => {
                baTable.table.loading = false;
            })
        }
    },
]

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/spend.Manage/'),
    {
        pk: 'id',
        column: [
            {
                label: t('spend.manage.id'),
                prop: 'id',
                align: 'center',
                width: 70,
                operator: false,
                sortable: 'custom',
                show: false
            },
            {
                label: '日期',
                prop: 'a_date',
                align: 'center',
                render: 'datetime',
                comSearchRender: 'date',
                operator: 'RANGE',
                sortable: false,
                show: false,
                timeFormat: 'yyyy-mm-dd',
            },
            {
                label: t('spend.manage.domain_id'),
                prop: 'domain.id',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'IN',
                sortable: false,
                show: false,
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/xpark.Domain/index',
                    multiple: true,
                    field: 'full_name',
                }
            },
            {
                label: t('spend.manage.domain_id'),
                prop: 'domain_name',
                align: 'center',
                width: 250,
                operator: false
            },
            {
                label: '地区',
                prop: 'country_code',
                align: 'center',
                operator: 'eq',
                sortable: false,
                width: 70,
            },
            {
                label: '收入',
                prop: 'total_revenue',
                align: 'center',
                operator: false,
                sortable: false,
                width: 100,
            },
            {
                label: '支出',
                prop: 'total_spend',
                align: 'center',
                operator: false,
                sortable: false,
                width: 100,
            },
            {
                label: 'ROI',
                prop: 'roi',
                align: 'center',
                operator: false,
                sortable: false,
                width: 100,
            },
            {
                label: t('spend.manage.smart_switch'),
                prop: 'smart_switch',
                align: 'center',
                operator: 'eq',
                render: 'tag',
                sortable: false,
                width: 120,
                custom: {'0': 'plain', '1': 'plain'},
                replaceValue: {'0': '普通', '1': 'Smart+'}
            },
            {
                label: t('spend.manage.campaign_id'),
                prop: 'campaign_id',
                align: 'center',
                operator: 'LIKE',
                sortable: 'custom',
                width: 160,
            },
            {
                label: t('spend.manage.campaign_name'),
                prop: 'campaign_name',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            {
                label: t('spend.manage.budget'),
                prop: 'budget',
                align: 'center',
                operator: false,
                sortable: false,
                width: 100,
            },
            {
                label: t('spend.manage.status'),
                prop: 'status',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                width: 120,
                replaceValue: {'0': t('spend.manage.status 0'), '1': t('spend.manage.status 1')},
                custom: {'0': 'warning', '1': 'primary'},
            },
            {label: t('Operate'), align: 'center', width: 150, render: 'buttons', buttons: optButtons, operator: false},
        ],
        dblClickNotEditColumn: [undefined, 'smart_switch'],
    },
    {}
)

provide('baTable', baTable)

onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.mount()
    // 默认查询今天
    const date = new Date(new Date().setDate(new Date().getDate())).toISOString().split('T')[0];
    baTable.comSearch.form['a_date'] = [date, date];
    baTable.table.filter!.search?.push({
        field: 'a_date',
        val: `${date} 00:00:00,${date} 23:59:59`,
        operator: 'RANGE',
        render: 'datetime',
    });
    baTable.comSearch.form['status'] = '1';
    baTable.table.filter!.search?.push({
        field: 'status',
        val: '1',
        operator: 'eq',
    });
    baTable.table.filter!.limit = 100;

    baTable.getIndex()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
        baTable.table.showComSearch = true
    })
})

const syncData = () => {
    baTable.table.loading = true;
    baTable.api.postData('sync', {}).then(res => {
        baTable.getIndex();
    }).finally(() => {
        baTable.table.loading = false;
    })
}
</script>

<style scoped lang="scss">
.last-time {
    color: #888;
    margin-left: 20px;
}

:deep(.el-table) {
    .el-tag--plain {
        --el-tag-border-color: transparent;
        --el-tag-bg-color: transparent;
        font-size: 14px;
    }

    .table-operate-text {
        padding-left: 0;
        font-size: 12px;
    }
}

:deep(.ba-operate-dialog) {
    .el-dialog__body {
        height: 250px;
    }
}

</style>
<style>
.el-popper:not(.is-dark) {
    .el-popconfirm__icon {
        font-size: 30px !important;
    }
}
</style>
