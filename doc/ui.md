## 前端UI

前端框架使用 [Spectre.css][spectre] 作为 CSS 基础，配合 [HTMX][htmx] 实现动态交互。

[spectre]: https://picturepan2.github.io/spectre/index.html
[htmx]: https://htmx.org/

根据项目需要，也可以完全使用另外的前端框架或方案代替。

## HTMX 示例

点击按钮加载服务器时间：

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
