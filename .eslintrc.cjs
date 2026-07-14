/* ESLint 8.x config (legacy format, matches package.json eslint ^8.57) */
module.exports = {
    root: true,
    env: {
        browser: true,
        es2022: true,
        node: true,
    },
    extends: [
        'eslint:recommended',
        'plugin:@typescript-eslint/recommended',
        'plugin:react/recommended',
        'plugin:react/jsx-runtime',
        'plugin:react-hooks/recommended',
    ],
    parser: '@typescript-eslint/parser',
    parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
        ecmaFeatures: { jsx: true },
    },
    plugins: ['react', 'react-hooks', '@typescript-eslint'],
    settings: {
        react: { version: '18.3' },
    },
    rules: {
        // React 18 with the new JSX transform — no need to import React
        'react/react-in-jsx-scope': 'off',
        'react/prop-types': 'off',

        // TypeScript handles unused vars better than the base rule
        'no-unused-vars': 'off',
        '@typescript-eslint/no-unused-vars': [
            'warn',
            {
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_',
                ignoreRestSiblings: true,
            },
        ],

        // Allow `any` while the codebase is being built out (Phase 1+ will tighten)
        '@typescript-eslint/no-explicit-any': 'warn',

        // Module augmentation for Inertia uses empty extending interfaces
        '@typescript-eslint/no-empty-interface': ['error', { allowSingleExtends: true }],

        // Console allowed for warnings/errors, not casual logging
        'no-console': ['warn', { allow: ['warn', 'error'] }],
    },
    ignorePatterns: [
        'public/build',
        'public/hot',
        'vendor',
        'node_modules',
        'storage',
        '*.d.ts',
        'vite.config.ts',
        'tailwind.config.js',
        'postcss.config.js',
    ],
};
