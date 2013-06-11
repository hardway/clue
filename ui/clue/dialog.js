var Dialog=new Class({
    Implements: [Options, Events],
    options:{
        width: 480,
        height: 320,
        modal: false,
        onClose: function(){}
    },

    initialize: function(options){
        this.setOptions(options);

        this.dlg=new Element("div", {id:'clue-dialog', 'class': 'hide'});

        var dlg_title=new Element("div", {id: 'clue-dialog-title', styles:{cursor: 'move'}});
        dlg_title.adopt(new Element("div", {id: 'clue-dialog-title-text'}));

        this.dlg.adopt(dlg_title);
        this.dlg.adopt(new Element("div", {id: 'clue-dialog-text'}));

        var dlg_close=new Element("div", {id: 'clue-dialog-close', title: "Close"});
        dlg_close.onclick=this.hide;
        dlg_close.inject(dlg_title);

        this.dlg.inject(document.body);

        new Drag.Move(this.dlg, {handle: dlg_title});

        var width=this.options.width;
        var height=this.options.height;
        this.dlg.setStyles({top: '50%', left: '50%', 'margin-top': "-"+(width/2)+"px", 'margin-left': "-"+(height/2)+"px",  'width': width+'px', 'min-height': height+'px'});

        $("clue-dialog-title-text").set('html', this.options.title);
        $("clue-dialog-text").adopt(new Element("div", {html: this.options.text, id: 'clue-dialog-message-text'}));

        this.buttons=new Element("div", {'class': 'buttons'});
        $("clue-dialog-text").adopt(this.buttons);

        if(this.options.modal){
            this.dlg.addClass("modal");
        }
    },

    resize: function(){
        this.dlg.setStyles({'width': 'auto', 'height': 'auto'});

        width=Math.max(this.width||0, this.dlg.getScrollSize().x);
        height=Math.max(this.height||0, this.dlg.getScrollSize().y);
        var left=Math.floor((window.getCoordinates().width - width)/2);
        var top=Math.floor((window.getCoordinates().height - height)/2);

        this.dlg.setStyles({'margin-left': '-'+(width/2)+'px', 'margin-top': '-'+(height/2)+'px', 'width': width+'px', 'height': height+'px'});
    },

    addButton: function(text, action){
        this.buttons.adopt(new Element('input', {
            type: 'button',
            value: text,
            events: {click: function(){action.attempt();this.hide();}.bind(this)}
        }));
    },

    show: function(){
        cover=new Element("div", {
            id: 'clue-dialog-cover'
        });
        cover.inject(document.body);

        if(!this.options.modal){
            cover.onclick=function(){this.hide();}.bind(this);
        }

        this.dlg.removeClass("hide");
    },

    hide: function(){
        if($("clue-dialog-cover")){
            $("clue-dialog-cover").destroy();
        }
        if($('clue-dialog')) $('clue-dialog').destroy();
    }
});

Dialog.hide=function(){
    if($("clue-dialog-cover")){
        $("clue-dialog-cover").destroy();
    }
    if($('clue-dialog')) $('clue-dialog').destroy();
};

Dialog.message=function(title, text, width, height){
    var d=new Dialog({title: title, text: text, width: width||320, height: height||240});
    d.show();

    return d;
};

Dialog.modal=function(title, text, options){
    onOK=options.onOK || function(){}
    width=options.width || 320;
    height=options.height || 240;

    var d=new Dialog({title: title, text: text, width: width, height: height, modal: true});
    d.addButton("OK", onOK);
    d.show();

    return d;
};

Dialog.open=function(title, url, width, height){
    return Dialog.open_iframe(title, url, width, height);
};

Dialog.open_iframe=function(title, url, width, height){
    height=height||480;
    var d=new Dialog({
        title: title,
        text: "<iframe frameborder='0' style='width: 100%; height: "+(height-20)+"px; border: 0;' src=\""+url+"\"></iframe>",
        width: width||640,
        height: height
    });

    d.show();

    return d;
};

Dialog.open_get=function(title, url, width, height){
    var d=new Dialog({title: title, text: "Loading ... ", width: width||640, height: height});
    new Request.HTML({url: url, method: 'get', onSuccess:function(_,_,body){
        console.log(body);
        $("clue-dialog-message-text").innerHTML=body;
        d.resize();
    }}).send();

    d.show();
    return d;
};

Dialog.open_post=function(title, url, width, height, data){
    var d=new Dialog({title: title, text: "Loading ... ", width: width||640, height: height});
    new Request.HTML({
        url: url, method: 'post', data: data,
        onSuccess: function(_, _, body){
            $("clue-dialog-message-text").innerHTML=body;
            d.resize();
        }
    }).send();

    d.show();
    return d;
};

Dialog.yesno=function(title, text, options){
    options.title=title;
    options.text=text;

    var d=new Dialog(options);

    onYes=options.onYes || function(){};
    onNo=options.onNo || function(){};

    d.addButton("Yes", onYes);
    d.addButton("No", onNo);
    d.show();

    var btn=d.buttons.getElement("input");
    btn.focus();
    btn.addEvent("keydown", function(e){
        if(e.key=='esc'){
            d.hide();
            onNo.attempt();
        }
        else if(e.key=='enter'){
            d.hide();
            onYes.attempt();
        }

        e.stopPropagation();
        return false;
    });

    return d;
};

Dialog.enable=function(cls){
    $$(cls).each(function(a){
        a.onclick=function(){
            var size=[640, 480];
            var modal=false;
            var title="";

            if(a.get("title")){
                title=a.get("title");
            }
            if(a.get("size")){
                size=a.get("size").split("x");
            }
            if(a.get("modal")){
                modal=true;
            }

            Dialog.open(title, a.href, size[0], size[1], modal);
            return false;
        };
    });
};
