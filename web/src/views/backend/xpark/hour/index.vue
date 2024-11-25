<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info"
                  show-icon/>

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['comSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('xpark.hour.quick Search Fields') })"
        >
            <el-form-item :label-width="100" label="维度">
                <el-checkbox v-model="baTable.table.filter!.dimensions!.time_utc_0" label="日期" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.domain_id" label="域名" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.app_id" label="应用" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.country_code" label="地区" border/>
                <el-checkbox v-model="baTable.table.filter!.dimensions!.ad_placement_id" label="广告单元" border/>
                <el-checkbox v-if="adminInfo.id == 1" v-model="original" label="Original" border/>
                <!--                <el-button class="dimensions-btn" @click="onComSearch" type="primary">{{ $t('Search') }}</el-button>-->
            </el-form-item>

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
import {useAdminInfo} from '/@/stores/adminInfo'

defineOptions({
    name: 'xpark/hour',
})

const {t} = useI18n()
const tableRef = ref()
const dimensions = reactive({
    time_utc_0: true,
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
    new baTableApi('/admin/xpark.Hour/'),
    {
        pk: 'id',
        column: [
            {type: 'selection', align: 'center', operator: false},
            {
                label: t('xpark.hour.a_date'),
                prop: 'time_utc_0',
                align: 'center',
                render: 'datetimeAndTotal',
                comSearchRender: 'date',
                operator: 'RANGE',
                sortable: false,
                width: 170,
                timeFormat: 'yyyy-mm-dd hh:MM:ss',
                fixed: true
            },
            {
                label: t('xpark.hour.domain__domain'),
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
                label: t('xpark.hour.channel'),
                prop: 'channel_full',
                align: 'center',
                operatorPlaceholder: t('Click select'),
                sortable: false,
                show: false,
                minWidth: 135,
                // render: 'tag',
                operator: adminInfo.id == 1 ? 'eq' : false,
                // custom: {'xPark365': 'primary', 'BeesAds': 'warning', 'AdSense': 'danger'},
                // replaceValue: {'xPark365': t('xPark365'), 'BeesAds': t('BeesAds'), 'AdSense': t('AdSense')},
            },
            {
                label: t('xpark.hour.sub_channel'),
                prop: 'sub_channel',
                align: 'center',
                sortable: false,
                operator: false,
                show: false,
                minWidth: 180,
                fixed: true
            },
            {
                label: t('xpark.hour.admin'),
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
                label: t('xpark.hour.app'),
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
                label: t('xpark.hour.app'),
                prop: 'app_name',
                align: 'center',
                show: false,
                minWidth: 160,
                sortable: false,
                operator: false,
            },
            {
                label: t('xpark.hour.country_code'),
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
                label: t('xpark.hour.country_level'),
                prop: 'country_level',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: false,
                width: 60,
                sortable: false,
            },
            {
                label: t('xpark.hour.ad_placement_id'),
                prop: 'ad_placement_id',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                minWidth: 180,
                sortable: false
            },
            {
                label: t('xpark.hour.activity_per_display'),
                prop: 'activity_per_display',
                align: 'center',
                operator: false,
                minWidth: 90,
                sortable: false,
            },
            {
                label: t('xpark.hour.ad_revenue'),
                prop: 'ad_revenue',
                align: 'center',
                minWidth: 110,
                operator: false,
                sortable: true,
            },
            {
                label: t('xpark.hour.gross_revenue'),
                prop: 'gross_revenue',
                align: 'center',
                minWidth: 110,
                show: original.value == true,
                operator: false,
                sortable: true,
            },
            {
                label: t('xpark.hour.requests'),
                prop: 'requests',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: true,
            },
            {
                label: t('xpark.hour.fills'),
                prop: 'fills',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: true,
            },
            {
                label: t('xpark.hour.fill_rate'),
                prop: 'fill_rate',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: false,
            },
            {
                label: t('xpark.hour.impressions'),
                prop: 'impressions',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: true,
            },
            {
                label: t('xpark.hour.impressions_rate'),
                prop: 'impressions_rate',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: false,
            },

            {
                label: t('xpark.hour.clicks'),
                prop: 'clicks',
                align: 'center',
                minWidth: 100,
                operator: false,
                sortable: true,
            },

            {
                label: t('xpark.hour.click_rate'),
                prop: 'click_rate',
                align: 'center',
                minWidth: 100,
                operator: false,
                sortable: false,
            },

            {
                label: t('xpark.hour.unit_price'),
                prop: 'unit_price',
                align: 'center',
                operator: false,
                minWidth: 100,
                sortable: false,
            },
            {
                label: t('xpark.hour.raw_unit_price'),
                prop: 'raw_unit_price',
                align: 'center',
                show: original.value == true,
                operator: false,
                minWidth: 100,
                sortable: false,
            },
            {
                label: t('xpark.hour.ecpm'),
                prop: 'ecpm',
                align: 'center',
                operator: false,
                sortable: false,
            },
            {
                label: t('xpark.hour.raw_ecpm'),
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
            {
                label: '广告类型',
                show: false,
                prop: 'channel_type',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                width: 100,
                replaceValue: {
                    0: 'H5',
                    1: 'Native',
                },


            },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {time_utc_0: null, requests: 0, fills: 0, impressions: 0, clicks: 0, ad_revenue: 0, user_id: 0},
    }, {}, {
        getIndex: ({res}) => {
            baTable.table.column.forEach((item: any) => {
                // 管理员显示 original
                if (rawField.includes(item.prop)) {
                    item.show = adminInfo.id == 1 && original.value;
                    return;
                }
                // 广告单元维度不显示活跃数据
                if (['activity_per_display', 'rpm', 'raw_rpm'].includes(item.prop)) {
                    if (baTable.table.filter?.search?.some(item => item.field === 'channel_full' || item.field === 'ad_placement_id')) {
                        item.show = false;
                        return;
                    }
                    item.show = baTable.table.filter!.dimensions['ad_placement_id'] == false && baTable.table.filter!.dimensions['domain_id'] == true;
                    return;
                }
                // 管理员显示广告通道
                if (adminInfo.id == 1 && item.prop == 'channel_full') {
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
