<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'comSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('xpark.domain.quick Search Fields') })"
        >
            <template #default>
                <el-button class="table-header-operate" type="warning" @click="bill">
                    <Icon color="#ffffff" name="fa fa-dollar"/>
                    <span class="table-header-operate-text">对账单</span>
                </el-button>
            </template>
        </TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef">
            <template #rate>
                <el-table-column prop="rate" width="100" align="center" :label="t('xpark.domain.rate')">
                    <template #default="scope">
                        <span :class="{red: scope.row.rate < 1}">{{ scope.row.rate }}</span>
                    </template>
                </el-table-column>
            </template>
        </Table>

        <!-- 表单 -->
        <PopupForm />
        <PopupBill />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import PopupBill from './popupBill.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import {ElNotification} from "element-plus";

defineOptions({
    name: 'xpark/domain',
})

const { t } = useI18n()
const tableRef = ref()
const optButtons: OptButton[] = defaultOptButtons(['edit'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/xpark.Domain/'),
    {
        pk: 'id',
        filter: {
            limit: 1000
        },
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('xpark.domain.id'), prop: 'id', align: 'center', width: 70, operator: false, sortable: 'custom' },
            { label: t('xpark.domain.domain'), prop: 'domain', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('xpark.domain.channel'), prop: 'channel', align: 'center', operatorPlaceholder: t('Fuzzy query'),render: 'tag', operator: false, sortable: false },
            {
                render: 'slot',
                slotName: 'rate',
                operator: false,
            },
            {
                label: t('xpark.domain.app_id'),
                prop: 'app.id',
                align: 'center',
                sortable: false,
                show:false,
                operator: 'eq',
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/xpark.Apps/index',
                    field: 'app_name',
                }
            },
            {
                label: t('xpark.domain.app_id'),
                prop: 'app.app_name',
                align: 'center',
                sortable: false,
                operator: false,
            },
            {
                label: t('xpark.domain.admin_id'),
                prop: 'admin.id',
                align: 'center',
                sortable: false,
                show:false,
                operator: 'eq',
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/auth.Admin/index',
                    field: 'nickname',
                }
            },
            {
                label: t('xpark.domain.admin_id'),
                prop: 'admin.nickname',
                align: 'center',
                sortable: false,
                operator: false,
            },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: { domain: null },
    }
)

provide('baTable', baTable)

onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.mount()
    baTable.getIndex()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
    })
})

const bill = () => {
    let select = Object.values({...baTable.table.selection});
    if (select.length == 0) {
        return
    }
    let rowList = select.map(item => item.admin_id);
    rowList = [...new Set(rowList)];
    if(rowList.length > 1) {
        ElNotification({type: 'error', message: '一次不能选择多个渠道'})
        return;
    }
    baTable.form.extend!.domainList = select
    baTable.form.operate! = 'bill';
}
</script>

<style scoped lang="scss">
.red{
    color:red;
}
</style>
