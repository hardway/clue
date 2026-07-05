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

## nginx empty PATH_INFO + isset() trap

**表现：** nginx + PHP-FPM 下 `isset($_SERVER['PATH_INFO'])` 返回 true，但值实际是空字符串 `''`，导致 Router 拿到空路径 `$path`，`REQUEST_URI` 分支被跳过。

**根因：** `isset()` 检查键存在且非 null，空字符串 `''` 符合条件。PHP 8.2 无全局常量回落时暴露了此逻辑缺陷（PHP 7.x 下看似正常是因为常量回落掩盖了空路径的后续表现）。

**修复：** `if(isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] !== '')`

## MySQL 8: rank 是保留关键字

**表现：** `You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near '' at line 1`，但纯看 SQL 语法没发现明显错误。

**根因：** MySQL 8 新增窗口函数 `RANK()`，将 `rank` 列为保留字。`ORDER BY rank`、`min(rank) rank` 等裸列名写法在 MySQL 8 中报语法错误。

**修复：** `ORDER BY \`rank\``、`min(\`rank\`) \`rank\``

**排查：** 将 `ERROR_DISPLAY_LEVEL` 设为 `'error'`（或移除 `0` 覆盖）让 Guard 展示完整异常消息，SQL 在 `[last_query]` 字段中可见。

## Guard try/catch 拦截导致异常详情丢失

**表现：** 页面显示错误消息但无 backtrace、无文件位置、无 SQL。

**根因：** Guard 通过 `set_exception_handler` 监控异常，但 `index.php` 的 try/catch 在 Guard 之前拦截了异常，Guard 从未收到通知。页面只看到 `echo $e->getMessage()` 的裸消息。

**修复：** Guard 存为变量，catch 块手动转发：
```php
$guard = new Clue\Guard([...]);
try{ $app->run(); }
catch(Exception $e){
    $guard->handle_exception($e);
    $guard->summarize();
    $app->http_error($e->getCode() ?: 500, $e->getMessage());
}
```
