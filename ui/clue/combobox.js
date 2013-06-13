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

ComboBox=new Class({
    Implements: [Options, Events],

    options:{
        onSelect: function(){},

        displayDropButton: false,
        ignoreDataOnFilter: false,
        output: null,       // Normally the "hidden" input controll to hold the data
        format: "simple",   // could also be 'full'
        source: "none",     // could also be 'local' or remote: 'ajax.php?type=instock&name=abc'
        delay: 500,
        data: []
                            // Simple format: [item1, item2, ...]
                            // Full format: [{data: xx, content: xx, present: xx, filter: xx}, ...]
                            // or a url for remote data loader
    },

    list:null,

    input: null,
    output: null,

    lastHighlightedItem: null,
    lastFilterValue: null,

    filterTimeout: null,

    listItem:[],
    listData:[],

    initialize: function(input, options){
        this.input=$(input);
        this.setOptions(options);

        if(this.options.output)
            this.output=$(this.options.output);
        else
            this.output=this.input;

        this.input.addEvent("keydown", this.cancelInput.bind(this));

        this.input.addEvent("keyup", function(e){
            var e=new Event(e);

            //console.log("KEYUP: "+e.key);
            if(e.key=="esc"){
                return this.cancelInput(e);
            }

            if(this.options.source!="none"){
                if(this.options.source=="local"){
                    this.filterList(this.input.value);
                }
                else{   // remote
                    if(!this.filterTimeout)
                        this.filterTimeout=this.filter.delay(this.options.delay, this);
                }
            }

            this.showList();

            return true;
        }.bind(this));

        this.loadListData(this.options.format, this.options.data);
        this.decorate();
    },

    cancelInput: function(e){
        if(e.key!="esc") return true;

        if(this.list.getStyle("display")=="none"){
            this.input.value="";
            this.output.value="";
        }
        else{
            this.hideList();
        }

        e.preventDefault();

        return false;
    },

    filter: function(){
        if(this.options.source.indexOf("(?)")>=0){
            if(this.lastFilterValue!=this.input.value){
                this.lastFilterValue=this.input.value;
                var url=this.options.source.replace("(?)", encodeURIComponent(this.input.value));
                this.loadListData(this.options.format, url);
            }
        }

        this.filterTimeout=null;    // Clear timer
    },

    loadListData: function(format, data){
        this.listData=[];

        if(typeOf(data)=="string"){  // treat as url
            var json=new Request.JSON({url: data, onComplete: function(data){
                this.loadListData(format, data);
            }.bind(this)}).get();

            return;
        }

        if(typeOf(data)=="array"){
            if(format=="simple"){
                data.each(function(d){
                    this.listData.push({data: d, present: d, content: d});
                }, this);
            }
            else if(format=="full"){
                this.listData=data;
            }
            this.buildList();
        }
    },

    decorate: function(){
        if(this.options.displayDropButton){
            this.button=new Element("span", {
                "class":"combobox_button",
                "html": Browser.Engine.trident ? "&nbsp;" : "",
                "styles":{
                    cursor: "pointer",
                    height: "16px",
                    width: "16px",
                    padding: Browser.Engine.trident4 ? "0 6px" : "0 8px"
                },
                "events":{
                    "click": function(event){event.stop(); event.stopPropagation(); this.toggleList(); }.bind(this)
                }
            }).inject(this.input, "after");
        }

        this.input.addClass("combobox");
    },

    filterList: function(filter){
        var items=this.list.getChildren();
        var i=0;

        if(filter){
            for(i=0; i<items.length; i++){
                var match=
                    (this.options.ignoreDataOnFilter?false:items[i].get("data").contains(filter)) ||
                    items[i].get("text").contains(filter) ||
                    items[i].get("present").contains(filter);

                items[i].setStyle("display", match ? null : "none");
            }
        }
        else{
            for(i=0; i<items.length; i++){
                items[i].setStyle("display", null);
            }
        }
    },

    buildList: function(){
        if(this.list==null){
            this.list=new Element("div", {
                "class":"combobox_list",
                "styles":{
                    display:"none",
                    "z-index":"999",
                    "overflow-y": "auto",
                    position: "absolute",
                    cursor: "default"
                }
            }).inject(this.input, "after");
        }
        this.list.empty();

        this.listData.each(function(d){
            var row=new Element("div",{
                "class":"combobox_item",
                "html":d.content,
                "present":d.present,
                "data":d.data
            }).inject(this.list, "bottom");
            row.store("combobox", this);
            row.addEvent("mouseenter", function(event){this.retrieve("combobox").highlightItem(this);}.bindWithEvent(row));
            row.addEvent("click", function(event){this.retrieve("combobox").selectItem(this);}.bindWithEvent(row));
            this.listItem.push(row);
        }, this);
    },

    isComboBoxItem:function(item){
        return (item && item.hasClass && item.hasClass("combobox_item"));
    },

    unHighlightItem: function(item){
        if(!this.isComboBoxItem(item)) return;

        if(typeOf(item)==false) item=this.lastHighlightedItem;
        if(typeOf(item)=="element") item.removeClass("combobox_item_highlighted");
    },

    highlightItem: function(item){
        if(!this.isComboBoxItem(item)) return;

        this.unHighlightItem(this.lastHighlightedItem);
        item.addClass("combobox_item_highlighted");
        this.lastHighlightedItem=item;
        item.focus();
    },

    selectItem: function(item){
        if(!this.isComboBoxItem(item)) return;

        var oldData=this.output.value;
        var oldPresent=this.input.value;
        this.output.value=item.get("data");
        this.input.value=item.get("present");
        this.hideList();

        this.fireEvent("onSelect", [item.get("data"), item.get("present"), oldData, oldPresent]);
    },

    toggleList: function(){
        if(this.list==null) return; // Not ready, TODO: build on demand

        if(this.list.getStyle("display")=="none")
            this.showList();
        else
            this.hideList();
    },

    showList: function(){
        var parent=this.input.getParent();
        parent=document.body;

        if(this.list==null) return; // Not ready, TODO: build on demand

        this.list.setStyle("display", null);

        var margin_left=parseInt(this.input.getStyle('margin-left'));
        var margin_top=parseInt(this.input.getStyle('margin-top'));
        this.list.setStyle("left", this.input.getCoordinates(parent).left - margin_left);
        this.list.setStyle("top", this.input.getCoordinates(parent).top + this.input.getCoordinates().height - margin_top);
        if(this.list.getSize().x<this.input.getSize().x){
            //console.log("Enlarge list");
            this.list.setStyle("width", this.input.getSize().x);
        }

        // find out the selected value
        var items=this.list.getChildren();
        var i=0;
        var check=this.output.value || this.input.value;

        if(!this.lastHighlightedItem) for(i=0; i<items.length; i++){
            if(items[i].get("data")==check){
                this.highlightItem(items[i]);
                break;
            }
        }

        // try to find out other combobox drop down list and hide it.
        $$(".combobox_list").each(function(b){if(this.list!=b) b.setStyle("display", "none");}, this);

        // register hide event
        $(document.body).addEvent("click", this.hideList.bind(this));
    },

    hideList: function(){
        if(this.list==null) return; // Not ready, TODO: build on demand

        this.list.setStyle("display", "none");
        this.unHighlightItem();

        // remove the hide event
        $(document.body).removeEvent("click", this.hideList.bind(this));
    },

    set: function(data, present){
        present=present || data;
        this.input.value=present;
        this.output.value=data;
    },

    getPresent: function(){ return this.input.value; },
    getData: function(){ return this.output.value; }
});


