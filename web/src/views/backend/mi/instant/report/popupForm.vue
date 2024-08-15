<template>
    <!-- 对话框表单 -->
    <!-- 建议使用 Prettier 格式化代码 -->
    <!-- el-form 内可以混用 el-form-item、FormItem、ba-input 等输入组件 -->
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        width="50%"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate ? t(baTable.form.operate) : '' }}
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div
                class="ba-operate-form"
                :class="'ba-' + baTable.form.operate + '-form'"
                :style="config.layout.shrink ? '':'width: calc(100% - ' + baTable.form.labelWidth! / 2 + 'px)'"
            >
                <el-form
                    v-if="!baTable.form.loading"
                    ref="formRef"
                    @submit.prevent=""
                    @keyup.enter="baTable.onSubmit(formRef)"
                    :model="baTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="rules"
                >
                    <FormItem :label="t('mi.instant.report.google_account_id')" type="remoteSelect" v-model="baTable.form.items!.google_account_id" prop="google_account_id" :input-attr="{ pk: 'ba_.id', field: 'name', remoteUrl: '' }" :placeholder="t('Please select field', { field: t('mi.instant.report.google_account_id') })" />
                    <FormItem :label="t('mi.instant.report.DATE')" type="datetime" v-model="baTable.form.items!.DATE" prop="DATE" :placeholder="t('Please select field', { field: t('mi.instant.report.DATE') })" />
                    <FormItem :label="t('mi.instant.report.PAGE_URL')" type="string" v-model="baTable.form.items!.PAGE_URL" prop="PAGE_URL" :placeholder="t('Please input field', { field: t('mi.instant.report.PAGE_URL') })" />
                    <FormItem :label="t('mi.instant.report.DOMAIN_NAME')" type="string" v-model="baTable.form.items!.DOMAIN_NAME" prop="DOMAIN_NAME" :placeholder="t('Please input field', { field: t('mi.instant.report.DOMAIN_NAME') })" />
                    <FormItem :label="t('mi.instant.report.PAGE_VIEWS')" type="number" prop="PAGE_VIEWS" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.PAGE_VIEWS" :placeholder="t('Please input field', { field: t('mi.instant.report.PAGE_VIEWS') })" />
                    <FormItem :label="t('mi.instant.report.AD_REQUESTS')" type="number" prop="AD_REQUESTS" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.AD_REQUESTS" :placeholder="t('Please input field', { field: t('mi.instant.report.AD_REQUESTS') })" />
                    <FormItem :label="t('mi.instant.report.AD_REQUESTS_COVERAGE')" type="number" prop="AD_REQUESTS_COVERAGE" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.AD_REQUESTS_COVERAGE" :placeholder="t('Please input field', { field: t('mi.instant.report.AD_REQUESTS_COVERAGE') })" />
                    <FormItem :label="t('mi.instant.report.CLICKS')" type="number" prop="CLICKS" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.CLICKS" :placeholder="t('Please input field', { field: t('mi.instant.report.CLICKS') })" />
                    <FormItem :label="t('mi.instant.report.AD_REQUESTS_CTR')" type="number" prop="AD_REQUESTS_CTR" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.AD_REQUESTS_CTR" :placeholder="t('Please input field', { field: t('mi.instant.report.AD_REQUESTS_CTR') })" />
                    <FormItem :label="t('mi.instant.report.COST_PER_CLICK')" type="number" prop="COST_PER_CLICK" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.COST_PER_CLICK" :placeholder="t('Please input field', { field: t('mi.instant.report.COST_PER_CLICK') })" />
                    <FormItem :label="t('mi.instant.report.IMPRESSIONS_RPM')" type="number" prop="IMPRESSIONS_RPM" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.IMPRESSIONS_RPM" :placeholder="t('Please input field', { field: t('mi.instant.report.IMPRESSIONS_RPM') })" />
                    <FormItem :label="t('mi.instant.report.ESTIMATED_EARNINGS')" type="number" prop="ESTIMATED_EARNINGS" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.ESTIMATED_EARNINGS" :placeholder="t('Please input field', { field: t('mi.instant.report.ESTIMATED_EARNINGS') })" />
                    <FormItem :label="t('mi.instant.report.PAGE_VIEWS_RPM')" type="number" prop="PAGE_VIEWS_RPM" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.PAGE_VIEWS_RPM" :placeholder="t('Please input field', { field: t('mi.instant.report.PAGE_VIEWS_RPM') })" />
                    <FormItem :label="t('mi.instant.report.IMPRESSIONS')" type="number" prop="IMPRESSIONS" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.IMPRESSIONS" :placeholder="t('Please input field', { field: t('mi.instant.report.IMPRESSIONS') })" />
                    <FormItem :label="t('mi.instant.report.PAGE_VIEWS_CTR')" type="number" prop="PAGE_VIEWS_CTR" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.PAGE_VIEWS_CTR" :placeholder="t('Please input field', { field: t('mi.instant.report.PAGE_VIEWS_CTR') })" />
                    <FormItem :label="t('mi.instant.report.AD_REQUESTS_RPM')" type="number" prop="AD_REQUESTS_RPM" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.AD_REQUESTS_RPM" :placeholder="t('Please input field', { field: t('mi.instant.report.AD_REQUESTS_RPM') })" />
                    <FormItem :label="t('mi.instant.report.IMPRESSIONS_CTR')" type="number" prop="IMPRESSIONS_CTR" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.IMPRESSIONS_CTR" :placeholder="t('Please input field', { field: t('mi.instant.report.IMPRESSIONS_CTR') })" />
                    <FormItem :label="t('mi.instant.report.ACTIVE_VIEW_VIEWABILITY')" type="number" prop="ACTIVE_VIEW_VIEWABILITY" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.ACTIVE_VIEW_VIEWABILITY" :placeholder="t('Please input field', { field: t('mi.instant.report.ACTIVE_VIEW_VIEWABILITY') })" />
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm()">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="baTable.form.submitLoading" @click="baTable.onSubmit(formRef)" type="primary">
                    {{ baTable.form.operateIds && baTable.form.operateIds.length > 1 ? t('Save and edit next item') : t('Save') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormInstance, FormItemRule } from 'element-plus'
import { inject, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import { buildValidatorData } from '/@/utils/validate'

const config = useConfig()
const formRef = ref<FormInstance>()
const baTable = inject('baTable') as baTableClass

const { t } = useI18n()

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    create_time: [buildValidatorData({ name: 'date', title: t('mi.instant.report.create_time') })],
    DATE: [buildValidatorData({ name: 'date', title: t('mi.instant.report.DATE') })],
    PAGE_VIEWS: [buildValidatorData({ name: 'number', title: t('mi.instant.report.PAGE_VIEWS') })],
    AD_REQUESTS: [buildValidatorData({ name: 'number', title: t('mi.instant.report.AD_REQUESTS') })],
    AD_REQUESTS_COVERAGE: [buildValidatorData({ name: 'number', title: t('mi.instant.report.AD_REQUESTS_COVERAGE') })],
    CLICKS: [buildValidatorData({ name: 'number', title: t('mi.instant.report.CLICKS') })],
    AD_REQUESTS_CTR: [buildValidatorData({ name: 'number', title: t('mi.instant.report.AD_REQUESTS_CTR') })],
    COST_PER_CLICK: [buildValidatorData({ name: 'number', title: t('mi.instant.report.COST_PER_CLICK') })],
    IMPRESSIONS_RPM: [buildValidatorData({ name: 'number', title: t('mi.instant.report.IMPRESSIONS_RPM') })],
    ESTIMATED_EARNINGS: [buildValidatorData({ name: 'number', title: t('mi.instant.report.ESTIMATED_EARNINGS') })],
    PAGE_VIEWS_RPM: [buildValidatorData({ name: 'number', title: t('mi.instant.report.PAGE_VIEWS_RPM') })],
    IMPRESSIONS: [buildValidatorData({ name: 'number', title: t('mi.instant.report.IMPRESSIONS') })],
    PAGE_VIEWS_CTR: [buildValidatorData({ name: 'number', title: t('mi.instant.report.PAGE_VIEWS_CTR') })],
    AD_REQUESTS_RPM: [buildValidatorData({ name: 'number', title: t('mi.instant.report.AD_REQUESTS_RPM') })],
    IMPRESSIONS_CTR: [buildValidatorData({ name: 'number', title: t('mi.instant.report.IMPRESSIONS_CTR') })],
    ACTIVE_VIEW_VIEWABILITY: [buildValidatorData({ name: 'number', title: t('mi.instant.report.ACTIVE_VIEW_VIEWABILITY') })],
})
</script>

<style scoped lang="scss"></style>
