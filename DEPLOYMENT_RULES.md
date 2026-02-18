# 🚀 Regras de Deploy e Atualização de Versão

Este documento define o processo EXATO que deve ser seguido toda vez que uma alteração for feita no Frontend (PWA) para garantir que os usuários recebam a atualização imediatamente, limpando o cache antigo.

## 📋 Checklist de Atualização

Sempre que você modificar arquivos CSS, JS ou HTML, siga estes passos na ordem:

### 1. Atualizar Versão no `index.html`

Edite o arquivo `frontend-pwa/index.html`:

1.  **CSS**: Mude o parâmetro `?v=XX` no link do CSS.
    ```html
    <!-- Antes -->
    <link rel="stylesheet" href="assets/css/style-v5.css?v=42">
    <!-- Depois -->
    <link rel="stylesheet" href="assets/css/style-v5.css?v=43">
    ```

2.  **JavaScript**: Mude o parâmetro `?v=XX` nas tags de script `app-v5.js` e `api-v5.js`.
    > **IMPORTANTE**: Se você não mudar aqui, o navegador vai baixar o JS antigo do cache!
    ```html
    <!-- Antes -->
    <script src="assets/js/api-v5.js?v=42"></script>
    <script src="assets/js/app-v5.js?v=42"></script>
    <!-- Depois -->
    <script src="assets/js/api-v5.js?v=43"></script>
    <script src="assets/js/app-v5.js?v=43"></script>
    ```

3.  **Rodapé**: Atualize o texto visual da versão no final do arquivo.
    ```html
    <div>Versão: v43</div>
    ```

### 2. Atualizar Service Worker (`sw.js`)

Edite o arquivo `frontend-pwa/sw.js`:

1.  **Nome do Cache**: Incremente o número da versão na variável `CACHE_NAME`.
    ```javascript
    // Antes
    const CACHE_NAME = 'zelo-cache-v42';
    // Depois
    const CACHE_NAME = 'zelo-cache-v43';
    ```

2.  **Lista de Assets**: Atualize os caminhos dos arquivos CSS e JS para incluir o **mesmo parâmetro de versão** que você colocou no `index.html`.
    ```javascript
    const ASSETS_TO_CACHE = [
        './',
        './index.html',
        './manifest.json',
        './assets/css/style-v5.css?v=43', // <--- Atualize aqui
        './assets/js/app-v5.js?v=43',     // <--- Atualize aqui
        './assets/js/api-v5.js?v=43',     // <--- Atualize aqui
        // ...
    ];
    ```

## ⚠️ Por que isso é necessário?

O Zelo funciona "Offline First". Isso significa que o Service Worker salva tudo no celular do usuário. Se você mudar o código mas não mudar o **nome do arquivo** (via query string `?v=XX`) E o **nome do cache**, o navegador vai achar que nada mudou e continuar servindo a versão antiga por dias ou semanas.

**Regra de Ouro:**
Mudou código? -> Sobe versão em TUDO (`index.html` + `sw.js`).
