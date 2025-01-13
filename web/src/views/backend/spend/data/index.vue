<template>
    <div class="default-main ba-table-box">

        <TableHeader
            :buttons="['comSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('spend.data.quick Search Fields') })"
        >
            <el-form-item :label-width="100" label="维度">
                <el-checkbox v-model="baTable.table.filter!.dimensions!.date" label="日期" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.domain_id" label="域名" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.app_id" label="应用" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.country_code" label="地区" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.channel_name" label="通道" border/>
                <!--                <el-button class="dimensions-btn" @click="onComSearch" type="primary">{{ $t('Search') }}</el-button>-->
            </el-form-item>
        </TableHeader>

        <Table ref="tableRef"></Table>

        <PopupForm/>
    </div>
</template>

<script setup lang="ts">
import {onMounted, provide, reactive, ref} from 'vue'
import {useI18n} from 'vue-i18n'
import PopupForm from './popupForm.vue'
import {baTableApi} from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'spend/data',
})

const {t} = useI18n()
const tableRef = ref()
const dimensions = reactive({
    date: true,
    domain_id: false,
    app_id: false,
    country_code: false,
    channel_name:false,
})

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/spend.Data/'),
    {
        pk: 'id',
        column: [
            {type: 'selection', align: 'center', operator: false},
            {
                label: t('spend.data.date'),
                prop: 'date',
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
                label: t('spend.data.app_id'),
                prop: 'app_id',
                align: 'center',
                sortable: false,
                show: false,
                minWidth: 120,
                operator: 'eq',
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/xpark.Apps/select',
                    field: 'app_name',
                }
            },
            {
                label: t('spend.data.app_id'),
                prop: 'app_name',
                align: 'center',
                show: false,
                minWidth: 160,
                sortable: false,
                operator: false,
            },
            {
                label: t('spend.data.channel_name'),
                prop: 'channel_name',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                show: false,
                sortable: false
            },
            {
                label: t('spend.data.domain_id'),
                prop: 'domain_id',
                align: 'center',
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
                label: t('spend.data.domain_id'),
                prop: 'domain_name',
                align: 'center',
                sortable: false,
                operator: false,
                show: false,
                minWidth: 180,
                fixed: true
            },
            {
                label: t('spend.data.is_app'),
                prop: 'is_app',
                align: 'center',
                operator: 'eq',
                render: 'tag',
                sortable: false,
                show: false,
                replaceValue: {0: 'H5', 1: 'App',}
            },
            {
                label: t('spend.data.country_code'),
                prop: 'country_code',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'IN',
                width: 150,
                sortable: false,
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/xpark.Data/country',
                    multiple: true,
                    field: 'name'
                }
            },
            {
                label: t('spend.data.country_level'),
                prop: 'country_level',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: false,
                width: 60,
                sortable: false,
            },
            {label: t('spend.data.spend'), prop: 'spend', align: 'center', operator: false, sortable: false},
            {label: t('spend.data.clicks'), prop: 'clicks', align: 'center', operator: false, sortable: false},
            {
                label: t('spend.data.impressions'),
                prop: 'impressions',
                align: 'center',
                operator: false,
                sortable: false
            },
            {label: t('spend.data.install'), prop: 'install', align: 'center', operator: false, sortable: false},
            {label: t('spend.data.cpc'), prop: 'cpc', align: 'center', operator: false, sortable: false},
            {label: t('spend.data.cpm'), prop: 'cpm', align: 'center', operator: false, sortable: false},
        ],
        dblClickNotEditColumn: [undefined],
    }, {}, {}, {
        getIndex: ({res}) => {
            baTable.table.column.forEach((item: any) => {
                // 显示应用名称
                if (item.prop == 'app_id') return;
                if (item.prop == 'app_name') {
                    item.show = baTable.table.filter!.dimensions['app_id'] == true;
                    return;
                }
                // 显示国家T级
                if (item.prop == 'country_level') {
                    item.show = baTable.table.filter!.dimensions['country_code'] == true;
                    return;
                }
                if (item.prop == 'domain_name') {
                    item.show = baTable.table.filter!.dimensions['domain_id'] == true;
                    return;
                }
                if (item.prop == 'domain_id') {
                    item.show = false;
                    return;
                }
                if (baTable.table.filter!.dimensions[item.prop] == undefined) {
                    return;
                }
                item.show = baTable.table.filter!.dimensions[item.prop];
            })
        }
    }
)

provide('baTable', baTable)

baTable.table.filter!.dimensions = dimensions

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
.dimensions-btn {
    margin-left: 30px;
}

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
