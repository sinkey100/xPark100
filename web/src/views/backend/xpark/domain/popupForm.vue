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
                    <FormItem :label="t('xpark.domain.domain')" type="string" v-model="baTable.form.items!.domain"
                              prop="domain"
                              :placeholder="t('Please input field', { field: t('xpark.domain.domain') })"/>
                    <FormItem :label="t('xpark.domain.original_domain')" type="string"
                              v-model="baTable.form.items!.original_domain" prop="original_domain"
                              :placeholder="t('Please input field', { field: t('xpark.domain.original_domain') })"/>
                    <FormItem :label="t('xpark.domain.rate')" type="number" v-model="baTable.form.items!.rate"
                              prop="rate" :placeholder="t('Please input field', { field: t('xpark.domain.rate') })"/>

                    <FormItem :label="t('xpark.domain.channel')" type="remoteSelect"
                              v-model="baTable.form.items!.channel_id" prop="channel_id"
                              :input-attr="{ pk: 'ba_xpark_channel.id', field: 'channel_alias', remoteUrl: '/admin/xpark.Channel/index' }"
                              :placeholder="t('Please select field', { field: t('xpark.domain.channel') })"/>

                    <FormItem :label="t('xpark.domain.sls_switch')" type="switch" v-model="baTable.form.items!.sls_switch" prop="sls_switch" :data="{ content: { '0': t('xpark.domain.is_ssl 0'), '1': t('xpark.domain.is_ssl 1') } }" />
                    <FormItem :label="t('xpark.domain.is_hide')" type="switch" v-model="baTable.form.items!.is_hide" prop="is_hide" :data="{ content: { '0': t('xpark.domain.is_ssl 0'), '1': t('xpark.domain.is_ssl 1') } }" />

                </el-form>

            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm()">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="baTable.form.submitLoading" @click="baTable.onSubmit(formRef)"
                           type="primary">
                    {{
                        baTable.form.operateIds && baTable.form.operateIds.length > 1 ? t('Save and edit next item') : t('Save')
                    }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type {FormInstance, FormItemRule} from 'element-plus'
import {inject, reactive, ref} from 'vue'
import {useI18n} from 'vue-i18n'
import FormItem from '/@/components/formItem/index.vue'
import {useConfig} from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import {buildValidatorData} from '/@/utils/validate'

const config = useConfig()
const formRef = ref<FormInstance>()
const baTable = inject('baTable') as baTableClass

const {t} = useI18n()

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    domain: [buildValidatorData({name: 'required', title: t('xpark.domain.domain')})],
    original_domain: [buildValidatorData({name: 'required', title: t('xpark.domain.original_domain')})],
    rate: [buildValidatorData({name: 'required', title: t('xpark.domain.rate')})],
    channel: [buildValidatorData({name: 'required', title: t('xpark.domain.channel')})],
    flag: [buildValidatorData({name: 'required', title: t('xpark.domain.flag')})],
})
</script>

<style scoped lang="scss"></style>
