var EditNow={
    Begin: function(){
        type=this.get('mode');
        backend=this.get('backend');

        if(!backend || backend.length==0){
            return alert("Backend Property Missing.");
        }

        this.removeClass('editnow').removeEvents('click');

        if(type=='text'){
            EditNow.EditText(this);
        }
        else if(type=='select'){
            EditNow.EditSelect(this);
        }
        else if(type=='textarea'){
            EditNow.EditTextArea(this);
        }
    },

    notify: function(msg){
        if(!$("editnow-notification")){
            document.body.adopt(new Element("div", {
                id: 'editnow-notification'
            }));
        }

        $("editnow-notification").set('html', msg);
        (function(){
            if($("editnow-notification")) $("editnow-notification").destroy();
        }).delay(2000);
    },

    emptyClickHandler: function(e){
        var e=new Event(e); e.stopPropagation();
    },
    saveData: function(el, editor){
        var data={};
        data['action']='editnow';
        data[editor.name]=editor.value;

        EditNow.notify("Saving ...");
        var b=new Request({
            url: el.get('backend'), method: "post",
            data: data,
            evalScripts: true,
            onSuccess: function(text){
                if(text.trim()=="OK"){
                    EditNow.notify("saved");
                    EditNow.finishEdit(el, editor);
                }
                else
                    alert("ERROR: "+text);
            }
        }).send();
    },

    cancelEdit: function(el, text, editor){
        var type=el.get('mode');

        if(type=="text" || type=='textarea'){
            text=text.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/ /g, '&nbsp;');
            text=text==''?'-':text;
            el.set('html', text);
        }
        else if(type=='select'){
            el.set('html', text);
        }

        editor.destroy();

        el.addClass('editnow');
        el.addEvent('click', EditNow.Begin.bind(el));
    },
    finishEdit: function(el, editor){
        var type=el.get('mode');
        var text="", value="";

        if(type=='select'){
            var opts=editor.getElements('option');
            for(var i=0; i<opts.length; i++){
                if(opts[i].selected){
                    text=opts[i].get('text');
                    value=opts[i].value;
                    break;
                }
            }
        }
        else if(type=='text' || type=='textarea'){
            text=editor.value;
        }

        this.cancelEdit(el, text, editor);
    },

    EditTextArea: function(el){
        var coor=el.getCoordinates();

        var originText=el.get('text').trim();
        if(originText=='-') originText="";

        el.set('text','');

        var editor=new Element("textarea", {
            'name': el.get('name'),
            'text': originText,
            'rows': el.get('rows'),
            'styles': {
                "font-family": el.getStyle('font-family'),
                padding: '3px',
                width: coor.width - 16
            },
            'events':{
                'click': EditNow.emptyClickHandler,
                'keydown': function(e){
                    if(e.key=='esc')
                        EditNow.cancelEdit(el, originText, this);
                },
                'blur': function(e){
                    new Event(e).stop().preventDefault().stopPropagation();

                    if(this.value==originText){
                        return EditNow.cancelEdit(el, originText, this);
                    }

                    EditNow.saveData(el, this);
                }
            }
        }).inject(el).focus();
    },

    EditSelect: function(el){
        var coor=el.getCoordinates();
        var originText=el.get('text').trim();
        var originValue="";

        el.set('text', '');

        var editor=new Element("select", {
            'name': el.get('name'),
            'styles': {
                "font-family": el.getStyle('font-family'),
                width: coor.width,
            },
            'events':{
                'click': EditNow.emptyClickHandler,
                'keydown': function(e){
                    if(e.key=='esc')
                        EditNow.cancelEdit(el, originText, this);
                },
                'blur': function(){
                    if(this.value==originValue){
                        return EditNow.cancelEdit(el, originText, this);
                    }

                    EditNow.saveData(el, this);
                }
            }
        });

        el.get('options').trim().split(';').each(function(pair){
            var name="", value="";
            var match=/([^=]*)=(.*)/.exec(pair);
            if(match){
                value=match[1];
                name=match[2];
            }
            else{
                name=value=pair;
            }

            var option=new Element("option", {
                'value': value,
                'html': name
            }).inject(editor);

            if(name.trim()==originText.trim()){
                originValue=value;
                option.set('selected', 1);
            }
        });

        editor.inject(el).focus();
    },

    EditText: function(el){
        var coor=el.getCoordinates();

        var originText=el.get('text').trim();
        el.set('text', '');

        var editor=new Element("input", {
            'type': 'text',
            'name': el.get('name'),
            'value': originText,
            'styles': {
                "font": el.getStyle('font'),
                'text-align': el.getStyle('text-align'),
                width: coor.width-4
            },
            'events':{
                'click': EditNow.emptyClickHandler,
                'keydown': function(e){
                    if(e.key=='esc')
                        EditNow.cancelEdit(el, originText, this);
                    else if(e.key=='enter'){
                        EditNow.saveData(el, this);
                    }
                },
                'blur': function(){
                    if(this.value==originText){
                        return EditNow.cancelEdit(el, originText, this);
                    }

                    EditNow.saveData(el, this);
                }
            }
        });

        editor.inject(el).focus();
        editor.select();
    }
}

DeleteNow=function(){
    var form=this.get('form');
    var id=this.get('delid');
    var name=this.get('delname') || "this";

    if(!form || !$(form) || !$(form).getElement(".delid")){
        return alert("Form missing or incorrect.");
    }

    Dialog.yesno("Confirm", "Delete "+name+" ?", {
        onYes: function(){
            $(form).getElement(".delid").value=id;
            $(form).submit();
        },
        width: 320, height: 140
    });
}

window.addEvent("domready", function(){
    $$(".editnow").each(function(el){
        el.addEvent('click', EditNow.Begin.bind(el));
    });

    $$(".delnow").each(function(el){
        el.addEvent('click', DeleteNow.bind(el));
    });
});
