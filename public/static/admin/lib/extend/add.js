layui.define(['jquery','layer','global'], function(exports) {
    var $ = layui.jquery,layer = layui.layer,form = layui.form,globals=layui.global;

    function submit() {
		if (window.editor1) {
            window.editor1.sync();
		}
		if (window.editor2) {
            window.editor2.sync();
		}
		if (window.editor3) {
            window.editor3.sync();
		}
        var url = $('.submit').data('url');
        var data = $('.form').serialize();
        if(url && data){
            var index = globals.loading();
            globals.ajax(url,data,function(res){
                layer.close(index);
                if(res.code==10000) {
                    layer.alert(res.message, {
                        icon: 6
                    }, function () {
                        // 获得frame索引
                        let index = parent.layer.getFrameIndex(window.name);
                        //关闭当前frame
                        parent.layer.close(index);
                        window.parent.location.reload();
                    });
                } else {
                    globals.msg(res.message);
                }
            });
        }
    }
    $('.submit').click(function(){
        submit();
    });

    exports('add', {});
});
