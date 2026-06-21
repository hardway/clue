# 用户偏好模型

- 偏好将 HTML 结构与 Controller 逻辑分离（view 模板模式）
- 交互逻辑收敛到单个 JS 文件（spectre.js）
- 后端返回 HTML 片段时显式使用 `hx-on:click` 而非 JS 全局委托
- 文档示例代码优先从源头动态加载而非手写死代码
- 不修改框架核心文件（Pagination 等），在 controller 层用 ob/包装解决
- 提交前先确认，不自行 commit
