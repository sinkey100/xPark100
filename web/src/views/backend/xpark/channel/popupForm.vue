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
                    <FormItem :label="t('xpark.channel.ad_type')" type="radio" v-model="baTable.form.items!.ad_type" prop="ad_type" :input-attr="{ content: { H5: t('xpark.channel.ad_type H5'), Native: t('xpark.channel.ad_type Native') } }" :placeholder="t('Please select field', { field: t('xpark.channel.ad_type') })" />
                    <FormItem :label="t('xpark.channel.channel_type')" type="string" v-model="baTable.form.items!.channel_type" prop="channel_type" :placeholder="t('Please input field', { field: t('xpark.channel.channel_type') })" />
                    <FormItem :label="t('xpark.channel.channel_account')" type="string" v-model="baTable.form.items!.channel_account" prop="channel_account" :placeholder="t('Please input field', { field: t('xpark.channel.channel_account') })" />
                    <FormItem :label="t('xpark.channel.channel_alias')" type="string" v-model="baTable.form.items!.channel_alias" prop="channel_alias" :placeholder="t('Please input field', { field: t('xpark.channel.channel_alias') })" />

                    <el-form-item label="模型配置">
                        <el-row>
                            <el-col :span="8">
                                <el-input v-model="baTable.form.items!.spend_model">
                                    <template #prepend>{{ t('xpark.channel.spend_model') }}</template>
                                </el-input>
                            </el-col>
                            <el-col :span="8">
                                <el-input v-model="baTable.form.items!.revenue_model">
                                    <template #prepend>{{ t('xpark.channel.revenue_model') }}</template>
                                </el-input>
                            </el-col>
                            <el-col :span="8">
                                <el-input v-model="baTable.form.items!.user_model">
                                    <template #prepend>{{ t('xpark.channel.user_model') }}</template>
                                </el-input>
                            </el-col>
                        </el-row>
                    </el-form-item>

                    <FormItem :label="t('xpark.channel.is_own')" type="switch" v-model="baTable.form.items!.is_own" prop="is_own" :data="{ content: { '0': t('xpark.domain.is_ssl 0'), '1': t('xpark.domain.is_ssl 1') } }" />
                    <FormItem :label="t('xpark.channel.private_switch')" type="switch" v-model="baTable.form.items!.private_switch" prop="private_switch" :data="{ content: { '0': t('xpark.domain.is_ssl 0'), '1': t('xpark.domain.is_ssl 1') } }" />
                    <FormItem
                        :label="t('xpark.channel.timezone')"
                        v-model="baTable.form.items!.timezone"
                        prop="timezone"
                        type="radio"
                        :input-attr="{
                            border: true,
                            content: { 0: 'UTC 0', 8:'UTC+8' },
                        }"
                    />



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
    create_time: [buildValidatorData({ name: 'date', title: t('xpark.channel.create_time') })],
    update_time: [buildValidatorData({ name: 'date', title: t('xpark.channel.update_time') })],
})
</script>

<style scoped lang="scss"></style>
