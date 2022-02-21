layui.define(['jquery', 'layer', 'util', 'element'], function (exports) {
    let $ = layui.jquery,
        layer = layui.layer;

    let ajax = function (url, data, fb) {
        $.ajax({
            url: url, type: 'POST', dataType: "json", data: data,
            success: function (res) {
                fb(res);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                layer.closeAll();
                msg('没有权限!');
            },
        });
    }

    let msg = function (msg) {
        layer.msg(msg, {offset: '100px', fixed: true, shade: 0.3, shadeClose: true});
    }

    let loading = function () {
        return layer.load(1, {shade: 0.3});
    }

    let open = function (title, url, width, height) {
        layer.open({
            type: 2,
            title: title ? title : '操作',
            content: url ? url : 'URL错误',
            area: [width ? width : '1000px', height ? height : '600px'],
            shade: 0, //不显示遮罩
            maxmin: true,
        });
    }

    $('.layui-open').click(function () {
        let url = $(this).attr('data-url');
        let title = $(this).attr('data-title');
        let width = $(this).attr('data-width');
        let height = $(this).attr('data-height');

        open(title, url, width, height);
    });

    $('.layui-ajax').click(function () {
        let url = $(this).attr('data-url');

        let load = loading()
        ajax(url, {}, function (res) {
            layer.close(load);
            layer.msg(res.message, {time: 1000}, function () {
                location.reload();
            });
        });
    });

    exports('global', {ajax: ajax, open: open, msg: msg, loading: loading});
});
