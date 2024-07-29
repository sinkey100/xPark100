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
                    <FormItem :label="t('xpark.data.domain_id')" type="remoteSelect" v-model="baTable.form.items!.domain_id" prop="domain_id" :input-attr="{ pk: 'ba_xpark_domain.id', field: 'domain', remoteUrl: '/admin/xpark.Domain/index' }" :placeholder="t('Please select field', { field: t('xpark.data.domain_id') })" />
                    <FormItem :label="t('xpark.data.a_date')" type="datetime" v-model="baTable.form.items!.a_date" prop="a_date" :placeholder="t('Please select field', { field: t('xpark.data.a_date') })" />
                    <FormItem :label="t('xpark.data.country_code')" type="string" v-model="baTable.form.items!.country_code" prop="country_code" :placeholder="t('Please input field', { field: t('xpark.data.country_code') })" />
                    <FormItem :label="t('xpark.data.sub_channel')" type="string" v-model="baTable.form.items!.sub_channel" prop="sub_channel" :placeholder="t('Please input field', { field: t('xpark.data.sub_channel') })" />
                    <FormItem :label="t('xpark.data.ad_placement_id')" type="string" v-model="baTable.form.items!.ad_placement_id" prop="ad_placement_id" :placeholder="t('Please input field', { field: t('xpark.data.ad_placement_id') })" />
                    <FormItem :label="t('xpark.data.requests')" type="number" prop="requests" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.requests" :placeholder="t('Please input field', { field: t('xpark.data.requests') })" />
                    <FormItem :label="t('xpark.data.fills')" type="number" prop="fills" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.fills" :placeholder="t('Please input field', { field: t('xpark.data.fills') })" />
                    <FormItem :label="t('xpark.data.impressions')" type="number" prop="impressions" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.impressions" :placeholder="t('Please input field', { field: t('xpark.data.impressions') })" />
                    <FormItem :label="t('xpark.data.clicks')" type="number" prop="clicks" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.clicks" :placeholder="t('Please input field', { field: t('xpark.data.clicks') })" />
                    <FormItem :label="t('xpark.data.ad_revenue')" type="number" prop="ad_revenue" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.ad_revenue" :placeholder="t('Please input field', { field: t('xpark.data.ad_revenue') })" />
                    <FormItem :label="t('xpark.data.user_id')" type="number" prop="user_id" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.user_id" :placeholder="t('Please input field', { field: t('xpark.data.user_id') })" />
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
    a_date: [buildValidatorData({ name: 'date', title: t('xpark.data.a_date') })],
    requests: [buildValidatorData({ name: 'number', title: t('xpark.data.requests') })],
    fills: [buildValidatorData({ name: 'number', title: t('xpark.data.fills') })],
    impressions: [buildValidatorData({ name: 'number', title: t('xpark.data.impressions') })],
    clicks: [buildValidatorData({ name: 'number', title: t('xpark.data.clicks') })],
    ad_revenue: [buildValidatorData({ name: 'number', title: t('xpark.data.ad_revenue') })],
})
</script>

<style scoped lang="scss"></style>
