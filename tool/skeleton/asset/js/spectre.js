/**
 * spectre.js — Spectre.css component interactions via HTMX
 *
 * Included automatically because app.js globs asset/js/*.js
 * in the skeleton config.
 */

/**
 * 关闭父级 .toast 元素
 *
 * 各端点返回 toast 时在关闭按钮上加：
 *   hx-on:click="closeToast(this)"
 *
 * 而不是用绑定委托或 htmx.on()，因为 toast 由 HTMX 动态渲染，
 * 从后端产生的 HTML 最可靠的方式就是显式 hx-on 属性。
 */
window.closeToast = function(btn){
    var toast = btn.closest('.toast');
    if (toast) toast.remove();
};

/**
 * 关闭父级 .modal 元素
 *
 * overlay / 关闭按钮 / 底部按钮统一用：
 *   hx-on:click="closeModal(this)"
 */
window.closeModal = function(el){
    var modal = el.closest('.modal');
    if (modal) modal.remove();
};

/**
 * 切换 Tab 标签页激活状态
 *
 * 在 tab 链接上加：
 *   hx-on:click="switchTab(this, 'tab-id')"
 *
 * 移除同一 .tab 内所有 .active，为当前 tab-item 加上 .active
 */
window.switchTab = function(link, tabId){
    var tabBlock = link.closest('.tab');
    if (tabBlock) {
        tabBlock.querySelectorAll('.tab-item').forEach(function(item){
            item.classList.remove('active');
        });
    }
    var tabItem = document.getElementById(tabId);
    if (tabItem) tabItem.classList.add('active');
};

/**
 * 从 autocomplete 建议列表选择一项，添加为 chip
 *
 * HTML 结构假设：
 *   .form-autocomplete
 *     .form-autocomplete-input.form-input  → chip 插入区
 *       .has-icon → input + .form-icon
 *     .menu → 建议列表
 *
 * 点击建议 → 在 .has-icon 前插入 chip → 清空输入 → 关闭菜单
 *
 * 在建议链接上使用：
 *   hx-on:click="selectSuggestion(this, event)"
 */
window.selectSuggestion = function(el, e){
    if (e) e.preventDefault();
    var value = el.getAttribute('data-value');
    if (!value) return;

    // abort 输入框上的 inflight 请求，防止并发响应 swap 时元素被 detached
    var autoBox = el.closest('.form-autocomplete');
    if (!autoBox) return;
    var input = autoBox.querySelector('.form-input');
    if (input && typeof htmx !== 'undefined') htmx.trigger(input, 'htmx:abort');

    var menu = autoBox.querySelector('.menu');
    if (menu) menu.innerHTML = '';

    var chipsArea = autoBox.querySelector('.form-autocomplete-input');
    if (!chipsArea) return;
    var hasIcon = chipsArea.querySelector('.has-icon');
    if (!hasIcon) return;

    var chip = document.createElement('div');
    chip.className = 'chip';
    chip.textContent = value;
    var closeBtn = document.createElement('button');
    closeBtn.className = 'btn btn-clear';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.onclick = function(){ removeChip(this); };
    chip.appendChild(closeBtn);
    chipsArea.insertBefore(chip, hasIcon);

    input = hasIcon.querySelector('.form-input');
    if (input) {
        input.value = '';
        input.focus();
    }
};

/**
 * 移除 autocomplete 的 chip
 *
 * Chip 关闭按钮使用：
 *   onclick="removeChip(this)"
 * （由 selectSuggestion JS 动态创建，无需 hx-on 处理）
 */
window.removeChip = function(btn){
    var chip = btn.closest('.chip');
    if (chip) chip.remove();
};
