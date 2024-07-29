<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info"
                  show-icon/>

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['comSearch']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('xpark.data.quick Search Fields') })"
        >
            <el-form-item :label-width="100" label="维度">
                <el-checkbox v-model="baTable.table.filter!.dimensions!.a_date" label="日期" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.sub_channel" label="子渠道" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.country_code" label="地区" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.ad_placement_id" label="广告单元" border/>
                <!--                <el-button class="dimensions-btn" @click="onComSearch" type="primary">{{ $t('Search') }}</el-button>-->
            </el-form-item>
            <el-button class="table-header-operate btn-export" type="success" @click="derive">
                <Icon color="#ffffff" name="el-icon-Download" />
                <span class="table-header-operate-text">导出</span>
            </el-button>
        </TableHeader>


        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
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
import {getArrayKey} from "/@/utils/common";
import createAxios from "/@/utils/axios";
import {AxiosPromise} from "axios";
import fileDownload from "js-file-download";

defineOptions({
    name: 'xpark/data',
})

const {t} = useI18n()
const tableRef = ref()
const dimensions = reactive({
    a_date: true,
    sub_channel: true,
    country_code: true,
    ad_placement_id: true
})

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/xpark.Data/'),
    {
        pk: 'id',
        column: [
            {type: 'selection', align: 'center', operator: false},
            {
                label: t('xpark.data.a_date'),
                prop: 'a_date',
                align: 'center',
                render: 'datetime',
                comSearchRender: 'date',
                operator: 'RANGE',
                sortable: 'custom',
                width: 140,
                timeFormat: 'yyyy-mm-dd'
            },
            {
                label: t('xpark.data.domain__domain'),
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
                    field: 'domain',
                }
            },
            {
                label: t('xpark.data.sub_channel'),
                prop: 'sub_channel',
                align: 'center',
                sortable: false,
                operator: false
            },
            {
                label: t('xpark.data.country_code'),
                prop: 'country_code',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                width: 80,
            },
            {
                label: t('xpark.data.ad_placement_id'),
                prop: 'ad_placement_id',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false
            },
            {
                label: t('xpark.data.requests'),
                prop: 'requests',
                align: 'center',
                operator: false,
                sortable: false,
                width: 120
            },
            {
                label: t('xpark.data.fills'),
                prop: 'fills',
                align: 'center',
                operator: false,
                sortable: false,
                width: 120
            },
            {
                label: t('xpark.data.impressions'),
                prop: 'impressions',
                align: 'center',
                operator: false,
                sortable: false,
                width: 120
            },
            {
                label: t('xpark.data.clicks'),
                prop: 'clicks',
                align: 'center',
                operator: false,
                sortable: false,
                width: 120
            },
            {
                label: t('xpark.data.ad_revenue'),
                prop: 'ad_revenue',
                align: 'center',
                operator: false,
                sortable: false,
                width: 120
            },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {a_date: null, requests: 0, fills: 0, impressions: 0, clicks: 0, ad_revenue: 0, user_id: 0},
    }, {}, {
        getIndex: ({ res }) => {
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

const derive = () => {
    createAxios<any, AxiosPromise>(
        {
            url: '/admin/xpark.data/export',
            method: 'get',
            params: baTable.table.filter,

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
</script>

<style scoped lang="scss">
.dimensions-btn {
    margin-left: 30px;
}
.btn-export{
    position: absolute;
    right:70px;
    top:22px;
}
</style>
