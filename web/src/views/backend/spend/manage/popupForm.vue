<template>
    <!-- 对话框表单 -->
    <!-- 建议使用 Prettier 格式化代码 -->
    <!-- el-form 内可以混用 el-form-item、FormItem、ba-input 等输入组件 -->
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="baTable.form.operate! == 'Budget'"
        @close="baTable.toggleForm"
        width="35%"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
               预算调整
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
                    <FormItem label="域名" type="string" v-model="baTable.form.items!.domain_name" prop="domain_name" :input-attr="{disabled:true}"/>
                    <FormItem :label="t('spend.manage.campaign_id')" type="string" prop="campaign_id" v-model="baTable.form.items!.campaign_id" :input-attr="{disabled:true}" />
                    <FormItem :label="t('spend.manage.campaign_name')" type="string" prop="campaign_name" v-model="baTable.form.items!.campaign_name" :input-attr="{disabled:true}" />
                    <FormItem :label="t('spend.manage.budget')" type="string" prop="budget" v-model.number="baTable.form.items!.budget" />
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
