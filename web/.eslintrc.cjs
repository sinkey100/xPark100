module.exports = {
    root: true,
    env: {
        browser: true,
        es2021: true,
        node: true,
        'vue/setup-compiler-macros': true,
    },
    parser: 'vue-eslint-parser',
    parserOptions: {
        ecmaVersion: 12,
        parser: '@typescript-eslint/parser',
        sourceType: 'module',
        ecmaFeatures: {
            jsx: true
        }
    },
    extends: ['plugin:vue/vue3-essential', 'plugin:vue/vue3-recommended', 'plugin:@typescript-eslint/recommended', 'prettier'],
    plugins: ['vue', '@typescript-eslint'],
    rules: {
        // http://eslint.cn/docs/rules/
        // https://eslint.vuejs.org/rules/
        '@typescript-eslint/no-empty-function': 'off',
        '@typescript-eslint/no-explicit-any': 'off',
        '@typescript-eslint/no-non-null-assertion': 'off',
        '@typescript-eslint/ban-types': 'off',
        '@typescript-eslint/ban-ts-ignore': 'off',
        '@typescript-eslint/no-unused-vars': [
            'warn',
            {
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_',
            },
        ],
        'no-unused-vars': [
            'warn',
            {
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_',
            },
        ],
        'vue/v-on-event-hyphenation': 'off',
        'vue/custom-event-name-casing': 'off',
        'vue/component-definition-name-casing': 'off',
        'vue/attributes-order': 'off',
        'vue/one-component-per-file': 'off',
        'vue/html-closing-bracket-newline': 'off',
        'vue/max-attributes-per-line': 'off',
        'vue/multiline-html-element-content-newline': 'off',
        'vue/singleline-html-element-content-newline': 'off',
        'vue/attribute-hyphenation': 'off',
        'vue/html-self-closing': 'off',
        'vue/require-default-prop': 'off',
        'vue/no-arrow-functions-in-watch': 'off',
        'vue/no-v-html': 'off',
        'vue/comment-directive': 'off',
        'vue/multi-word-component-names': 'off',
        'vue/require-prop-types': 'off',
        'no-useless-escape': 'off',
        'no-sparse-arrays': 'off',
        'no-prototype-builtins': 'off',
        'no-use-before-define': 'off',
        'no-case-declarations': 'off',
        'no-console': 'off',
        'vue/html-indent': 'off',
        indent: [1, 4, { SwitchCase: 1 }],
    },
    globals: {
        window: true,
        NodeJS: true,
    },
}
