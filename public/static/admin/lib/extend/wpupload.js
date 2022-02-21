layui.define(['jquery','global','upload','layer'], function(exports){
    var $ = layui.jquery
    ,layer = layui.layer
    ,globals = layui.global
    ,upload = layui.upload;

    var uploadTarget = null;
    if($('.image_list_upload').length>0 && uploadUrl) {
        upload.render({
            elem: '.image_list_upload'
            ,url: uploadUrl
            ,method: 'post'
            ,multiple: true
            ,done: function(res, index, upload){
                uploadTarget = this.item.parents('.image_list').find('.img_list');
                if(uploadTarget.find('.img_box').length>10) {
                    globals.msg('最多允许上传10张图片');
                } else if(res.code==10000) {
                    var _html = '<div class="img_box"><input type="hidden" name="'+ this.item.data('field') +'" value="'+ res.data.path +'"><span class="close">×</span><img src="'+ res.data.src +'"></div>';
                    uploadTarget.append(_html);
                    $(".img_box .close").click(function(){close($(this));});
                } else {
                    globals.msg(res.message);
                }
            }
        });
    }

    if($('.image_file').length>0 && uploadUrl) {
        upload.render({
            elem: '.image_file'
            ,url: uploadUrl
            ,method: 'post'
            ,multiple: true
            ,done: function(res, index, upload){
                if(res.code==10000) {
                    uploadTarget = this.item.parents('.img_box');
                    uploadField = this.item.data('field');

                    if(uploadTarget) {
                        var input = uploadTarget.find("input[name='" + uploadField + "']");
                        if(input) {
                            input.val(res.data.path);
                        }
                        uploadTarget.find('img').remove();
                        uploadTarget.find('.img_data').html('<span class="close_one">×</span><img src="'+res.data.src+'" />');
                        $(".img_box .close_one").click(function(){close_one($(this));});
                    }
                } else {
                    globals.msg(res.message);
                }
            }
        });
    }

    $(".img_box .close_one").click(function(){
        close_one($(this));
    });

    function close_one(that){
    	var target = that.parent().parent();
    	target.find('.img_data').html('');
        target.find('input[type="hidden"]').val('');
    }

    function close(that) {
    	that.parents('.img_box').remove();
    }

    $(".img_box .close").click(function(){
        close($(this));
    });

    exports('wpupload', {});
});
