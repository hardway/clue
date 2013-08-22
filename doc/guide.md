## 快速了解

CLUE会被打包为clue.phar发布，可以直接在项目中引用(`include clue.phar`)，相关的类会自动加载<br/>
也可以作为命令行中使用`php clue.phar`，用于创建应用，controller, model等

### 创建应用

执行`php clue.phar init [directory]`后会在directory目录（默认为当前目录）创建如下格式的应用结构
<pre>
├── asset					# 所有的js,css,img建议放在这里
│   └── stylesheet.css
├── .htaccess				# mod_rewrite 配置文件
├── config.php				# 应用配置文件
├── index.php				# 程序入口（符合条件的URL访问通过.htaccess转移到这里）
└── source					# 源程序
    ├── class				# 类定义
    ├── control				# Controller定义
    │   └── index.php		# 默认的Index Controller
    ├── include				# 包含文件
    ├── model 				# Model定义
    └── view				# View定义
        ├── index 			# Index Controller的View
        │   └── index.htm   # @controller=index, @action=index
        └── layout			# 布局定义
            └── default.htm	# 默认布局
</pre>

### 基本MVC

类似与Ruby On Rails，Controller有多个Action，定义在Controller中，例如(index.php):
<pre>
class Controller extends Clue\Controller{   # 因为文件名为index，所以这是Index Controller
	function index(){}		# 默认action
	function hello(){}		# @action=hello
	function _post(){}		# “_”前缀表示该Action响应HTTP POST请求
}
</pre>

Controller定义在`source/control`目录中<br/>
与controller和action相对应的View存放在`source/view`目录中，例如:
<pre>
└── index                   # Index Controller
    └── index.htm           # .htm和.php后缀均可以
    └── hello.php           # 不过.php会优先于.htm执行，因此建议将一些逻辑处理代码放在.php
    └── hello.htm           # 而仅显示内容放在.htm
    └── post.php
</pre>

事实上Controller的Action可自由选择调用哪一个视图(View)，例如(control/index.php):
<pre>
class Controller extends Clue\Controller{
    function foo(){
        $data['msg']='Hello World';
        $this->render('hello', $data);
    }

    function _bar(){
        $this->render('hello');
    }
}
</pre>

向View传递参数通过Controller::render()的第二个参数，这样在View中可以直接使用该变量，例如(view/index/hello.htm)：

    Message: <?=$msg?>
    <?php $this->incl('form', array('message'=>$msg)); ?>


注意到View是可以相互嵌套的（通过View::incl()方法），其中若sub-view以'/'开头，则视作绝对路径（视图绝对路径的根是source/view/）<br/>
嵌套的视图也通过类似的hash table传递变量，例如(view/index/form.php)：

    This is message in form: <?=$message?>

默认的render()方法将使用default布局（即view/layout/default.htm，这个文件也是一个View），如果需要使用到其他布局（例如view/layout/popup.htm），<br/>
可以使用`$this->render_popup('hello', $data)`

### HMVC（层次MVC）

MVC不是简单的扁平结构，可以支持层级目录。

一般而言，/foo/bar 会被解析到`@controller=foo, @action=bar`进行处理，由于支持层次MVC，实际的解析过程是这样的（假设Control只定义了foo::bar()）:
<pre>
    1. 尝试 @controller=foo/bar, @action=index    # 因为没有foo/bar这个Controller而跳过
    2. 尝试 @controller=foo, @action=bar          # 在本例中匹配，若control/foo.php不存在，或者缺少bar()方法，将继续向下匹配
    3. 尝试 @controller=index, @action=foo, @param=[bar]
    4. 尝试 @controller=index, @action=index, @param=[foo, bar]
</pre>

### 路由

路由的作用就是将非标准的URL转换为符合HMVC的URL而已，例如：
<pre>
    $router->alias('/^.*-p-(\d+)$/i', '/product/view/$1');
    $router->alias('/^.*-c-(\d+)$/i', '/category/view/$1');
</pre>

### Database

支持多种数据库（Microsoft SQLServer, MySQL, Oracle, Sqlite）

创建实例：
<pre>
    $db=Clue\Database::create(array(
        'type'="mysql",
        'host'=>'127.0.0.1',
        'database'=>'test'
        'username'=>'root',
        'password'=>'',
        'encoding'=>'UTF8'
    ));
</pre>

常用的CRUD方法:
<pre>
    $db->insert("table", array(
        'name'=>'foo',
        'value'=>'bar'
    ));

    $db->update("table", array(
        'name'=>'foo',
        'value'=>'bar'
    ), "id=1");

    $db->delete("table", "id=2");

    $all_rows=$db->get_results("select * from table");
    $one_row=$db->get_row("select * from table");
    $one_col=$db->get_col("select name from table");
    $cell=$db->get_var("select name from table limik 1")

    # 分别获取三种类型的数据库结果

    $r=$db->get_row("select * from table limit 1", ARRAY_A);
    # $r=array('name'=>'foo', 'value=>'bar');

    $r=$db->get_row("select * from table limit 1", ARRAY_N);
    # $r=array('foo', 'bar');

    $r=$db->get_object("select * from table limit 1", 'Table');
    # typeof $r=="Table"
    # $r->name='foo';
    # $r->value='bar';
</pre>

### ActiveRecord

参考Martin Fowler的[Active Record Pattern](http://www.martinfowler.com/eaaCatalog/activeRecord.html)

MVC中的Model一般用ActiveRecord来实现，例如：
<pre>
class Article extends Clue\ActiveRecord{
    public $id;
    public $title, $content;
    public $author;
}
</pre>

对于Article的操作将自动关联到数据库，例如：
<pre>
    $a=new Article();
    $a->title="Subject Line";
    $a->content="Body Text";
    $a->save();

    $b=Article::find_one(array('id'=>$a->id));
    $b=Article::find_one_by_id($a->id);

    $articles=Article::find_all();

    $b->destroy();
</pre>

对于常用的FORM POST动作，一个方便的绑定方法是:
<pre>
    # 假设提交的form为title=a&content=b
    $a=new Article($_POST);
    # $a->title=='a';
    # $a->content=='b';
</pre>

### Asset

asset()全局函数可以直接返回/asset目录下的资源文件

Clue\Asset类用于将多个asset组合（未来将提供压缩选项）为单个文件输出

### CLI

提供ANSI控制，输入获取等功能，方便编写控制台脚本

### Web\Client

Clue\Web\Client用于抓取（下载）URL资源，支持CACHE，支持COOKIE和HTTP REFERER和HTTP POST
Clue\Web\Parser用于解析HTML文件，支持常用css selector

简单示例:
<pre>
    $client=new Clue\Web\Client();
    $dom=new Clue\Web\Parser($client->get("http://www.google.com/about/"));
    $mission=$dom->getElement("#about-mission blockquote");
    echo $mission->text;
</pre>
