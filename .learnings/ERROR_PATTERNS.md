# 错误模式

## REGEX-PHP-QUOTES: 正则匹配 HTML 属性时忽略单引号
- **场景**: 用 `/<a href="([^"]+)"/` 匹配 `<a href='...'>`（单引号）
- **后果**: 替换完全没命中，链接走原生跳转
- **解法**: 同时匹配单双引号：`/<a href=(["'\``])([^"'\``]+)\1/`，或输出缓冲前先统一 HTML 格式

## HTMX-BOOST-PUSHURL: hx-boost 无视 hx-push-url 属性
- **场景**: 在 hx-boost 父容器上设 `hx-push-url="false"`，子链接上设同样属性
- **后果**: URL 仍然被推送到浏览器历史
- **解法**: 服务端返回 `header('HX-Push-Url: false')` 响应头

## HTMX-ON-DELEGATION: htmx.on() 不是事件委托
- **场景**: `htmx.on("click", ".selector", fn)` — 误以为第二个参数是 CSS 选择器
- **后果**: 只对已存在的元素生效，HTMX 动态渲染的元素不触发
- **解法**: 后端返回 HTML 时直接在元素上加 `hx-on:click`
