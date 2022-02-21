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

//加载图库
function ajax_load_tu(_this){
		//记录标识符号识别是哪个图被点击
		$(".pic-select").removeAttr("id")
		$(".pic-select-input").removeAttr("id")
		$(_this).find('img').attr("id","pic-active");
		$(_this).next(".pic-select-input").attr("id","pic-select-input-active");
		$.ajax({
		  url: "/Pic/count",
		  success: function(data){
				if(data>0){
						var initPagination = function() {
							var num_entries =data;
							// 创建分页
							$("#Pagination").pagination(num_entries, {
								num_edge_entries: 2, //边缘页数
								num_display_entries: 40, //主体页数
								callback: pageselectCallback,
								items_per_page:4 //每页显示1项
							});
						 }();
					}
		  }
		});
	}
//图库分页回调函数
function pageselectCallback(page_index, jq){
			$.ajax({
			  url: "/Pic/getPic",
			  type: "POST",
			  data:{"p":page_index},
			  success: function(data){
				$(".modal-body").empty().append(data);
			  }
		});
		return false;
	}
//图库图像选择
 function pic_select(_this){
	var src=$(_this).attr("src");
		if($("#pic-button").length>0){
		//图添加
		//增加的图片数量,如果数量不是一个，则input的name是数组
		var num=parseInt($("#pic-button").attr("data-num"));
		//隐藏表单的name
		var input_name='';
		if(num!=1){
			//多图上传name转数组
			 input_name=$("#pic-button").attr("data-input")+"[]";
			 //如果是多图上传，则提供删除功能
			 $("#pic-button").before("<div class='pic-wrp' onmouseover='show_tishi(event)'onmouseout='hide_tishi(event)' onclick='delete_pic(this)' style='display:inline-block'><img class='pic-select' width=150 height=150 src='"+src+"'/><p class='tishi'  style='display: none;'>单击删除</p><div class='wrap-zhe' style='display: none;'></div></div>");
		}else{
			input_name=$("#pic-button").attr("data-input");
			$("#pic-button").before("<div class='pic-wrp' onmouseover='show_tishi(event)'onmouseout='hide_tishi(event)' onclick='ajax_load_tu(this)' data-toggle='modal'  data-target='#pic' ><img class='pic-select' src='"+src+"'/><p class='tishi'>单击修改<p><div class='wrap-zhe'></div></div>");
		}
		//添加隐藏表单
		$("#pic-button").before("<input type='hidden' value='"+src+"' name='"+input_name+"'  class='pic-select-input'/>");
		//统计已经添加的图片数量
		var pic_count=$(".pic-select").length
		if(num>0&&pic_count>=num){
			$("#pic-button").hide();
		}
	}else{
		//图修改
		$("#pic-active").attr("src",src);
		$("#pic-select-input-active").val(src);
		}
}

//删除程序
function delet(id,_this){
	var url=$(_this).attr("data-url");
	layer.confirm('确定要删除该条记录吗？', {
		btn: ['确定','取消'] //按钮
	}, function(){
		$.ajax({
				  url: url,
				  type: "POST",
				  data:{"id":id},
				  success: function(data){
					if(data.status==1){
						layer.msg(data.info);
						window.location.reload();	
					}else{
						layer.msg(data.info);
						}
				  }
				});
	});
	return false;
}
 //重载验证码
function fleshVerify(){ 
    var time = new Date().getTime();
    $("#verify").attr('src', '/Login/verify'+'?'+time);
}
//后台左侧导航程序
function showNav(c){
	$(".nav li").removeClass("active");
	$(c).addClass("active")
}
//箭头,全选
$(document).ready(function(e) {
    /*$(".nav-second-level>li").children("a").on("click",function(){
		$(this).children("span").addClass("arrow");	
	});*/
	$("#allchk").on("click",function(){
		var isChecked = $(this).prop("checked");
    	$("input[name='id']").prop("checked", isChecked);
		/*if($("#allchk").prop("checked")){
			$("[name = id]:checkbox").attr("checked","checked");
		}else{
			$("[name = id]:checkbox").removeAttr("checked");
		}*/
	});
});


// 首页
$(document).ready(function(){
	// 首页积分类型隔行变色 移入变色
	$('.data_table_box_box:nth-of-type(odd)').addClass('data_table_bg_odd');
	$('.data_table_box_box:nth-of-type(even)').addClass('data_table_bg_even');
	$('.data_table_box_box').mousemove(function(){
    $(this).addClass('data_table_bg_active')
	})
	$('.data_table_box_box').mouseout(function(){
    $(this).removeClass('data_table_bg_active');
	})
	// 首页躺着挣钱点击隐藏
    $("#bq").click(function(){
        $("#xjdf_left").hide();
    })
    // 首页系统公告 新闻中 心媒体报道 选项切换
    $('.press_release').mouseover(function(){
        $(this).addClass('pressActive').stop().animate({width:'500px'},300).siblings().removeClass('pressActive').stop().animate({width:'349px'},300);
    })

// 弹出层 定位  以及 右边客服电话块显示  返回顶部等
        $(".pcicon1").on("mouseover", function () {
            $(this).addClass("lbnora").next(".pcicon1box").css({"width": "148px"});
        }).on("mouseout", function () {
            $(this).removeClass("lbnora").next(".pcicon1box").css("width", "0px");
        });
        $(".iscion4").on("click", function () {
            $("html, body").animate({
                scrollTop: 0
            })
        });
        $(".pcicon4").on("mouseover", function () {
            $(this).addClass("lbnora").next(".pcicon1box").css({"width": "148px"});
        }).on("mouseout", function () {
            $(this).removeClass("lbnora").next(".pcicon1box").css("width", "0px");
        });
        $(".iscion9").on("click", function () {
            $("html, body").animate({
                scrollTop: 0
            })
        });
        var objWin;
        $("#opensq").on("click", function () {
            var top = window.screen.height / 2 - 250;
            var left = window.screen.width / 2 - 390;
            var target = "http://p.qiao.baidu.com//im/index?siteid=8050707&ucid=18622305";
            var cans = 'width=780,height=550,left=' + left + ',top=' + top + ',toolbar=no, status=no, menubar=no, resizable=yes, scrollbars=yes';

            if ((navigator.userAgent.indexOf('MSIE') >= 0) && (navigator.userAgent.indexOf('Opera') < 0)) {
                //objWin = window.open ('','baidubridge',cans) ;
                if (objWin === undefined || objWin === null || objWin.closed) {
                    objWin = window.open(target, 'baidubridge', cans);
                } else {
                    objWin.focus();
                }
            } else {
                var win = window.open('', 'baidubridge', cans);
                if (win.location.href == "about:blank") {
                    //窗口不存在
                    win = window.open(target, 'baidubridge', cans);
                } else {
                    win.focus();
                }
            }
            return false;
        })
 // 登陆后鼠标放入我的账户发生的 菜单显示何下拉  
 $(".myhome").hover(function () {
        $(".mywallet_list").show();
    }, function () {
        $(".mywallet_list").hover(function () {
            $(".mywallet_list").show();
        }, function () {
            $(".mywallet_list").hide();
        });
        $(".mywallet_list").hide();
    });


   // 个人ip登陆后的鼠标移入
$(function(){
	$(".accountList").hide();
	$("#my").mouseover(function(){
		$(".accountList").show();	
	}).mouseout(function(){
		$(".accountList").hide();
	});
})
$(function(){
	$(".accountList2").hide();
	$("#phone").mouseover(function(){
		$(".accountList2").show();	
	}).mouseout(function(){
		$(".accountList2").hide();
	});
})

$(document).ready(function(){
var T =getCookie('T');
    if(T != null){
    	var height = $('document').height()/2+110;
        $("#autoCenter").css('margin-top', height+'px');
        $('#myModal').css('hidden');
       
    }else{
    	 var height = $('document').height()/2+110 ;
         $("#autoCenter").css('margin-top', height+'px');
		//  $('#myModal').modal('show');
		 $('#myModal').css('show');
        document.cookie= "T=1";
    }
});

function getCookie(name){
var arr,reg=new RegExp("(^| )"+name+"=([^;]*)(;|$)");
if(arr=document.cookie.match(reg))
return unescape(arr[2]);
else
return null;
}


 var index_p = 0;
    $('.HomePageTab').mousemove(function(){
        index_p = $(this).index();
        $(this).addClass("color_btn_active").siblings().removeClass("color_btn_active");
        $('.HomePageTab_box').eq(index_p-1).show().siblings('.HomePageTab_box').hide();
    })

    $('.left_btn').click(function(){
        $('.subscribe_scroll_imgs_box').stop(true,true).animate({'left':0});
    })
    $('.right_btn').click(function(){
     $('.subscribe_scroll_imgs_box').stop(true,true).animate({'left':-278});
    })
    
    //新闻公告页
    $(".Art_list a").mouseenter(function(){
		$(this).find("h4").css({"color":"#dbb668"});
		$(this).find("p").css({"color":"#dbb668"});
		$(this).find("span").css({"color":"#dbb668"});
	});
	$(".Art_list a").mouseleave(function(){
		$(this).find("h4").css({"color":"#333333"});
		$(this).find("p").css({"color":"#666"});
		$(this).find("span").css({"color":"#999"});
	})
})
// header代号一

 // $('#phone_email').mouseover(function () {
    //     $('#phone_email2').show();
    // }).mouseout(function () {
    //     $('#phone_email2').hide();
    // });

// header代号二

    // $('#phone_email').mouseover(function () {
    //     $('#gz-qrcode').show();
    // }).mouseout(function () {
    //     $('#gz-qrcode').hide();
    // });
    // $('#phone400').mouseover(function () {
    //     $('#gz').show();
    // }).mouseout(function () {
    //     $('#gz').hide();
    // });
