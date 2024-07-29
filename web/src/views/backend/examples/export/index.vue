<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('examples.export.quick Search Fields') })"
        >
            <el-button v-blur class="table-header-operate" type="success" style="margin-left: 12px" @click="derive">
                <Icon color="#ffffff" name="el-icon-Download" />
                <span class="table-header-operate-text">{{ t('examples.export.export') }}</span>
            </el-button>
        </TableHeader>

        <!-- 表格 -->
        <!-- 要使用`el-table`组件原有的属性，直接加在Table标签上即可 -->
        <Table ref="tableRef" />

        <!-- 表单 -->
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { ref, provide, onMounted } from 'vue'
import baTableClass from '/@/utils/baTable'
import { defaultOptButtons } from '/@/components/table'
import { baTableApi } from '/@/api/common'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import createAxios from '/@/utils/axios'
import fileDownload from 'js-file-download'
import { AxiosPromise } from 'axios'

const { t } = useI18n()
const tableRef = ref()
const optButtons = defaultOptButtons(['weigh-sort', 'edit', 'delete'])
const baTable = new baTableClass(
    new baTableApi('/admin/examples.export/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('examples.export.id'), prop: 'id', align: 'center', width: 70, sortable: 'custom', operator: 'RANGE' },
            { label: t('examples.export.name'), prop: 'name', align: 'center' },
            { label: t('examples.export.age'), prop: 'age', align: 'center', operator: 'RANGE' },
            { label: t('examples.export.addr'), prop: 'addr', align: 'center' },
            { label: t('examples.export.h'), prop: 'h', align: 'center', operator: 'RANGE' },
            {
                label: t('examples.export.status'),
                prop: 'status',
                align: 'center',
                render: 'tag',
                replaceValue: { 0: t('examples.export.status 0'), 1: t('examples.export.status 1') },
            },
            { label: t('examples.export.weigh'), prop: 'weigh', align: 'center', sortable: 'custom', operator: false },
            {
                label: t('Update time'),
                prop: 'update_time',
                align: 'center',
                render: 'datetime',
                sortable: 'custom',
                operator: 'RANGE',
                width: 160,
            },
            {
                label: t('Create time'),
                prop: 'create_time',
                align: 'center',
                render: 'datetime',
                sortable: 'custom',
                operator: 'RANGE',
                width: 160,
            },
            { label: t('Operate'), align: 'center', width: 140, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined],
        defaultOrder: { prop: 'weigh', order: 'desc' },
    },
    {
        defaultItems: { h: '0', status: '1', weigh: '0' },
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
const derive = () => {
    createAxios<any, AxiosPromise>(
        {
            url: '/admin/examples.Export/export',
            method: 'get',
            responseType: 'blob',
        },
        { reductDataFormat: false }
    ).then((response) => {
        const disposition = response.headers['content-disposition']
        const arr = disposition.split('filename=')
        const fileName = decodeURI(arr[1])
        fileDownload(response.data, fileName)
    })
}

defineOptions({
    name: 'examples/export',
})
</script>

<style scoped lang="scss"></style>
