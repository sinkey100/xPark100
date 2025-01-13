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
                    <FormItem :label="t('spend.data.app_id')" type="remoteSelect" v-model="baTable.form.items!.app_id" prop="app_id" :input-attr="{ pk: 'ba_.id', field: 'name', remoteUrl: '' }" :placeholder="t('Please select field', { field: t('spend.data.app_id') })" />
                    <FormItem :label="t('spend.data.channel_name')" type="string" v-model="baTable.form.items!.channel_name" prop="channel_name" :placeholder="t('Please input field', { field: t('spend.data.channel_name') })" />
                    <FormItem :label="t('spend.data.domain_id')" type="remoteSelect" v-model="baTable.form.items!.domain_id" prop="domain_id" :input-attr="{ pk: 'ba_.id', field: 'name', remoteUrl: '' }" :placeholder="t('Please select field', { field: t('spend.data.domain_id') })" />
                    <FormItem :label="t('spend.data.is_app')" type="number" v-model="baTable.form.items!.is_app" prop="is_app" :input-attr="{ content: { '0': t('spend.data.is_app 0'), '1': t('spend.data.is_app 1') } }" :placeholder="t('Please input field', { field: t('spend.data.is_app') })" />
                    <FormItem :label="t('spend.data.date')" type="date" v-model="baTable.form.items!.date" prop="date" :placeholder="t('Please select field', { field: t('spend.data.date') })" />
                    <FormItem :label="t('spend.data.country_code')" type="string" v-model="baTable.form.items!.country_code" prop="country_code" :placeholder="t('Please input field', { field: t('spend.data.country_code') })" />
                    <FormItem :label="t('spend.data.spend')" type="number" prop="spend" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.spend" :placeholder="t('Please input field', { field: t('spend.data.spend') })" />
                    <FormItem :label="t('spend.data.clicks')" type="number" prop="clicks" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.clicks" :placeholder="t('Please input field', { field: t('spend.data.clicks') })" />
                    <FormItem :label="t('spend.data.impressions')" type="number" prop="impressions" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.impressions" :placeholder="t('Please input field', { field: t('spend.data.impressions') })" />
                    <FormItem :label="t('spend.data.install')" type="number" prop="install" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.install" :placeholder="t('Please input field', { field: t('spend.data.install') })" />
                    <FormItem :label="t('spend.data.campaign_name')" type="string" v-model="baTable.form.items!.campaign_name" prop="campaign_name" :placeholder="t('Please input field', { field: t('spend.data.campaign_name') })" />
                    <FormItem :label="t('spend.data.cpc')" type="number" prop="cpc" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.cpc" :placeholder="t('Please input field', { field: t('spend.data.cpc') })" />
                    <FormItem :label="t('spend.data.cpm')" type="number" prop="cpm" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.cpm" :placeholder="t('Please input field', { field: t('spend.data.cpm') })" />
                    <FormItem :label="t('spend.data.status')" type="radio" v-model="baTable.form.items!.status" prop="status" :input-attr="{ content: {} }" :placeholder="t('Please select field', { field: t('spend.data.status') })" />
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
    is_app: [buildValidatorData({ name: 'number', title: t('spend.data.is_app') })],
    date: [buildValidatorData({ name: 'date', title: t('spend.data.date') })],
    spend: [buildValidatorData({ name: 'number', title: t('spend.data.spend') })],
    clicks: [buildValidatorData({ name: 'number', title: t('spend.data.clicks') })],
    impressions: [buildValidatorData({ name: 'number', title: t('spend.data.impressions') })],
    install: [buildValidatorData({ name: 'number', title: t('spend.data.install') })],
    cpc: [buildValidatorData({ name: 'number', title: t('spend.data.cpc') })],
    cpm: [buildValidatorData({ name: 'number', title: t('spend.data.cpm') })],
})
</script>

<style scoped lang="scss"></style>
