<template>
    <!-- 对话框表单 -->
    <!-- el-form 内可以混用 el-form-item、FormItem、ba-input 等输入组件 -->
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="baTable.form.operate! == 'bill'"
        @close="baTable.toggleForm"
        destroy-on-close
        width="70%"
        @open="open"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                对账单生成
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div
                class="ba-operate-form"
                :class="'ba-' + baTable.form.operate + '-form'"
                :style="config.layout.shrink ? '':'width: calc(100% - ' + baTable.form.labelWidth! / 2 + 'px)'"
            >
                <el-form
                    class="form-generate"
                    v-if="!baTable.form.loading"
                    ref="formRef"
                    @submit.prevent=""
                    @keyup.enter="baTable.onSubmit(formRef)"
                    :model="baTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="rules"
                >


                    <el-row :gutter="15" class="thead">
                        <el-col :span="6" class="thead-title">
                            月份
                        </el-col>
                        <el-col :span="6">
                            <el-date-picker
                                class="month"
                                v-model="baTable.form.items!.month"
                                type="month"
                                @change="selectMonth"
                                placeholder="选择月份"
                            />
                        </el-col>

                    </el-row>

                    <div v-if="baTable.form.items?.domains?.length > 0">
                        <el-row :gutter="15" class="thead">
                            <el-col :span="6" class="thead-title">
                                批量设置
                            </el-col>
                            <el-col :span="6">
                                <el-input v-model="defaultCut" @input="setDefault('cut')">
                                    <template #prepend>核减比例</template>
                                </el-input>
                            </el-col>
                            <el-col :span="6">
                                <el-input v-model="defaultRate" @input="setDefault('rate')">
                                    <template #prepend>分成比例</template>
                                </el-input>
                            </el-col>
                            <el-col :span="6">
                                <el-input v-model="defaultExchange" @input="setDefault('exchange')">
                                    <template #prepend>汇率</template>
                                </el-input>
                            </el-col>
                        </el-row>

                        <el-row :gutter="15">
                            <el-col :span="6">
                                <el-input class="readonly" readonly v-for="(item, index) in baTable.form.items?.domains"
                                          :key="'domain_key_' + index" :value="item"/>
                            </el-col>
                            <el-col :span="6">
                                <el-input v-for="(item, index) in baTable.form.items?.cut" :key="'domain_cut_' + index"
                                          v-model="baTable.form.items!.cut[index]">
                                    <template #append>%</template>
                                </el-input>
                            </el-col>
                            <el-col :span="6">
                                <el-input v-for="(item, index) in baTable.form.items?.rate"
                                          :key="'domain_rate_' + index"
                                          v-model="baTable.form.items!.rate[index]">
                                    <template #append>%</template>
                                </el-input>
                            </el-col>
                            <el-col :span="6">
                                <el-input v-for="(item, index) in baTable.form.items?.exchange"
                                          :key="'domain_exchange_' + index"
                                          v-model="baTable.form.items!.exchange[index]"/>
                            </el-col>
                        </el-row>

                        <el-button type="primary" class="btn-generate" :loading="isGenerate" @click="generate">
                            生成账单
                        </el-button>
                    </div>


                </el-form>
            </div>
        </el-scrollbar>
    </el-dialog>
</template>

<script setup lang="ts">
import {ElNotification, FormInstance, FormItemRule} from 'element-plus'
import {inject, reactive, ref} from 'vue'
import {useConfig} from '/src/stores/config'
import type baTableClass from '/src/utils/baTable'
import createAxios from "/src/utils/axios";
import {AxiosPromise} from "axios";
import fileDownload from "js-file-download";

const config = useConfig()
const formRef = ref<FormInstance>()
const baTable = inject('baTable') as baTableClass
const rules: Partial<Record<string, FormItemRule[]>> = reactive({})
const defaultExchange = ref('');
const defaultCut = ref('');
const defaultRate = ref('');
const isGenerate = ref(false);
let app_ids: any[] = [];

const generate = () => {
    // 表单校验
    if (!baTable.form.items?.month) {
        ElNotification({type: 'error', message: '请填写数据月份',})
        return;
    }
    isGenerate.value = true;
    // 提交下载请求
    createAxios<any, AxiosPromise>(
        {
            url: '/admin/xpark.Domain/bill',
            method: 'post',
            data: {
                ...baTable.form.items,
                app_ids
            },
            responseType: 'blob',
        },
        {reductDataFormat: false}
    ).then((response) => {
        const disposition = response.headers['content-disposition']
        const arr = disposition.split('filename=')
        const fileName = decodeURI(arr[1])
        fileDownload(response.data, fileName)
        isGenerate.value = false
        baTable.toggleForm('')

    })
}
const open = () => {
    defaultCut.value = defaultRate.value = defaultExchange.value = '';
    app_ids = baTable.form.extend.appsList.map((item: anyObj) => item.id);
    baTable.form.items = {
        domains: [],
        cut: [],
        exchange: [],
        rate: [],
        month: '',
    };
}

const selectMonth = (e: string) => {
    baTable.form.items!.domains = [];
    baTable.form.items!.cut = [];
    baTable.form.items!.exchange = [];
    baTable.form.items!.rate = [];

    const month = new Date(e).getFullYear() + '-' + String(new Date(e).getMonth() + 1).padStart(2, '0');
    // 获取域名列表
    baTable.api.postData('monthDomains', {month, app_ids}, false).then(res => {
        res.data.domains.forEach((item, index) => {
            baTable.form.items?.domains.push(item)
            baTable.form.items?.cut.push('')
            baTable.form.items?.exchange.push('1')
            baTable.form.items?.rate.push('80')
        })
    })


}

const setDefault = (key: string) => {
    if (key == 'cut') {
        baTable.form.items?.cut.fill(defaultCut.value)
        return;
    } else if (key == 'rate') {
        baTable.form.items?.rate.fill(defaultRate.value)
        return;
    } else if (key == 'exchange') {
        baTable.form.items?.exchange.fill(defaultExchange.value)
        return;
    }

}

</script>

<style scoped lang="scss">
.form-generate {
    position: relative;

    .thead {
        font-size: 14px;
        text-align: center;
        margin-bottom: 10px;
    }

    .thead-title {
        padding-top: 6px;
    }

    .el-input {
        margin-bottom: 10px;

        :deep(.el-input__inner) {
            text-align: center;
        }

        :deep(.el-input-group__append) {
            padding: 0 8px;
        }
    }

    .readonly {
        :deep(.el-input__wrapper) {
            border: 0 !important;
            box-shadow: none !important;
        }
    }

    :deep(.el-date-editor) {
        margin-bottom: 10px;
    }

    .btn-generate {
        position: absolute;
        right: 0;
        bottom: -80px;
    }
}


</style>
