var HTML={
    fix_table_header: function(table){
        var container=new Element("table", {styles: {position: 'fixed'}});
        table.getElement("thead").clone().inject(container);

        borderCollapse=table.getStyle("border-collapse")=="collapse";

        container.addClass(table.get('class'));

        var on_resize=function(){
            container.setStyles(table.getStyles('width'));

            var trs=container.getElements("thead tr");
            var trs2=table.getElements("thead tr");

            for(i=0; i<trs.length; i++){
                var ths=trs[i].getElements("th");
                var ths2=trs2[i].getElements("th");

                for(j=0; j<ths.length; j++){
                    var ts=ths2[j].getSize();
                    ths[j].styles=ths2[j].styles;
                    var paddingH=parseInt(ths2[j].getStyle('padding-left')) + parseInt(ths2[j].getStyle('padding-right'));
                    var borderH=parseInt(ths2[j].getStyle('border-left')) + parseInt(ths2[j].getStyle('border-right'));

                    var paddingV=parseInt(ths2[j].getStyle('padding-top')) + parseInt(ths2[j].getStyle('padding-bottom'));
                    var borderV=parseInt(ths2[j].getStyle('border-top')) + parseInt(ths2[j].getStyle('border-bottom'));

                    if(borderCollapse){
                        borderH=Math.ceil(borderH/2);
                        borderV=Math.ceil(borderV/2);
                    }

                    ths[j].setStyles({width: (ts.x - paddingH - borderH)+"px", height: (ts.y - paddingV - borderV)+"px"});
                }
            }
        }

        var on_scroll=function(){
            var cord=table.getCoordinates();
            var scroll=document.body.getScroll();

            var top=cord.top - scroll.y;
            var left=cord.left - scroll.x;

            var hs=table.getElement("thead").getSize();

            // 如果超出Table的底部，隐藏标题
            if(cord.bottom -hs.y < scroll.y || cord.right -hs.x < scroll.x){
                container.hide(); return;
            }
            else{
                container.show();
            }

            // 标题被隐藏后，置顶并显示阴影
            if(top<0){
                container.setStyles({'left': left+"px", 'top': "0", 'box-shadow': "0 2px 10px 1px gray"});
            }
            else
                container.setStyles({'left': left+"px", 'top': top+"px", 'box-shadow': "none"});
        }

        container.inject(table, 'before');
        on_resize();
        window.addEvent('resize', on_resize);
        window.addEvent('scroll', on_scroll);
    }
};
