<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info"
                  show-icon/>

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['comSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('xpark.data.quick Search Fields') })"
        >
            <el-form-item :label-width="100" label="维度">
                <el-checkbox v-model="baTable.table.filter!.dimensions!.a_date" label="日期" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.domain_id" label="域名" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.app_id" label="应用" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.country_code" label="地区" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.ad_placement_id" label="广告单元" border/>
                <el-checkbox v-if="adminInfo.id == 1" v-model="original" label="Original" border/>
                <!--                <el-button class="dimensions-btn" @click="onComSearch" type="primary">{{ $t('Search') }}</el-button>-->
            </el-form-item>
            <el-popconfirm title="是否确认导出？" @confirm="derive">
                <template #reference>
                    <el-button class="table-header-operate btn-export" type="default">
                        <Icon style="color:#333!important;" name="el-icon-Download"/>
                    </el-button>
                </template>
            </el-popconfirm>

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
import {useAdminInfo} from '/@/stores/adminInfo'

defineOptions({
    name: 'xpark/data',
})

const {t} = useI18n()
const tableRef = ref()
const dimensions = reactive({
    a_date: true,
    domain_id: false,
    app_id: false,
    country_code: false,
    ad_placement_id: false
})

const adminInfo = useAdminInfo();
const original = ref(false)
const rawField = ['gross_revenue', 'raw_ecpm', 'raw_unit_price', 'raw_rpm'];

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
                render: 'datetimeAndTotal',
                comSearchRender: 'date',
                operator: 'RANGE',
                sortable: false,
                width: 110,
                timeFormat: 'yyyy-mm-dd',
                fixed: true
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
                    field: 'full_name',
                }
            },
            {
                label: t('xpark.data.channel'),
                prop: 'channel',
                align: 'center',
                operatorPlaceholder: t('Click select'),
                sortable: false,
                show: false,
                minWidth: 100,
                // render: 'tag',
                operator: adminInfo.id == 1 ? 'eq' : false,
                // custom: {'xPark365': 'primary', 'BeesAds': 'warning', 'AdSense': 'danger'},
                // replaceValue: {'xPark365': t('xPark365'), 'BeesAds': t('BeesAds'), 'AdSense': t('AdSense')},
            },
            {
                label: t('xpark.data.sub_channel'),
                prop: 'sub_channel',
                align: 'center',
                sortable: false,
                operator: false,
                show: false,
                minWidth: 180,
                fixed: true
            },
            {
                label: t('xpark.data.admin'),
                prop: 'admin',
                align: 'center',
                sortable: false,
                show: adminInfo.id == 1,
                minWidth: 120,
                operator: adminInfo.id == 1 ? 'eq' : false,
                // operator: false,
                comSearchRender: 'remoteSelect',
                remote: {
                    pk: 'id',
                    remoteUrl: 'admin/auth.Admin/index',
                    field: 'nickname',
                }
            },
            {
                label: t('xpark.data.app'),
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
                label: t('xpark.data.app'),
                prop: 'app_name',
                align: 'center',
                show: false,
                minWidth: 160,
                sortable: false,
                operator: false,
            },
            {
                label: t('xpark.data.country_code'),
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
                label: t('xpark.data.country_level'),
                prop: 'country_level',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: false,
                width: 60,
                sortable: false,
            },
            {
                label: t('xpark.data.ad_placement_id'),
                prop: 'ad_placement_id',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                minWidth: 180,
                sortable: false
            },
            {
                label: t('xpark.data.activity_page_views'),
                prop: 'activity_page_views',
                align: 'center',
                operator: false,
                minWidth: 70,
                sortable: false,
            },
            {
                label: t('xpark.data.activity_new_users'),
                prop: 'activity_new_users',
                align: 'center',
                operator: false,
                minWidth: 70,
                sortable: false,
            },
            {
                label: t('xpark.data.activity_active_users'),
                prop: 'activity_active_users',
                align: 'center',
                operator: false,
                minWidth: 70,
                sortable: false,
            },
            {
                label: t('xpark.data.ad_revenue'),
                prop: 'ad_revenue',
                align: 'center',
                minWidth: 110,
                operator: false,
                sortable: true,
            },
            {
                label: t('xpark.data.gross_revenue'),
                prop: 'gross_revenue',
                align: 'center',
                minWidth: 110,
                show: original.value == true,
                operator: false,
                sortable: true,
            },
            {
                label: t('xpark.data.requests'),
                prop: 'requests',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: true,
            },
            {
                label: t('xpark.data.fills'),
                prop: 'fills',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: true,
            },
            {
                label: t('xpark.data.fill_rate'),
                prop: 'fill_rate',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: false,
            },
            {
                label: t('xpark.data.impressions'),
                prop: 'impressions',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: true,
            },
            {
                label: t('xpark.data.impressions_rate'),
                prop: 'impressions_rate',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: false,
            },

            {
                label: t('xpark.data.clicks'),
                prop: 'clicks',
                align: 'center',
                minWidth: 100,
                operator: false,
                sortable: true,
            },

            {
                label: t('xpark.data.click_rate'),
                prop: 'click_rate',
                align: 'center',
                minWidth: 100,
                operator: false,
                sortable: false,
            },

            {
                label: t('xpark.data.unit_price'),
                prop: 'unit_price',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: false,
            },
            {
                label: t('xpark.data.raw_unit_price'),
                prop: 'raw_unit_price',
                align: 'center',
                show: original.value == true,
                operator: false,
                minWidth: 100,
                sortable: false,
            },
            {
                label: t('xpark.data.ecpm'),
                prop: 'ecpm',
                align: 'center',
                operator: false,
                sortable: false,
            },
            {
                label: t('xpark.data.raw_ecpm'),
                prop: 'raw_ecpm',
                align: 'center',
                minWidth: 100,
                show: original.value == true,
                operator: false,
                sortable: false,
            },
            {
                label: 'RPM',
                prop: 'rpm',
                align: 'center',
                minWidth: 100,
                operator: false,
                sortable: false,
            },
            {
                label: '原始RPM',
                prop: 'raw_rpm',
                align: 'center',
                show: original.value == true,
                minWidth: 100,
                operator: false,
                sortable: false,
            },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {a_date: null, requests: 0, fills: 0, impressions: 0, clicks: 0, ad_revenue: 0, user_id: 0},
    }, {}, {
        getIndex: ({res}) => {
            baTable.table.column.forEach((item: any) => {
                // 广告单元维度不显示活跃数据
                if (['activity_page_views', 'activity_new_users', 'activity_active_users', 'rpm', 'raw_rpm'].includes(item.prop)) {
                    if (baTable.table.filter?.search?.some(item => item.field === 'channel' || item.field === 'ad_placement_id')) {
                        item.show = false;
                        return;
                    }
                    item.show = baTable.table.filter!.dimensions['ad_placement_id'] == false && baTable.table.filter!.dimensions['domain_id'] == true;
                    return;
                }
                // 管理员显示 original
                if (adminInfo.id == 1 && rawField.includes(item.prop)) {
                    item.show = original.value;
                    return;
                }
                // 管理员显示广告通道
                if (adminInfo.id == 1 && item.prop == 'channel') {
                    item.show = dimensions.domain_id;
                    return;
                }
                // 管理员显示用户
                if (adminInfo.id == 1 && item.prop == 'admin') {
                    item.show = dimensions.domain_id;
                    return;
                }
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
                if (item.prop == 'sub_channel') {
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
        {reductDataFormat: false}
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
