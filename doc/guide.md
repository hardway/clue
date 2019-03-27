## 快速了解

CLUE会被打包为clue.phar发布，可以直接在项目中引用(`include clue.phar`)，相关的类会自动加载（代码命名空间为Clue）。
也可以作为可执行PHP脚本使用`php clue.phar`，用于创建应用，controller, model等

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

### 文件路径和加载过程

一般来说，PHP类文件是遵循PSR-0规范来保存的，优先顺序为clue.phar > /source/class > /source/model

/source/include目录已经放在PHP的include_path中，可以直接用include或者require加载（用于存放一些函数代码）

应用执行流程
<pre>
    .htaccess
        |
    index.php               # 可以通过修改.htaccess，指向其他文件，比如admin.php
        |                   # 在这里可以做一些特定的设置，如Session Name之类
    stub.php, config.php    # 公用的配置文件（CLI模式也可以用）
        |
    /control/foo.php        # 根据Router规则定位到Controller
        |
    /view/foo/bar.php       # 根据Controller中Action所调用的视图
    /view/foo/bar.htm       # .php起到code behind的作用
        |                   # .htm原则上只输出变量
    /view/foo/subview.htm   # 视图可以包含其他视图（此时code behind的作用可以发挥出来）
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
    └── post.php 			# View的后缀建议用PHP，方便IDE识别，以及避免代码泄漏
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

### 路由别名（rewrite/alias）

别名的作用就是将非标准的URL转换为符合HMVC的URL而已，类似apache的mod_rewrite例如：
<pre>
    $router->alias('/^.*-p-(\d+)$/i', '/product/view/$1');
    $router->alias('/^.*-c-(\d+)$/i', '/category/view/$1');
</pre>

### Database 数据库连接

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

    # 返回结果集
    $all_rows=$db->get_results("select * from table");
    
    # 仅返回第一行
    $one_row=$db->get_row("select * from table");

    # 仅返回第一列，形式为数组
    $one_col=$db->get_col("select name from table");

    # 仅返回第一个值（第一行，第一列）
    $cell=$db->get_var("select name from table");

    # 以hash形式返回，例如array('a'=>'1', 'b'=>'2')
    $hash=$db->get_hash("select name, value from table");

    # 如果有多个值，返回形式为array(
    #    'a'=>array('id'=>46, 'value'=>'1'),
    #    'b'=>array('id'=>89, 'value'=>'2')
    # )
    $hash=$db->get_hash("select name, id, value from table"); 

    # 分别获取三种类型的数据库结果
    # ARRAY_A 哈希数组，列名作为键，数据作为值
    # ARRAY_N 基本数组，键从0开始
    # OBJECT 对象，最后一个参数是类名，每一行数据会自动构造为该类的实例

    $r=$db->get_row("select * from table limit 1", ARRAY_A);
    # $r=array('name'=>'foo', 'value=>'bar');

    $r=$db->get_row("select * from table limit 1", ARRAY_N);
    # $r=array('foo', 'bar');

    $r=$db->get_object("select * from table limit 1", OBJECT, 'Table');
    # $r instanceof Table
    # $r->name='foo';
    # $r->value='bar';
</pre>

### ActiveRecord 模式

参考Martin Fowler的[Active Record Pattern](http://www.martinfowler.com/eaaCatalog/activeRecord.html)

MVC中的Model一般用ActiveRecord来实现，例如：
<pre>
class Article extends Clue\ActiveRecord{
    public $id;
    public $title, $content;
    public $author;
}
</pre>

对于Article的操作将自动关联到数据库，CRUD操作如下：
<pre>
    $a=new Article();   # 默认表名为article，主键为id，不过可以在static $model中自定义
    $a->title="Subject Line";
    $a->content="Body Text";
    $a->save();     # 根据是否新记录，调用insert和update语句
    # 获取单个记录
    $b=Article::get($a->id);    #直接通过主键
    $b=Article::find_one(array('id'=>$a->id));  #通过一组合条件
    $b=Article::find_one_by_id($a->id);     # 通过特定单个条件
    # 获取多个记录
    $articles=Article::find_all();          # 取得全部记录
    $articles=Article::find(array("title like 'xxx'"));   # 组合条件
    # 查询后Upadate
    $b=Article::get($a->id);
    $b->title="Changed";
    $b->save(); #运行SQL UPDATE操作
    # 删除记录
    $b->destroy();  # 删除该记录，运行SQL DELETE操作
</pre>

对于常用的FORM POST动作，一个方便的绑定方法是:
<pre>
    # 假设提交的form为title=a&content=b
    $a=new Article($_POST);  # 等效于
    # $a->title='a';
    # $a->content='b';
</pre>

### Asset 资源文件管理

asset()全局函数可以直接返回/asset目录下的资源文件

Clue\Asset类用于将多个asset组合（未来将提供压缩选项）为单个文件输出

### CLI 命令行模式

提供ANSI控制，输入获取等功能，方便编写控制台脚本

### Web\Client HTTP客户端

Clue\Web\Client用于抓取（下载）URL资源，支持CACHE，支持COOKIE和HTTP REFERER和HTTP POST
Clue\Web\Parser用于解析HTML文件，支持常用css selector

简单示例:
<pre>
    $client=new Clue\Web\Client();
    $dom=new Clue\Web\Parser($client->get("http://www.google.com/about/"));
    $mission=$dom->getElement("#about-mission blockquote");
    echo $mission->text;
</pre>
