文档管理
===============

类似Gitbook，可以通过controller或action指定显示某个文件夹的内容。具体可以参考模板(skeleton)项目中的`controller/doc.php`

目录内的txt, md, htm文件均被视作一个文章

`readme.md`作为目录的默认首页（缺省根据目录内容生成）

`index.md`作为目录的索引页，显示在侧边栏（如果没有指定index.md，系统也会自动生成）

#### 自定义

简单的外观自定义，可以用CSS重新定义来实现

也可以通过重载`source/view/clue/book/page`和`source/view/clue/book/404`来定义全新的页面格局
