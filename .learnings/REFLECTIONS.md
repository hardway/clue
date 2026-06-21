# Technical Reflections

## 2026-06-21: HTMX Autocomplete Race Condition

**最小成功路径：**

1. 视图模板 `source/view/clue/autocomplete.php` — Spectre `.form-autocomplete` 结构 + `hx-trigger="keyup changed delay:300ms"`
2. Controller 端点 `htmx_autocomplete_suggest($q)` — 过滤数据返回 `<li class="menu-item">`
3. JS `selectSuggestion()` — 添加 chip、清空输入、关闭菜单
4. JS `removeChip()` — 删除 chip

**关键发现 — 并发请求时序竞争：**

`hx-trigger="keyup changed delay:300ms"` 会产生多个并发 GET 请求。由于响应到达顺序不定（晚发的可能先到），JavaScript 事件循环中可能发生：

1. 用户点击 suggestion → click event 排队
2. 另一个 AJAX 响应回调排在 click 之前 → swap `#auto-menu` → 被点击的 `<a>` 被 detached
3. click handler 执行在 detached 元素上 → `closest('.menu')` 返回 null

**修复：** 在 `selectSuggestion()` 开头调用 `htmx.trigger(input, 'htmx:abort')` 取消所有 inflight 请求，阻止后续响应干扰 selection 处理。

**纯 `closest()` 方案可行** — 无需 `getElementById` fallback，前提是消除并发竞争。

## 2026-06-21 同步: HTMX Autocomplete 竞态分析修正

**初期诊断错误：** 认为 `closest('.menu')` 返回 null 是 HTMX 并发请求的时序竞争导致的。
错误推断路径：多个 `keyup delay:300ms` 请求 → 响应乱序 → swap 在 click 前 detached 元素。

**实际根因回顾：**
1. 初始代码 `el.closest('.form-autocomplete-input')` 天然失败（`.form-autocomplete-input` 是 `.menu` 的兄弟，不是 `<a>` 的祖先）
2. debug log 中 `el.closest('menu')` 漏了 `.`，实际匹配 `<menu>` 标签而非 `.menu` class
3. HTMX 默认 `queue:last` + `delay:300ms` 已保证同时最多 1 个 inflight 请求，不存在并发竞态
4. `htmx:abort` 是多余的，最终移除

**经验：** 调试时 selector 准确性先行验证，不要急着往并发方向猜。
