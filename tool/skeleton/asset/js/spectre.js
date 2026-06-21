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
