var RPC={
    get: function(url, update){
        if(typeOf(update)=='element')
            new Request.HTML({url: url, method: 'get', update: update, evalScripts: true}).send();
        else
            new Request({url: url, method: 'get', evalScripts: true}).send();
    },

    post: function(url, data, update){
        if(typeOf(update)=='element')
            new Request.HTML({url: url, update: update, data: data, method: 'post', evalScripts: true}).send();
        else
            new Request({url: url, data: data, method: 'post', evalScripts: true}).send();
    }
};
