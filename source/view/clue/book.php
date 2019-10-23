<?php
    // 类似Gitbook的文档组织方案和说明
    $clue_root=dirname(dirname(dirname(__DIR__)));

    // 使用ParseDown转换 https://github.com/erusev/parsedown
    include_once $clue_root.'/vendor/parsedown.php';
    function md2htm($text){
        $pd=new Parsedown();
        return $pd->text($text);
    }

    // TODO: 单独的ClueBook类
    if(isset($app->params['q'])){
        $q=$app->params['q'];
        $docroot=Clue\site_file("/doc");

        // 简单搜索
        $ok=exec("grep -r \"$q\" \"$docroot\"", $result);
        if($ok){
            $found=[];
            foreach($result as $match){
                list($path, $text)=explode(':', $match, 2);
                $page=str_replace($docroot, '', $path);
                $found[$page][]=$text;
            }

            foreach($found as $page=>$lines){
                echo "<li><a href='".url_for($app->controller, $app->action)."/$page'><b>$page</b></a><ul>";
                echo implode("<br/>", $lines);
                echo "</ul></li>";
            }
        }
        else{
            echo "<h2>Not found: $q</h2>";
        }
        return;
    }

    if(!$path){
        // 自动根据路径拼接
        $path=implode("/", array_filter($app->params, function($k){return is_numeric($k);}, ARRAY_FILTER_USE_KEY));
    }

    if($path=='clue'){
        // 保留关键词 :)
        $page=$clue_root.'/doc/guide.md';
        $index=false;
    }
    else{
        $path=preg_replace('/\.md$/', '', $path);   // 关闭.md后缀
        $page=Clue\site_file("/doc/$path.md") ?: Clue\site_file("/doc/$path/readme.md") ?: Clue\site_file($path);
        $index=@file_get_contents($page ? dirname($page).'/index.md' : Clue\site_file("/doc/$path/index.md"));
    }

    if(!$page && $index){
        // index作为页面内容时关闭index显示
        $markdown=$index;
        $index=false;
    }
    else{
        if($index) $index.="\n[ ^ ](../)\n";
        $markdown=file_get_contents($page);
    }
?>
<?php
    // TODO: comment支持js
    // TODO: 图片点击js
    // TODO: index中当前页面高亮
    // TODO: breadcrum支持
    // TODO: 简单搜索功能
?>

<style type="text/css">
    #clue-book-search {float:right;}
    #clue-book-index {float:left; width:20%; display:;}
    #clue-book-index + #clue-book-html {margin-left:25%;}
</style>
<form id='clue-book-search'>
    <input type='text' name='q' placeholder="Search" value="<?=htmlspecialchars(GET("q"))?>" />
</form>
<?php if($index): ?>
    <div id='clue-book-index'><?=md2htm($index)?></div>
<?php endif; ?>
<div id='clue-book-html'><?=md2htm($markdown);?></div>

<?php if(false): // TODO: 使用StackEdit.js markdown解析器 ?>
    <textarea id='clue-book-markdown' style='display:none;'><?=$buffer?></textarea>
    <script src="https://unpkg.com/stackedit-js@1.0.7/docs/lib/stackedit.min.js"></script>
    <script type="text/javascript">
        const stackedit = new Stackedit();
        stackedit.openFile({
            name: 'Filename',
            content: { text: document.getElementById('clue-book-markdown').value }
        }, true /* silent mode */);

        stackedit.on('fileChange', (file) => {
            document.getElementById('clue-book-html').innerHTML = file.content.html;
        });
    </script>
<?php endif; ?>
