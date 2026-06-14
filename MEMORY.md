# clue 项目知识

## 测试框架

- **测试运行器**: `tests/run.php`
  - `php tests/run.php` — 跑全部
  - `php tests/run.php session` — 跑指定模块
- **TestCase 基类**: `Clue\Test\TestCase`（`Test/TestCase.php`）
  - 自动 alias 为 `PHPUnit_Framework_TestCase`（向后兼容）
- **可用断言方法**: `assertEquals`（`==`）、`assertTrue`、`assertFalse`、`assertNull`、`assertNotNull`、`assertInstanceOf`、`assertEmpty`、`assertNotEmpty`、`assertMatchesRegularExpression`、`assertContains`
  - ❌ 没有 `assertSame`、`assertNotEquals`
- **异常测试**: `expectException()` / `expectExceptionMessageMatches()` + `@expectedException` 注解

## Session 组件

- `Session.php` — 包含 `DBSession`、`FileSession`、`Session` 三个类
- `ttl` 配置用 `isset()` 判断，支持 `ttl=0`
- `FileSession::read()` 在测试中需配合 `clearstatcache()` 确保 mtime 刷新
- `$_SERVER['REMOTE_ADDR']` / `HTTP_USER_AGENT` 需在测试 setUp 中模拟

## 架构要点

- 无 composer 依赖的测试框架，纯自研
- 测试文件 `.test.php` 放在 `tests/` 目录
- `stub.php` 是入口引导文件
- 自动加载: `autoload.php` — `spl_autoload_register("Clue\autoload_load")`
