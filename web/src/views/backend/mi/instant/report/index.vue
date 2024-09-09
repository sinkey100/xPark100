<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['comSearch']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('mi.instant.report.quick Search Fields') })"
        >
            <el-form-item :label-width="100" label="维度">
                <el-checkbox v-model="baTable.table.filter!.dimensions!.DATE" label="日期" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.COUNTRY_CODE" label="地区" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.AD_UNIT_URL" label="URL" border/>
            </el-form-item>

        </TableHeader>

<!--        <div v-if="baTable.table.filter!.dimensions!.PAGE_URL" class="dimensions-alert">-->
<!--            <el-alert class="alert" title="由于 Google Adsense 数据限制，页面URL在部分国家/地区如果没有达到最低的展示次数要求，则不会统计细分数据，因此报表中收入看起来可能会减少。" :closable="false" type="warning" show-icon >-->
<!--            </el-alert>-->
<!--        </div>-->

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
    </div>
</template>

<script setup lang="ts">
import {onMounted, provide, reactive, ref} from 'vue'
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
const dimensions = reactive({
    DATE: true,
    AD_UNIT_URL: false,
    COUNTRY_CODE: false,
})

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/mi.instant.Report/'),
    {
        pk: 'id',
        filter:{
            limit: 20
        },
        column: [
            { label: t('mi.instant.report.DATE'), prop: 'DATE', align: 'center', render: 'datetimeAndTotal',comSearchRender: 'date', operator: 'RANGE', width: 150, timeFormat: 'yyyy-mm-dd' },
            {label: t('mi.instant.report.PAGE_URL'), prop: 'AD_UNIT_URL', align: 'center', operator: 'LIKE', sortable: false,},
            {label: t('mi.instant.report.COUNTRY_CODE'), prop: 'COUNTRY_CODE', align: 'center', operator: 'LIKE'},
            {label: t('mi.instant.report.ESTIMATED_EARNINGS'), prop: 'revenue', align: 'center', operator: false, sortable: true},
            {label: t('mi.instant.report.PAGE_VIEWS'), prop: 'PAGE_VIEWS', align: 'center', operator: false, sortable: true,},
            {label: t('mi.instant.report.AD_REQUESTS'), prop: 'AD_REQUESTS', align: 'center', operator: false, sortable: true},
            {label: t('mi.instant.report.IMPRESSIONS'), prop: 'IMPRESSIONS', align: 'center', operator: false, sortable: true},
            {label: t('mi.instant.report.AD_REQUESTS_COVERAGE'), prop: 'coverage', align: 'center', operator: false, sortable: false},
            {label: t('mi.instant.report.CLICKS'), prop: 'CLICKS', align: 'center', operator: false, sortable: true},
            {label: t('mi.instant.report.IMPRESSIONS_CTR'), prop: 'ctr', align: 'center', operator: false, sortable: false},
            {label: t('mi.instant.report.COST_PER_CLICK'), prop: 'cpc', align: 'center', operator: false, sortable: false},
            {label: t('mi.instant.report.IMPRESSIONS_RPM'), prop: 'ecpm', align: 'center', operator: false, sortable: false},
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {},
    }, {}, {
        getIndex: ({res}) => {
            baTable.table.column.forEach((item: any) => {
                if (baTable.table.filter!.dimensions[item.prop] == undefined) {
                    return;
                }
                item.show = baTable.table.filter!.dimensions[item.prop];
            })
        },
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
.table-header{
    .el-form-item{
        margin-bottom: 0;
    }
}
:deep(.table-search) {
    display: none;
}
:deep(.el-table){
    tbody{
        tr:last-child{
            background: #eaeef0 !important;
        }
    }

}
.dimensions-alert{
    background: #fff;
    padding:0 20px 15px;
    :deep(.el-alert__title){
        font-size:14px;
    }
}
</style>
