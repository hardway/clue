var ContextMenu=new Class({
    Implements: [Options],
    options: {},
    initialize: function(el, options){
        this.el=$(el);
        this.setOptions(options);

        $$(this.options.triggers).addEvent('click', function(e){this.show(e.target); e.stop(); e.stopPropagation();}.bind(this));
        window.addEvent("click", this.hide.bind(this));
    },

    show: function(trigger){
        this.el.set('context', trigger.get('context'));

        var cord=trigger.getCoordinates();
        this.el.setStyles({top: cord.top, left: cord.left});
        this.el.show();
    },
    hide: function(){
        this.el.hide();
    }
});
