/**
 * clue.js — Clue framework frontend utilities
 */

document.addEventListener('DOMContentLoaded', function() {
    // debug() 树状折叠展开
    document.querySelector('.clue-debug')?.addEventListener('click', function(e) {
        var toggle = e.target.closest('.d-toggle');
        if (!toggle) return;
        toggle.classList.toggle('collapsed');
        // toggle 后面的第一个 .d-children 兄弟元素
        var node = toggle.nextElementSibling;
        while (node && !node.classList.contains('d-children')) {
            node = node.nextElementSibling;
        }
        if (node) {
            node.classList.toggle('collapsed');
        }
    });
});
