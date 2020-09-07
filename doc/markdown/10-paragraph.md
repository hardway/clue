
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


## 分隔线

可以在一行中使用三个或更多的 *、- 或 _ 来添加分隔线（`<hr>`）
多个字符之间可以有空格（空白符），但不能有其他字符

```
***
------
* * *
- - -
```