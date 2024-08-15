<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'comSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('mi.instant.report.quick Search Fields') })"
        ></TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { baTableApi } from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'mi/instant/report',
})

const { t } = useI18n()
const tableRef = ref()

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/mi.instant.Report/'),
    {
        pk: 'id',
        column: [
            { label: t('mi.instant.report.DATE'), prop: 'DATE', align: 'center', render: 'datetime', operator: 'RANGE', width: 110, timeFormat: 'yyyy-mm-dd' },
            {label: t('mi.instant.report.PAGE_URL'), prop: 'PAGE_URL', align: 'center', operator: false, sortable: false,},
            {label: t('mi.instant.report.COUNTRY_CODE'), prop: 'COUNTRY_CODE', align: 'center', operator: 'LIKE',  width: 70},
            {label: t('mi.instant.report.ESTIMATED_EARNINGS'), prop: 'ESTIMATED_EARNINGS', align: 'center', operator: 'LIKE', sortable: true, width: 110},
            {label: t('mi.instant.report.PAGE_VIEWS'), prop: 'PAGE_VIEWS', align: 'center', operator: 'LIKE', sortable: true, width: 100,},
            {label: t('mi.instant.report.AD_REQUESTS'), prop: 'AD_REQUESTS', align: 'center', operator: 'LIKE', sortable: true, width: 100},
            {label: t('mi.instant.report.IMPRESSIONS'), prop: 'IMPRESSIONS', align: 'center', operator: 'LIKE', sortable: true, width: 100},
            {label: t('mi.instant.report.AD_REQUESTS_COVERAGE'), prop: 'coverage', align: 'center', operator: 'LIKE', sortable: true, width: 100},
            {label: t('mi.instant.report.CLICKS'), prop: 'CLICKS', align: 'center', operator: 'LIKE', sortable: true, width: 100},
            {label: t('mi.instant.report.IMPRESSIONS_CTR'), prop: 'ctr', align: 'center', operator: 'LIKE', sortable: true, width: 100},
            {label: t('mi.instant.report.COST_PER_CLICK'), prop: 'COST_PER_CLICK', align: 'center', operator: 'LIKE', sortable: true, width: 100},
            {label: t('mi.instant.report.IMPRESSIONS_RPM'), prop: 'IMPRESSIONS_RPM', align: 'center', operator: 'LIKE', sortable: true, width: 100},
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: { google_account_id: null, DATE: null, PAGE_VIEWS: 0, AD_REQUESTS: 0, AD_REQUESTS_COVERAGE: 0, CLICKS: 0, AD_REQUESTS_CTR: 0, COST_PER_CLICK: 0, IMPRESSIONS_RPM: 0, ESTIMATED_EARNINGS: 0, PAGE_VIEWS_RPM: 0, IMPRESSIONS: 0, PAGE_VIEWS_CTR: 0, AD_REQUESTS_RPM: 0, IMPRESSIONS_CTR: 0, ACTIVE_VIEW_VIEWABILITY: 0 },
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
