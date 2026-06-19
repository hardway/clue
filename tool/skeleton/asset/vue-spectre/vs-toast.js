var VueSpectre=VueSpectre || {};
VueSpectre.Toast={
    mounted:function(){
        console.log("VueSpectre.Toast");
    },
    methods:{
        close:function(){
            this.$el.parentNode.removeChild(this.$el);
        },
    },
    template:`<div class='toast'><button @click='close' class="btn btn-clear float-right"></button><slot></slot></div>`
};
