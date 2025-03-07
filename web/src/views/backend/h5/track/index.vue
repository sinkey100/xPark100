<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh','comSearch']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('h5.track.quick Search Fields') })"
        ></TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

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
    name: 'h5/track',
})

const { t } = useI18n()
const tableRef = ref()

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/h5.Track/'),
    {
        pk: 'id',
        column: [
            { label: t('h5.track.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('h5.track.app_id'), prop: 'app_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            { label: t('h5.track.channel_id'), prop: 'channel_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            { label: t('h5.track.domain_id'), prop: 'domain_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            { label: t('h5.track.date'), prop: 'date', align: 'center', operator: 'eq', sortable: 'custom' },
            { label: t('h5.track.country_code'), prop: 'country_code', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('h5.track.event_type'), prop: 'event_type', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('h5.track.valid_events'), prop: 'valid_events', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('h5.track.invalid_events'), prop: 'invalid_events', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('h5.track.anchored_count'), prop: 'anchored_count', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('h5.track.banner_count'), prop: 'banner_count', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('h5.track.fullscreen_count'), prop: 'fullscreen_count', align: 'center', operator: 'RANGE', sortable: false },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: { app_id: null, channel_id: null, domain_id: null, date: null, country_code: null, valid_events: 0, invalid_events: 0, anchored_count: 0, banner_count: 0, fullscreen_count: 0, status: '1' },
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
