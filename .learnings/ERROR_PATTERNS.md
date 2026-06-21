# Error Patterns

## HTMX: autocomplete suggestion 点击后 closest() 返回 null

**表现：** `el.closest('.menu')` 在 `hx-on:click` handler 内返回 null，但在浏览器 console 中手动执行正确。

**根因：** 多个 `keyup delay:300ms` 触发的并发 HTMX 请求导致时序竞争。先发出的请求响应后到，在 click handler 执行前 swap 了 `#auto-menu`，被点击的元素被 detached 后 `closest()` 失效。

**规避：** `selectSuggestion` 开头 abort 输入框上的所有 HTMX 请求：
```js
var input = autoBox.querySelector('.form-input');
if (input && typeof htmx !== 'undefined') htmx.trigger(input, 'htmx:abort');
```

**原理：** `htmx:abort` 事件让 HTMX 取消指定元素上所有 `XMLHttpRequest`，阻止后续响应触发 swap。
