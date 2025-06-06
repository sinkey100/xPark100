<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('xpark.channel.quick Search Fields') })"
        ></TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'xpark/channel',
})

const { t } = useI18n()
const tableRef = ref()
const optButtons: OptButton[] = defaultOptButtons(['edit'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/xpark.Channel/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('xpark.channel.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('xpark.channel.ad_type'), prop: 'ad_type', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { H5: t('xpark.channel.ad_type H5'), Native: t('xpark.channel.ad_type Native') } },
            { label: t('xpark.channel.channel_type'), prop: 'channel_type', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('xpark.channel.channel_account'), prop: 'channel_account', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('xpark.channel.channel_alias'), prop: 'channel_alias', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('xpark.channel.is_own'), prop: 'is_own', align: 'center', width: 110, render: 'switch', operator: 'eq', sortable: false, replaceValue: { '0': t('build.domain.is_ssl 0'), '1': t('build.domain.is_ssl 1') } },
            { label: t('xpark.channel.private_switch'), prop: 'private_switch', align: 'center', width: 110, render: 'switch', operator: 'eq', sortable: false, replaceValue: { '0': t('build.domain.is_ssl 0'), '1': t('build.domain.is_ssl 1') } },
            { label: t('xpark.channel.status'), prop: 'status', align: 'center', width: 110, render: 'switch', operator: 'eq', sortable: false, replaceValue: { '0': t('build.domain.is_ssl 0'), '1': t('build.domain.is_ssl 1') } },
            { label: t('xpark.channel.timezone'), prop: 'timezone', align: 'center', operator: false, sortable: false ,width: 80},
            {
                label: t('xpark.channel.spend_model'),
                prop: 'spend_model',
                width: 120,
                align: 'center',
                operator: false,
                sortable: false
            },
            {
                label: t('xpark.channel.revenue_model'),
                prop: 'revenue_model',
                width: 120,
                align: 'center',
                operator: false,
                sortable: false
            },
            {
                label: t('xpark.channel.user_model'),
                prop: 'user_model',
                width: 120,
                align: 'center',
                operator: false,
                sortable: false
            },
            { label: t('xpark.channel.create_time'), prop: 'create_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM:ss' },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: { ad_type: 'H5' },
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
</script>

<style scoped lang="scss"></style>
