var VueSpectre=VueSpectre || {};

VueSpectre.Autocomplete={
    props:['items', 'selected'],
    data:function(){
        return {json:{}}
    },
    mounted:function(){
        console.log("MOUNT");
        console.log(this.items);
        // TODO: 检测content是否有效?
    },
    computed:{
        src:function(){
            return json.src || ""
            console.log(this.content);
        }
    },
    methods:{
        dialog:function(){
            // this.isEditing=!this.isEditing;
        },
        onFileChange:function(name, files){
            var file=files[0];

            // 更新浏览器
            this.json.src = URL.createObjectURL(file)

            // 上传
            var fd = new FormData()
            fd.append(name, file)

            axios.post('/block/upload', fd).then(function (response) {
                this.json.src="/block/image/"+response.data.id;
                this.json.name=response.data.name;
                this.save(JSON.stringify(this.json));
            }.bind(this))
        },
    },
    template:`
<div class="form-autocomplete">
      <!-- autocomplete input container -->
      <div class="form-autocomplete-input form-input">

        <!-- autocomplete chips -->
        <div class="chip">
          <figure class="avatar avatar-sm" data-initial="TO"></figure>
          Thor Odinson
          <a href="#" class="btn btn-clear" aria-label="Close" role="button"></a>
        </div>

        <!-- autocomplete real input box -->
        <input class="form-input" type="text" placeholder="typing here">
      </div>

      <!-- autocomplete suggestion list -->
      <ul class="menu">
        <!-- menu list items -->
        <li class="menu-item">
          <a href="#">
            <div class="tile tile-centered">
              <div class="tile-icon">
                <figure class="avatar avatar-sm" data-initial="TS"></figure>
              </div>
              <div class="tile-content">
                Tony Stark
              </div>
            </div>
          </a>
        </li>
        <li class="menu-item">
          <a href="#">
            <div class="tile tile-centered">
              <div class="tile-icon">
                <figure class="avatar avatar-sm" data-initial="SR"></figure>
              </div>
              <div class="tile-content">
                Steve Rogers
              </div>
            </div>
          </a>
        </li>
      </ul>
    </div>
`
};
