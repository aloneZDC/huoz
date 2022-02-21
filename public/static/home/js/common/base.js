// JavaScript Document
//ajax提交表单并刷新本页面
function ajax_submit_form(form){
    var index='';
    $.ajax({
        type:$(form).attr("method"),
        url: $(form).attr("action"),
        async:false,
        data: $(form).serialize(),
        beforeSend: function(){
            //index=	layer.msg("{:L('lan_please_wait_moment')}");//正在提交请稍后
        },
        success: function(msg){
            //layer.msg(msg.info,{time:2000});
            if(msg.status==1){
                var url=$(form).attr("jump-url");
                if(url){
                    layer.alert(msg.info,function () {
                        window.location.href=url;
                    });
                }else{
                    window.location.reload();
                }
            }else{
                layer.alert(msg.info,function () {
                    window.location.reload();
                    layer.close(index);
                });

            }
        }
    });
    return false;
}
function submit_form(form){
    var index='';
    layer.msg(msg.info);
    if(msg.status==1){
        var url=$(form).attr("jump-url");
        if(url){
            window.location.href=url;
        }else{
            window.location.reload();
        }
    }else{
        layer.msg(msg.info);
        layer.close(index);
        //window.location.reload();
    }

    return true;
}

$(function(){
	$("header .menu").on("mouseenter",function(){
		$(this).find(".header_list").css("display","block");
	});
	$("header .menu").on("mouseleave",function(){
		$(this).find(".header_list").css("display","none");
	});
});