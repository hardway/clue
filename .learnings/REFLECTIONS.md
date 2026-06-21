# 2026-06-21: HTMX 交互组件进阶

## 会话概要
在 Clue skeleton 的 doc/ui 页面中，围绕 Spectre.css + HTMX 实现了完整的交互组件体系。

### 组件列表
1. **Toast** — 后端返回带关闭按钮的 toast，点击 × 关闭
2. **Modal** — HTMX 动态渲染模态弹窗，遮罩层/×/底部按钮三入口关闭
3. **Pagination** — `Clue\UI\Pagination` 后端分页 + `hx-boost` 无刷新翻页
4. **Tab** — 标签切换，内容通过 HTMX 请求，激活态前端管理

### 经验教训

**htmx.on() 不能用在后端渲染的元素上**
- `htmx.on("click", selector, fn)` 不是事件委托形式，第二个参数被当作 DOM 元素
- 动态渲染的元素用 `hx-on:click` 属性，逻辑收敛到 `spectre.js`

**HX-Push-Url 响应头 vs hx-push-url 属性**
- `hx-boost` 默认推送 URL 到 history，`hx-push-url="false"` 在 boost 场景不生效
- 服务端返回 `header('HX-Push-Url: false')` 可有效抑制

**视图模板分离**
- Controller 不再 echo HTML 字符串，改用 `new \Clue\View('clue/xxx')` 传数据
- HTML 结构 → `source/view/clue/*.php`，交互逻辑 → `spectre.js`

**Clue 参数映射**
- `$_GET` 参数自动映射到 controller action 的函数参数
- `function htmx_tab($tab = 'server')` 无需手动 `$_GET`

**动态源码展示**
- `htmx_source` 端点用 `ReflectionMethod` 提取 Controller 方法源码
- doc 页面通过 `hx-trigger="load"` 自动加载，代码永不过期

### Bug 修复
- `Pagination::render()` `$begin` 计算到 0 → 加 `max(1, ...)`
- `pagination.php` 视图残留的 `<i></i>` 标签
- 正则匹配 `href` 时单双引号问题
