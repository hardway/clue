<?php
/**
 * Spectre.css Autocomplete 组件视图模板
 *
 * 配合 HTMX 实现按键搜索建议：
 *   input → hx-get="/doc/htmx_autocomplete_suggest" → 返回 <li class="menu-item">...
 *   点击建议 → selectSuggestion() → 添加 chip
 *   chip × → removeChip() → 删除 chip
 *
 * 变量：
 * @var string $placeholder  输入框占位文字
 */
$placeholder ??= '输入搜索内容...';
?>
<style>
  .form-autocomplete .has-icon .form-icon.loading { display: none; }
  .form-autocomplete .has-icon.htmx-request .form-icon.loading { display: block; }
  .form-autocomplete .has-icon.htmx-request .form-input { padding-right: 1.6rem; }
</style>
<div class="form-autocomplete" id="auto-demo">
  <div class="form-autocomplete-input form-input">
    <!-- chip 由 selectSuggestion() JS 动态插入到这里 -->
    <div class="has-icon">
      <input class="form-input" type="text"
             name="q" autocomplete="off"
             placeholder="<?= htmlspecialchars($placeholder) ?>"
             hx-get="/doc/htmx_autocomplete_suggest"
             hx-trigger="keyup changed delay:300ms, search"
             hx-target="#auto-menu"
             hx-indicator="closest .has-icon">
      <i class="form-icon loading"></i>
    </div>
  </div>
  <ul class="menu" id="auto-menu"></ul>
</div>
