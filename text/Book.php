<?php
// TODO: deprecate default view?
namespace Clue\Text{
    class Book{
        public $folder;
        public $default_view;

        function __construct($folder, $default_view='/clue/book/page'){
            $this->folder=rtrim($folder, '/');
            $this->default_view=$default_view;
        }

        // 路径是否包含 .. 段或空字节（防止目录穿越；不解析符号链接，保持原有行为）
        static function is_unsafe_path($path){
            $path=(string)$path;
            if(strpos($path, "\0") !== false) return true;
            return in_array('..', explode('/', $path), true);
        }

        /**
         * 将相对路径映射到文档目录下的文件路径
         * 含 .. 或空字节的路径返回 null
         */
        function resolve($path, $require_file=false){
            if(self::is_unsafe_path($path)) return null;

            $target=$this->folder.'/'.ltrim($path, '/');
            if($require_file && !is_file($target)) return null;
            return $target;
        }

        // 查找原始档所在位置
        function lookup($path){
            if($path=='index') $path="";

            $page_path=null;
            $candidates=["%s", "%s.htm", "%s.md", "%s.txt", "%s/readme.md"];
            foreach($candidates as $c){
                $real=$this->resolve(sprintf($c, $path), true);
                if($real){
                    $page_path=$real;
                    break;
                }
            }

            if($page_path){
                return BookPage::load($page_path, $this);
            }

            // 如果是目录，则直接返回索引页面
            if($this->resolve($path) && is_dir($this->resolve($path))){
                $index=$this->index($path);
                return $index;
            }

            // 找不到页面
            $page404=new BookPage("#page not found", 'md', $this);
            $page404->path=$path;
            $page404->view="/clue/book/404";
            return $page404;
        }

        // 列出文件夹目录（或使用现有的index.md）
        function index($path=""){
            // 含 .. 的路径视为文档根目录，防止列出文档目录之外的内容
            if(self::is_unsafe_path($path)) $path="";

            $folder=$this->folder.'/'.$path;
            while(!is_dir($folder) && strpos($folder, $this->folder)===0){
                $folder=dirname($folder);
            }

            // 如果有自定义index.md，则优先使用
            $index_path="$folder/index.md";
            if(is_file($index_path)){
                return new BookPage(file_get_contents($index_path), 'md', $this);
            }

            // TODO 支持递归目录?（应该也会同时需要缓存）
            $entries=[];
            foreach (new \DirectoryIterator($folder) as $f) {
                if($f->isFile()){
                    $ext=$f->getExtension();
                    $filename=str_replace(".$ext", "", $f->getFilename());

                    $entryies[]=[
                        'path'=>$filename,
                        'type'=>$ext,
                        'name'=>$filename
                    ];
                }
            }

            // 排序
            uasort($entryies, function($a, $b){return strncasecmp($a['name'], $b['name'], 32);});

            // 生成markdown
            $markdown="";
            foreach($entryies as $e){
                $markdown.="- [{$e['name']}]({$e['path']})\n";
            }

            return new BookPage($markdown, 'md', $this);
        }

        // 按关键词搜索
        function search($keywords){
            $markdown="Searching `$keywords`\n------\n\n";

            // 简单搜索：关键词与目录都用 escapeshellarg 传参，防止命令注入；grep 语义保持不变
            $ok=exec("grep -ri -e ".escapeshellarg($keywords)." -- ".escapeshellarg($this->folder), $result);

            if($ok){
                $found=[];
                foreach($result as $match){
                    list($path, $text)=explode(':', $match, 2);
                    $page=trim(str_replace($this->folder, '', $path), "/");
                    $found[$page][]=$text;
                }

                foreach($found as $page=>$lines){
                    $ext=pathinfo($page, PATHINFO_EXTENSION);
                    $url=substr($page, 0, -1 - strlen($ext));   // 若有需要，在view中指定<base>标签

                    // TODO: 根据front matter显示正确的title和其他信息
                    $markdown.= "####[$page]($url)";
                    $markdown.= "\n> ".implode("\n> ", $lines);
                    $markdown.= "\n\n";
                }
            }
            else{
                $markdown.= "No results found.";
            }

            // 返回一个结果页
            return new BookPage($markdown, 'md', $this);
        }
    }

    class BookPage{
        // 根据静态文件加载
        // 所以会有$path属性
        static function load($path, $book){
            $page_content=@file_get_contents($path);
            $page_type=pathinfo($path, PATHINFO_EXTENSION);

            $p=new self($page_content, $page_type, $book);
            $p->path=$path;

            $base=trim(str_replace($book->folder, '', dirname($path)), '/');
            // $p->content="<h1>$p->path</h1><base href='$base'></base>".$p->content;

            return $p;
        }

        public $content;
        public $path;   // 动态生成的内容则path为空
        public $type;
        public $view;
        public $book;

        public function __construct($content, $type, $book){
            $this->book=$book;
            $this->view=$book->default_view;

            if($this->path) exit(var_dump($this->path));
            if($type=='md'){
                // TODO: 更多FrontMatter的参考
                // http://simpleprimate.com/blog/front-matter
                // http://jekyllcn.com/docs/frontmatter/

                if(preg_match('/^\-{3,}\n.+\n\-{3,}\n/ism', $content, $m)){
                    $content=str_replace($m[0], '', $content);
                    foreach(explode("\n", $m[0]) as $line){
                        list($n, $v)=explode(":", $line, 2);
                        $n=strtolower(trim($n));
                        $v=trim($v);
                        switch($n){
                            case 'title':
                                define("META_TITLE", $v);
                                break;

                            case 'layout':
                            case 'view':
                                $this->view=$v;
                                break;

                            // TODO: author, date, tags, categories, slug, meta, description
                            default:
                                error_log("Unknown meta block - $n: $v");
                        }
                    }
                }
            }

            $this->type=$type;
            $this->content=$content;
        }

        function render_content(){
            switch($this->type){
                case 'txt':
                    return "<div class='clue-book-text'>$this->content</div>";
                    break;
                case 'htm':
                case 'html':
                    return $this->content;
                    break;
                case 'md':
                default:
                    // 复用 ParseDown 实例（text() 每次都会重置内部状态）
                    return \Clue\Text\ParseDown::format($this->content);
                    break;
            }
        }
    }
}
