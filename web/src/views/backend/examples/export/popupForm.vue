<template>
    <!-- 对话框表单 -->
    <el-dialog class="ba-operate-dialog" :close-on-click-modal="false" :model-value="baTable.form.operate ? true : false" @close="baTable.toggleForm">
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate ? t(baTable.form.operate) : '' }}
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div
                class="ba-operate-form"
                :class="'ba-' + baTable.form.operate + '-form'"
                :style="'width: calc(100% - ' + baTable.form.labelWidth! / 2 + 'px)'"
            >
                <el-form
                    v-if="!baTable.form.loading"
                    ref="formRef"
                    @keyup.enter="baTable.onSubmit(formRef)"
                    :model="baTable.form.items"
                    label-position="right"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="rules"
                >
                    <FormItem
                        :label="t('examples.export.name')"
                        type="string"
                        v-model="baTable.form.items!.name"
                        prop="name"
                        :input-attr="{ placeholder: t('Please input field', { field: t('examples.export.name') }) }"
                    />
                    <FormItem
                        :label="t('examples.export.age')"
                        type="number"
                        prop="age"
                        v-model.number="baTable.form.items!.age"
                        :input-attr="{ step: '1', placeholder: t('Please input field', { field: t('examples.export.age') }) }"
                    />
                    <FormItem
                        :label="t('examples.export.addr')"
                        type="string"
                        v-model="baTable.form.items!.addr"
                        prop="addr"
                        :input-attr="{ placeholder: t('Please input field', { field: t('examples.export.addr') }) }"
                    />
                    <FormItem
                        :label="t('examples.export.h')"
                        type="number"
                        prop="h"
                        v-model.number="baTable.form.items!.h"
                        :input-attr="{ step: '1', placeholder: t('Please input field', { field: t('examples.export.h') }) }"
                    />
                    <FormItem
                        :label="t('examples.export.status')"
                        type="radio"
                        v-model="baTable.form.items!.status"
                        prop="status"
                        :data="{ content: { 0: t('examples.export.status 0'), 1: t('examples.export.status 1') } }"
                        :input-attr="{ placeholder: t('Please select field', { field: t('examples.export.status') }) }"
                    />
                    <FormItem
                        :label="t('examples.export.weigh')"
                        type="number"
                        prop="weigh"
                        v-model.number="baTable.form.items!.weigh"
                        :input-attr="{ step: '1', placeholder: t('Please input field', { field: t('examples.export.weigh') }) }"
                    />
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm('')">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="baTable.form.submitLoading" @click="baTable.onSubmit(formRef)" type="primary">
                    {{ baTable.form.operateIds && baTable.form.operateIds.length > 1 ? t('Save and edit next item') : t('Save') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type baTableClass from '/@/utils/baTable'
import FormItem from '/@/components/formItem/index.vue'
import type { ElForm, FormItemRule } from 'element-plus'
import { buildValidatorData } from '/@/utils/validate'

const formRef = ref<InstanceType<typeof ElForm>>()
const baTable = inject('baTable') as baTableClass

const { t } = useI18n()

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    name: [buildValidatorData({ name: 'required', title: t('examples.export.name') })],
    age: [buildValidatorData({ name: 'required', title: t('examples.export.age') })],
    addr: [buildValidatorData({ name: 'required', title: t('examples.export.addr') })],
    h: [buildValidatorData({ name: 'required', title: t('examples.export.h') })],
    status: [buildValidatorData({ name: 'required', title: t('examples.export.status') })],
    weigh: [buildValidatorData({ name: 'required', title: t('examples.export.weigh') })],
})
</script>

<style scoped lang="scss"></style>
