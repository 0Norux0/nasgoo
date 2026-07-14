/// <reference types="vite/client" />

// This file is a SCRIPT file (no top-level `import`/`export`), so
// `interface X {…}` here merges with the global interface X — that's
// how we extend `Window` and `ImportMetaEnv` without `declare global`.

interface Window {
    axios: import('axios').AxiosInstance;
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
