var Dropdown=new Class({
    Implements: [Options, Events],
    options:{

    },
    initialize: function(el, options){
        this.el=el;
        this.setOptions(options);

        this.selected=el.getElement(".selected");

        el.getElement(".selected").addEvent('click', function(){
            el.getElement(".options").toggle();
        });

        this.addEvent("changed", this.set_value);

        var dd=this;
        el.getElements(".option").addEvent("click", function(){
            dd.fireEvent("changed", this.get('val'));
            el.getElement(".options").hide();
        });
    },
    set_value: function(val){
        var options=this.el.getElements(".option");
        for(i=0; i<options.length; i++){
            if(options[i].get('val')==val){
                this.selected.set('html', options[i].get('name'));
                break;
            }
        }
    }
});
