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
                    <FormItem :label="t('xpark.apps.app_name')" type="string" v-model="baTable.form.items!.app_name" prop="app_name" :placeholder="t('Please input field', { field: t('xpark.apps.app_name') })" />
                    <FormItem :label="t('xpark.apps.pkg_name')" type="string" v-model="baTable.form.items!.pkg_name" prop="pkg_name" :placeholder="t('Please input field', { field: t('xpark.apps.pkg_name') })" />
                    <FormItem :label="t('xpark.apps.remarks')" type="string" v-model="baTable.form.items!.remarks" prop="remarks" :placeholder="t('Please input field', { field: t('xpark.apps.remarks') })" />
                    <FormItem :label="t('xpark.apps.admin_id')" type="remoteSelect" v-model="baTable.form.items!.admin_id" prop="admin_id" :input-attr="{ pk: 'ba_admin.id', field: 'nickname', remoteUrl: '/admin/auth.Admin/index' }" :placeholder="t('Please select field', { field: t('xpark.apps.admin_id') })" />
                    <FormItem :label="t('xpark.apps.cp_admin_id')" type="remoteSelect" v-model="baTable.form.items!.cp_admin_id" prop="cp_admin_id" :input-attr="{ pk: 'ba_admin.id', field: 'nickname', remoteUrl: '/admin/auth.Admin/index' }" :placeholder="t('Please select field', { field: t('xpark.apps.cp_admin_id') })" />


                    <FormItem
                        v-if="baTable.form.items!.id > 0"
                        label="授权域名"
                        v-model="baTable.form.items!.domain_arr"
                        prop="domain_arr"
                        type="remoteSelect"
                        :input-attr="{
                            multiple: true,
                            field: 'domain',
                            pk: 'domain.id',
                            remoteUrl: '/admin/xpark.domain/index',
                            placeholder: t('Click select'),
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
    createtime: [buildValidatorData({ name: 'date', title: t('xpark.apps.createtime') })],
    updatetime: [buildValidatorData({ name: 'date', title: t('xpark.apps.updatetime') })],
})
</script>

<style scoped lang="scss"></style>
