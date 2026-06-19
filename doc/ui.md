## 前端UI

浏览器前端UI主要组合了[Vue][vue]和[Spectre.css][spectre]两个框架

[vue]: https://vuejs.org/
[spectre]: https://picturepan2.github.io/spectre/index.html

根据项目需要，也可以完全使用另外的前端框架或方案代替

## 现有组件

#### AutoComplete

<script>
    var vsa={
        data:function(){
            console.log("data");
            return {}
        },
        mounted:function(){
            console.log("MOUNT");
        },
        template:
            "<div><h1>VSA</h1></div>"
    };
</script>

<div id='app'>
    <vs-toast>HI</vs-toast>
    <vs-autocomplete items="a, b, c">
        asdf
    </vsautocomplete>
</div>

<script type="text/javascript">
    Vue.createApp({
        data() {
          return {
            message: 'Hello Vue!'
          }
        },
        components:{
            'vs-autocomplete': VueSpectre.Autocomplete,
            'vs-toast': VueSpectre.Toast
        }
      }).mount('#app');
</script>
