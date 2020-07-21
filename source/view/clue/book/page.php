<?php
    /**
     * @param $search   搜索网址（缺省则不显示搜索框）
     * @param $content  正文(HTML格式)
     * @param $sidebar  像GitBook那样的索引侧边栏（例如可以指定sidebar=index）
     */

    // TODO: 一些可能的外挂js功能可以通过自定义view来实现，例如：
    // TODO: 支持js的外挂comment系统
    // TODO: 图片点击js
    // TODO: index中当前页面高亮
    // TODO: breadcrum支持（或nav）
?>

<div class='clue-book'>
    <?php if(!$sidebar): ?>
        <div class='clue-book-search'>
            <a class='btn btn-sm btn-link' style='font-size:1.5em' href='./'>^</a>
        </div>
    <?php endif; ?>

    <?php if($search): ?>
    <form class='clue-book-search' action='<?=$search?>'>
        <input type='text' name='q' placeholder="Search" value="<?=htmlspecialchars(GET("q"))?>" />
    </form>
    <?php endif; ?>

    <?php if($sidebar): ?>
        <div class='clue-book-sidebar'><?=$sidebar?></div>
    <?php endif; ?>

    <div class='clue-book-html'><?=$page->render_content()?></div>
</div>
