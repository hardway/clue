## 关于 Markdown

本文来源: [Markdown入门参考][2]

> [Markdown][1] 是一种轻量级标记语言，创始人为约翰·格鲁伯（John Gruber）。它允许人们“使用易读易写的纯文本格式编写文档，然后转换成有效的 XHTML（或者 HTML）文档”。这种语言吸收了很多在电子邮件中已有的纯文本标记的特性。

### 为什么选择 Markdown

* 它基于纯文本，方便修改和共享；
* 几乎可以在所有的文本编辑器中编写；
* 有众多编程语言的实现，以及应用的相关扩展；
* 在 GitHub 等网站中有很好的应用；
* 很容易转换为 HTML 文档或其他格式；
* 适合用来编写文档、记录笔记、撰写文章。

#### 兼容 HTML

Markdown 完全兼容 HTML 语法，可以直接在 Markdown 文档中插入 HTML 内容：

## 段落与换行

1. 段落的前后必须是空行：
   空行指的是行内什么都没有，或者只有空白符（空格或制表符）
   相邻两行文本，如果中间没有空行 会显示在一行中（换行符被转换为空格）
2. 如果需要在段落内加入换行（`<br>`）：
   可以在前一行的末尾加入至少两个空格
   然后换行写其它的文字
3. Markdown 中的多数区块都需要在两个空行之间。

## 标题

#### Setext形式

    H1
    =====
    H2
    ----

> = 和 - 的数量是没有限制的。通常的做法是使其和标题文本的长度相同，这样看起来比较舒服。或者可以像我一样，用四个 - 或 =。
> Setext 形式只支持 h1 和 h2 两种标题。

#### atx形式

① 可以用对称的 # 包括文本：

    ####H4####
    #####H5#####

② 也可以只在左边使用 #：

    ####H4
    #####H5

③ 成对的 # 左侧和只在左边使用的 # 左侧都不可以有任何空白，但其内侧可以使用空白。

> 在这一点上，可能各种 Markdown 的实现会有不同的结果，不过仍然需要我们遵守语法规则。

## 引用

**引用内容**

在段落或其他内容前使用 > 符号，就可以将这段内容标记为 '引用' 的内容（`<blockquote>`）：

    > 引用内容

**多行引用**
```
> 多行引用
> 可以在每行前加 `>`
```

```
>如果仅在第一行使用 `>`，
后面相邻的行即使省略 `>`，也会变成引用内容
```

**嵌套引用**

```
>也可以在引用中
>>使用嵌套的引用
```

**其他 Markdown**

```
>在引用中可以使用使用其他任何 *Markdown* 语法
```

## 列表

**无序列表**

* 可以使用 `*` 作为标记
+ 也可以使用 `+`
- 或者 `-`

**有序列表**

1. 有序列表以数字和 `.` 开始；
3. 数字的序列并不会影响生成的列表序列；
4. 但仍然推荐按照自然顺序（1.2.3...）编写。

**嵌套的列表**

1. 第一层
  + 1-1
  + 1-2
2. 无序列表和有序列表可以随意相互嵌套
  1. 2-1
  2. 2-2

**语法和用法**

1. 无序列表项的开始是：符号 空格；
2. 有序列表项的开始是：数字 . 空格；
3. 空格至少为一个，多个空格将被解析为一个；
4. 如果仅需要在行前显示数字和 .：
05. 可以使用：数字\. 来取消显示为列表

`\*` 的语法专门用来显示 Markdown 语法中使用的特殊字符

## 代码

可以使用缩进来插入代码块：

    <html> // Tab开头
        <title>Markdown</title>
    </html> // 四个空格开头

代码块前后需要有至少一个空行，且每行代码前需要有至少一个 Tab 或四个空格；

也可以通过 \`，插入行内代码（\` 是 Tab 键上边、数字 1 键左侧的那个按键）

例如 `<title>Markdown</title>`

**转换规则**

代码块中的文本（包括 Markdown 语法）都会显示为原始内容，而特殊字符会被转换为 HTML 字符实体。

## 分隔线

可以在一行中使用三个或更多的 *、- 或 _ 来添加分隔线（`<hr>`）
多个字符之间可以有空格（空白符），但不能有其他字符

```
***
------
* * *
- - -
```

## 超链接

**行内式**

格式为 `[link text](URL 'title text')`。

① 普通链接：`[Google](http://www.google.com/)`
② 指向本地文件的链接：`[icon.png](./images/icon.png)`
③ 包含 'title' 的链接: `[Google](http://www.google.com/ "Google")`

> title 使用 ' 或 " 都是可以的。

**参考式**

参考式链接的写法相当于行内式拆分成两部分，并通过一个 识别符 来连接两部分。参考式能尽量保持文章结构的简单，也方便统一管理 URL。

① 首先，定义链接：`[Google][link]`

第二个方括号内为链接独有的 识别符，可以是字母、数字、空白或标点符号。识别符是 不区分大小写 的；

② 然后定义链接内容：`[link]: http://www.google.com/ "Google"`
其格式为：[识别符]: URL 'title'。

> 其中，URL可以使用 <> 包括起来，title 可以使用 ""、''、() 包括（考虑到兼容性，建议使用引号），title 部分也可以换行来写；
> 链接内容的定义可以放在同一个文件的 任意位置；

③ 也可以省略 识别符，使用链接文本作为 识别符：

```
[Google][]
[Google]: http://www.google.com/ "Google"
```

> 参考式相对于行内式有一个明显的优点，就是可以在多个不同的位置引用同一个 URL。

**自动链接**

使用 <> 包括的 URL 或邮箱地址会被自动转换为超链接：

    <http://www.google.com/>
    <123@email.com>

该方式适合行内较短的链接，会使用 URL 作为链接文字。邮箱地址会自动编码，以逃避抓取机器人。

## 图像

插入图片的语法和插入超链接的语法基本一致，只是在最前面多一个 !。也分为行内式和参考式两种。

**行内式** `![GitHub](https://avatars2.githubusercontent.com/u/3265208?v=3&s=100 "GitHub,Social Coding")`

方括号中的部分是图片的替代文本，括号中的 'title' 部分和链接一样，是可选的。

**参考式**

```
![GitHub][github]
[github]: https://avatars2.githubusercontent.com/u/3265208?v=3&s=100 "GitHub,Social Coding"
```

指定图片的显示大小

Markdown 不支持指定图片的显示大小，不过可以通过直接插入<img />标签来指定相关属性：

`<img src="https://avatars2.githubusercontent.com/u/3265208?v=3&s=100" alt="GitHub" title="GitHub,Social Coding" width="50" height="50" />`

## 强调

1. 使用 `* *` 或 `_ _` 包括的文本会被转换为 `<em></em>` ，通常表现为斜体：
2. 使用 `** **` 或 `__ __` 包括的文本会被转换为 `<strong></strong>`，通常表现为加粗：
3. 用来包括文本的 * 或 _ 内侧不能有空白，否则 * 和 _ 将不会被转换（不同的实现会有不同的表现）：
4. 如果需要在文本中显示成对的 * 或 _，可以在符号前加入 \ 即可：
5. *、**、_ 和 __ 都必须 成对使用 。

## 字符转义

反斜线 \ 用于插入在 Markdown 语法中有特殊作用的字符。

# 扩展语法

Markdown 标准 本身所包含的功能有限，所以产生了许多第三方的扩展语法，如 GitHub Flavored Markdown。

这里只介绍众多扩展语法中的一部分内容，它们在不同平台或工具的支持程度不同，请参考具体平台或工具的文档和说明来使用。

## 删除线

这就是 `~~删除线~~`

## 代码块和语法高亮

与原来使用缩进来添加代码块的语法不同，这里使用 \`\`\` \`\`\` 来包含多行代码：

三个 \`\`\` 要独占一行。

在上面的代码块语法基础上，在第一组 ``` 之后添加代码的语言，如 'javascript' 或 'js'，即可将代码标记为 JavaScript。

## 表格

**单元格和表头**

使用 | 来分隔不同的单元格，使用 - 来分隔表头和其他行：

    name | age
    ---- | ---
    LearnShare | 12
    Mike |  32

为了美观，可以使用空格对齐不同行的单元格，并在左右两侧都使用 | 来标记单元格边界：

    |    name    | age |
    | ---------- | --- |
    | LearnShare |  12 |
    | Mike       |  32 |

为了使 Markdown 更清晰，| 和 - 两侧需要至少有一个空格（最左侧和最右侧的 | 外就不需要了）。

**对齐**

在表头下方的分隔线标记中加入 :，即可标记下方单元格内容的对齐方式：

 * :--- 代表左对齐
 * :--: 代表居中对齐
 * ---: 代表右对齐

```
| left | center | right |
| :--- | :----: | ----: |
| aaaa | bbbbbb | ccccc |
| a    | b      | c     |
```

如果不使用对齐标记，单元格中的内容默认左对齐；表头单元格中的内容会一直居中对齐（不同的实现可能会有不同表现）。



[1]: https://zh.wikipedia.org/wiki/Markdown
[2]: http://xianbai.me/learn-md/