layui.define(['jquery','layer','global','laydate'], function(exports) {
    var $ = layui.jquery,layer = layui.layer,form = layui.form,globals=layui.global,laydate=layui.laydate;

    layui.use(['form','global','table'],function () {
        var table = layui.table;
        let page = 15;
        if (window.page_limit) {
            page = window.page_limit
        }
        table.init('table-list', {limit: page});
        table.on('tool(table-list)', function(obj){
            var data = obj.data;
            if(obj.event==='layui-open') {
                var url = $(this).attr('data-url');
                var title = $(this).attr('data-title');
                var width = $(this).attr('data-width');
                var height = $(this).attr('data-height');

                var layer_open = layer.open({
                    type: 2,
                    maxmin: true,
                    title: title ? title : '操作',
                    content: url ? url : 'URL错误',
                    area: [width ? width : '800px', height ? height : '600px'],
                    shade: 0, //不显示遮罩
                });
            }
        });
    });

    lay('.layui-search-time').each(function(){
        laydate.render({ elem: this});
    });

    form.on("switch(layui-switch-field)", data => {
        var that = $(data.elem);
        var url = that.data('url');
        var field = that.data('field');
        var checked =  data.elem.checked === true ? 1 : 2;
        if(url && field) {
            var index = globals.loading();
            globals.ajax(url,{'field':field,'status':checked},function(res){
                layer.close(index);
                globals.msg(res.message);
            });
        }
    });

    exports('index', {});
});