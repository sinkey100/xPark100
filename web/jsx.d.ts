import { VNode } from 'vue';

declare global {
    namespace JSX {
        // 表示 JSX 元素返回的是 Vue 的 VNode 类型
        interface Element extends VNode {}
        // 定义组件类的 props 类型（简单示例）
        interface ElementClass {
            $props: any;
        }
        // 允许使用任意的内置标签，并设置为 any 类型
        interface IntrinsicElements {
            [elem: string]: any;
        }
    }
}
