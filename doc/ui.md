## 前端UI

前端框架使用 [Spectre.css][spectre] 作为 CSS 基础，配合 [HTMX][htmx] 实现动态交互。

[spectre]: https://picturepan2.github.io/spectre/index.html
[htmx]: https://htmx.org/

根据项目需要，也可以完全使用另外的前端框架或方案代替。

## HTMX 示例

点击按钮加载服务器时间（toast 右上角的 × 按钮可关闭）：

```html
<button class="btn btn-primary"
        hx-get="/doc/htmx_time"
        hx-target="#time-result"
        hx-trigger="click">
  🕐 获取服务器时间
</button>
```

<div class="mt-2">
<button class="btn btn-primary"
        hx-get="/doc/htmx_time"
        hx-target="#time-result"
        hx-trigger="click">
  🕐 获取服务器时间
</button>
</div>
<div id="time-result" class="mt-2"></div>

端点代码（`source/control/doc.php`）：

<div class="code-block" hx-get="/doc/htmx_source?type=controller&name=htmx_time" hx-trigger="load">加载中...</div>

视图模板（`source/view/clue/toast.php`）：

<div class="code-block" hx-get="/doc/htmx_source?type=view&name=toast" hx-trigger="load">加载中...</div>

关闭功能由 `asset/js/spectre.js` 中的全局函数 `closeToast()` 实现。

## HTMX + Modal 示例

点击按钮弹出 Modal 显示服务器时间（点击遮罩层或关闭按钮可关闭）：

```html
<button class="btn btn-primary"
        hx-get="/doc/htmx_modal_time"
        hx-target="#modal-root"
        hx-trigger="click">
  📋 弹出服务器时间
</button>
<div id="modal-root"></div>
```

<div class="mt-2">
<button class="btn btn-primary"
        hx-get="/doc/htmx_modal_time"
        hx-target="#modal-root"
        hx-trigger="click">
  📋 弹出服务器时间
</button>
</div>
<div id="modal-root"></div>

端点代码（`source/control/doc.php`）：

<div class="code-block" hx-get="/doc/htmx_source?type=controller&name=htmx_modal_time" hx-trigger="load">加载中...</div>

视图模板（`source/view/clue/modal.php`）：

<div class="code-block" hx-get="/doc/htmx_source?type=view&name=modal" hx-trigger="load">加载中...</div>

关闭逻辑同样收敛在 `spectre.js` 的 `closeModal()` 中。
遮罩层、× 按钮、底部按钮三者共用同一函数。
注意：Modal 默认带有 `active` 类，因为 Spectre.css 用 `.modal.active` 控制可见性。
使用 `#modal-root` 作为目标容器，方便多次弹出时替换上次内容。

## HTMX + Pagination 分页

使用 `Clue\UI\Pagination` 后端类生成分页导航，配合 HTMX Boost
（`hx-boost="true"` + `hx-target="#pagination-result"`）让分页链接通过 AJAX 局部刷新。

```html
<button class="btn btn-primary"
        hx-get="/doc/htmx_pagination?p=1"
        hx-target="#pagination-result"
        hx-trigger="click">
  📄 显示分页示例
</button>
<div id="pagination-result"></div>
```

<div class="mt-2">
<button class="btn btn-primary"
        hx-get="/doc/htmx_pagination?p=1"
        hx-target="#pagination-result"
        hx-trigger="click">
  📄 显示分页示例
</button>
</div>
<div id="pagination-result"></div>

端点代码（`source/control/doc.php`）：

<div class="code-block" hx-get="/doc/htmx_source?type=controller&name=htmx_pagination" hx-trigger="load">加载中...</div>

视图模板（`source/view/clue/pagination.php`）：

<div class="code-block" hx-get="/doc/htmx_source?type=view&name=pagination" hx-trigger="load">加载中...</div>

`Pagination::render()` 内部使用 `source/view/clue/pagination.php` 视图模板，
输出的 `<ul class="pagination">` 与 Spectre.css 的分页样式天然兼容。
外层包裹 `hx-boost="true" hx-target="#pagination-result"` 后，
无需修改 Pagination 源码即可让所有翻页链接通过 AJAX 局部刷新。

## HTMX + Tab 标签页

点击标签切换面板内容，仅面板区域通过 HTMX 刷新，标签激活态由 `spectre.js` 的 `switchTab()` 处理：

```html
<ul class="tab tab-block">
  <li class="tab-item active" id="tab-server">
    <a href="#"
       hx-get="/doc/htmx_tab?tab=server"
       hx-target="#tab-panel"
       hx-on:click="switchTab(this, 'tab-server')">🌐 服务器</a>
  </li>
  <li class="tab-item" id="tab-php">
    <a href="#"
       hx-get="/doc/htmx_tab?tab=php"
       hx-target="#tab-panel"
       hx-on:click="switchTab(this, 'tab-php')">🐘 PHP</a>
  </li>
  <li class="tab-item" id="tab-clue">
    <a href="#"
       hx-get="/doc/htmx_tab?tab=clue"
       hx-target="#tab-panel"
       hx-on:click="switchTab(this, 'tab-clue')">🔧 Clue</a>
  </li>
</ul>
<div id="tab-panel" class="p-2">选择一个标签页查看信息</div>
```

<ul class="tab tab-block">
  <li class="tab-item active" id="tab-server">
    <a href="#"
       hx-get="/doc/htmx_tab?tab=server"
       hx-target="#tab-panel"
       hx-on:click="switchTab(this, 'tab-server')">🌐 服务器</a>
  </li>
  <li class="tab-item" id="tab-php">
    <a href="#"
       hx-get="/doc/htmx_tab?tab=php"
       hx-target="#tab-panel"
       hx-on:click="switchTab(this, 'tab-php')">🐘 PHP</a>
  </li>
  <li class="tab-item" id="tab-clue">
    <a href="#"
       hx-get="/doc/htmx_tab?tab=clue"
       hx-target="#tab-panel"
       hx-on:click="switchTab(this, 'tab-clue')">🔧 Clue</a>
  </li>
</ul>
<div id="tab-panel" class="p-2">选择一个标签页查看信息</div>

端点代码（`source/control/doc.php`）：

<div class="code-block" hx-get="/doc/htmx_source?type=controller&name=htmx_tab" hx-trigger="load">加载中...</div>

`switchTab()` 定义在 `asset/js/spectre.js` 中。
每个标签的 `hx-on:click` 同时触发 HTMX 请求和本地激活态切换，
面板内容由服务端返回 HTML 片段，激活态由前端管理，避免服务端状态同步。

## 视图模板文件一览

上述三个示例的 HTML 结构与 Controller 分离后，视图文件集中在 `source/view/clue/`：

| 文件 | 用途 | 可用变量 |
|------|------|---------|
| `source/view/clue/toast.php` | Spectre.css Toast 通知 | `$level`（success/error/primary）、`$message` |
| `source/view/clue/modal.php` | Spectre.css 模态弹窗 | `$id`、`$title`、`$body`、`$footer`（可选） |
| `source/view/clue/pagination.php` | 分页导航 | 由 `Pagination::render()` 传入 |
| `asset/js/spectre.js` | Spectre.css 交互逻辑（关闭 toast/modal、切换 tab） | `closeToast()`、`closeModal()`、`switchTab()` |

Controller 只需 `new \Clue\View('clue/xxx')` 并传入数据，由视图模板
处理 Spectre.css 的 HTML 结构，`asset/js/spectre.js` 处理交互逻辑。
