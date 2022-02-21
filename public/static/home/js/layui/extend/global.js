layui.define(['jquery','layer','util','element'], function(exports) {
    var $ = layui.jquery,
        util = layui.util,
        layer = layui.layer,
        element = layui.element;
		
    var ajax = function(url,data,fb) {
		var index = loading();
        $.ajax({url: url,type: 'POST',dataType : "json",data: data,
            success: function(res){
				layer.close(index);
                fb(res);
            },
            error:function(XMLHttpRequest, textStatus, errorThrown){
				layer.closeAll();
                msg('请求失败,请重试!');
				layer.close(index);
            },
        });
    }

    var msg = function(msg) {
        layer.msg(msg, {offset: '100px',fixed:true,shade: 0.3,shadeClose:true});
    }

    var loading = function(){
        return layer.load(1, {shade: 0.3});
    }

    $('.layui-open').click(function () {
        var url = $(this).attr('data-url');
		var title = $(this).attr('data-title');
		var width = $(this).attr('data-width');
		var height = $(this).attr('data-height');
		if(url.indexOf('?')) {
			   url += '&layui=1'
		} else {
			   url += '?layui=1';
		}
		var layer_open = layer.open({
			   type: 2,
			   maxmin: true,
			   title: title ? title : '操作',
			   content: url ? url : 'URL错误',
			   area: [width ? width : '90%', height ? height : '80%'],
			   shade: 0, //不显示遮罩
		});
    });

    exports('global', {ajax:ajax,msg:msg,loading:loading});
});