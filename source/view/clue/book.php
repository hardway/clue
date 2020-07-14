<?php
    // 类似Gitbook的文档组织方案和说明
    // TODO: 支持完整的Jekyll front matter
    // http://simpleprimate.com/blog/front-matter
    // https://stackoverflow.com/questions/27838730/is-there-a-yaml-front-matter-standard-validator
    // http://jekyllcn.com/docs/frontmatter/
    // https://lexcao.github.io/zh/posts/jekyll-hugo-hexo#
    $clue_root=dirname(dirname(dirname(__DIR__)));

    // 使用ParseDown转换 https://github.com/erusev/parsedown
    include_once $clue_root.'/vendor/parsedown.php';

    function render_content($content, $type){
        switch($type){
            case 'txt':
                return nl2br($content);
                break;
            case 'htm':
            case 'html':
                return $content;
                break;
            case 'md':
            default:
                $pd=new \Clue\Text\ParseDown();
                return $pd->text($content);
                break;
        }
    }

    // TODO: 单独的ClueBook类
    // TODO: 支持提前向controller汇报meta信息
    if(isset($app->params['q'])){
        $q=$app->params['q'];
        $docroot=Clue\site_file("/doc");
        // DocBase 拼接URL时候的doc路径，允许通过参数指定
        $docbase=@$docbase ?: url_for($app->controller, $app->action);

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
                $url=url_normalize($docbase."/$page");
                echo "<li><a href='$url'><b>$page</b></a><ul>";
                echo implode("<br/>", $lines);
                echo "</ul></li>";
            }
        }
        else{
            echo "<h2>Not found: $q</h2>";
        }
        return;
    }

    if(!@$path){
        // 自动根据路径拼接
        $path=implode("/", array_filter($app->params, function($k){return is_numeric($k);}, ARRAY_FILTER_USE_KEY));
    }

    if(preg_match('/^clue\/?(.*)/', $path, $m)){
        // 这是保留关键词 :) 直接显示clue的文档
        $page=$m[1] ?: "guide";
        $page_type="md";
        $page_content=file_get_contents("$clue_root/doc/$page.md");

        $index=false;
    }
    else{
        // TODO: doc目录可以设置
        // $path=preg_replace('/\.md$/', '', $path);   // 关闭.md后缀
        $page_path = Clue\site_file($path)
                    ?: Clue\site_file("/doc/$path.htm")
                    ?: Clue\site_file("/doc/$path.md")
                    ?: Clue\site_file("/doc/$path.txt")
                    ?: Clue\site_file("/doc/$path/readme.md");

        $page_content=@file_get_contents($page_path);
        $page_type=pathinfo($page_path, PATHINFO_EXTENSION);

        $index=@file_get_contents($page ? dirname($page).'/index.md' : Clue\site_file("/doc/$path/index.md"));

        if(!$page && $index){
            // index作为页面内容时关闭index显示
            $page_content=$index;
            $page_type='md';
            $index=false;
        }
        else{
            if($index) $index.="\n[ ^ ](../)\n";
        }
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
    <div id='clue-book-index'><?=render_content($index, 'md')?></div>
<?php endif; ?>
<div id='clue-book-html'><?=render_content($page_content, $page_type);?></div>

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
