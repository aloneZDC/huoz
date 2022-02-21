///-------------------------------------------------------------------------
//jQuery弹出窗口 By Await [2009-11-22]
//--------------------------------------------------------------------------
/*参数：[可选参数在调用时可写可不写,其他为必写]
----------------------------------------------------------------------------
title:	窗口标题
content:  内容(可选内容为){ text | id | img | url | iframe }
width:	内容宽度
height:	内容高度
drag:  是否可以拖动(ture为是,false为否)
time:	自动关闭等待的时间，为空是则不自动关闭
showbg:	[可选参数]设置是否显示遮罩层(0为不显示,1为显示)
cssName:  [可选参数]附加class名称
------------------------------------------------------------------------*/
//示例:
//------------------------------------------------------------------------
//simpleWindown("例子","text:例子","500","400","true","3000","0","exa")
//------------------------------------------------------------------------
var showWindown = true;
var templateSrc = "../resource"; //设置loading.gif路径
function tipsWindown(title, content, width, height, drag, time, showbg, cssName) {
    $("#windown-box").remove();//请除内容
    var width = width >= 950 ? this.width = 950 : this.width = width;     //设置最大窗口宽度
    var height = height >= 600 ? this.height = 600 : this.height = height;  //设置最大窗口高度
    if (showWindown == true) {
        var simpleWindown_html = new String;
        simpleWindown_html = "<div id=\"windownbg\" style=\"height:" + $(document).height() + "px;filter:alpha(opacity=0);opacity:0;z-index: 999901\"></div>";
        simpleWindown_html += "<div id=\"windown-box\">";
	   	simpleWindown_html += "<div id=\"windown-title\"><h2></h2><span id=\"windown-close\"><a onclick='closewindowtips_refresh()'></a></span></div>";
		simpleWindown_html += "<div id=\"windown-content-border\"><div id=\"windown-content\"></div></div>";
        simpleWindown_html += "</div>";
        $("body").append(simpleWindown_html);
        show = false;
    }
    contentType = content.substring(0, content.indexOf(":"));
    content = content.substring(content.indexOf(":") + 1, content.length);
    switch (contentType) {
        case "text":
            $("#windown-content").html(content);
            break;
        case "id":
            $("#windown-content").html($("#" + content + "").html());
            break;
        case "img":
            $("#windown-content").ajaxStart(function() {
                $(this).html("<img src='" + templateSrc + "/images/loading.gif' class='loading' />");
            });
            $.ajax({
                error: function() {
                    $("#windown-content").html("<p class='windown-error'>加载数据出错...</p>");
                },
                success: function(html) {
                    $("#windown-content").html("<img src=" + content + " alt='' />");
                }
            });
            break;
        case "url":
            var content_array = content.split("?");
            $("#windown-content").ajaxStart(function() {
                $(this).html("<img src='" + templateSrc + "/images/loading.gif' class='loading' />");
            });
            $.ajax({
                type: content_array[0],
                url: content_array[1],
                data: content_array[2],
                error: function() {
                    $("#windown-content").html("<p class='windown-error'>加载数据出错...</p>");
                },
                success: function(html) {
                    $("#windown-content").html(html);
                }
            });
            break;
        case "iframe":
            $("#windown-content").ajaxStart(function() {
                $(this).html("<img src='" + templateSrc + "/images/loading.gif' class='loading' />");
            });
            $.ajax({
                error: function() {
                    $("#windown-content").html("<p class='windown-error'>加载数据出错...</p>");
                },
                success: function(html) {
                    $("#windown-content").html("<iframe src=\"" + content + "\" width=\"100%\" height=\"" + parseInt(height) + "px" + "\" scrolling=\"auto\" frameborder=\"0\" marginheight=\"0\" marginwidth=\"0\"></iframe>");
                }
            });
    }
    $("#windown-title h2").html(title);
    if (showbg == "true") { $("#windownbg").show(); } else { $("#windownbg").remove(); };
    $("#windownbg").animate({ opacity: "0.5" }, "normal"); //设置透明度
	/*添加窗口拖拽支持*/
    $("#windown-box").show().draggable();//添加拖拽功能（需要Jquery UI支持）
	$("#windown-title").mouseover(function(){
		$(this).css("cursor","move");
		});
	/*添加窗口拖拽支持 End*/
    if (height >= 527) {
        $("#windown-title").css({ width: (parseInt(width) + 22) + "px" });
        $("#windown-content").css({ width: (parseInt(width) + 17) + "px", height: height + "px" });
    } else {
        $("#windown-title").css({ width: (parseInt(width) + 10) + "px" });
        $("#windown-content").css({ width: width + "px", height: height + "px" });
    }
    var cw = document.documentElement.clientWidth, ch = document.documentElement.clientHeight, est = document.documentElement.scrollTop;
    var _version = $.browser.version;
    if (_version == 6.0) {
        $("#windown-box").css({ left: "50%", top: (parseInt((ch) / 2) + est) + "px", marginTop: -((parseInt(height) + 53) / 2) + "px", marginLeft: -((parseInt(width) + 32) / 2) + "px", zIndex: "999999" });
    }else{
        $("#windown-box").css({ left: "50%", top: "50%", marginTop: -((parseInt(height) + 53) / 2) + "px", marginLeft: -((parseInt(width) + 32) / 2) + "px", zIndex: "999999" });
    };
    $("#windown-content").attr("class", "windown-" + cssName);
	
    var closeWindown = function() {
        $("#windownbg").remove();
        $("#windown-box").fadeOut("slow", function() { $(this).remove();});
    }
    if (time == "" || typeof (time) == "undefined") {
        $("#windown-close").click(function() {
            $("#windownbg").remove();
            $("#windown-box").fadeOut("slow", function() { $(this).remove();});
        });
    }else{
        setTimeout(closeWindown, time);
    }
}


function tipsWindown2(title, content, width, height, drag, time, showbg, cssName) {
    $("#windown-box").remove();//请除内容
    var width = width >= 950 ? this.width = 950 : this.width = width;//设置最大窗口宽度
    var height = height >= 600 ? this.height = 600 : this.height = height;//设置最大窗口高度
    if (showWindown == true) {
        var simpleWindown_html = new String;
        simpleWindown_html = "<div id=\"windownbg\" style=\"height:" + $(document).height() + "px;filter:alpha(opacity=0);opacity:0;z-index: 999901\"></div>";
        simpleWindown_html += "<div id=\"windown-box\">";
        simpleWindown_html += "<div id=\"windown-title\"><h2></h2></div>";
		simpleWindown_html += "<div id=\"windown-content-border\"><div id=\"windown-content\"></div></div>";
        simpleWindown_html += "</div>";
        $("body").append(simpleWindown_html);
        show = false;
    }
    contentType = content.substring(0, content.indexOf(":"));
    content = content.substring(content.indexOf(":") + 1, content.length);
    switch (contentType) {
        case "text":
            $("#windown-content").html(content);
            break;
        case "id":
            $("#windown-content").html($("#" + content + "").html());
            break;
        case "img":
            $("#windown-content").ajaxStart(function() {
                $(this).html("<img src='" + templateSrc + "/images/loading.gif' class='loading' />");
            });
            $.ajax({
                error: function() {
                    $("#windown-content").html("<p class='windown-error'>加载数据出错...</p>");
                },
                success: function(html) {
                    $("#windown-content").html("<img src=" + content + " alt='' />");
                }
            });
            break;
        case "url":
            var content_array = content.split("?");
            $("#windown-content").ajaxStart(function() {
                $(this).html("<img src='" + templateSrc + "/images/loading.gif' class='loading' />");
            });
            $.ajax({
                type: content_array[0],
                url: content_array[1],
                data: content_array[2],
                error: function() {
                    $("#windown-content").html("<p class='windown-error'>加载数据出错...</p>");
                },
                success: function(html) {
                    $("#windown-content").html(html);
                }
            });
            break;
        case "iframe":
            $("#windown-content").ajaxStart(function() {
                $(this).html("<img src='" + templateSrc + "/images/loading.gif' class='loading' />");
            });
            $.ajax({
                error: function() {
                    $("#windown-content").html("<p class='windown-error'>加载数据出错...</p>");
                },
                success: function(html) {
                    $("#windown-content").html("<iframe src=\"" + content + "\" width=\"100%\" height=\"" + parseInt(height) + "px" + "\" scrolling=\"auto\" frameborder=\"0\" marginheight=\"0\" marginwidth=\"0\"></iframe>");
                }
            });
    }
    $("#windown-title h2").html(title);
    if (showbg == "true") { $("#windownbg").show(); } else { $("#windownbg").remove(); };
    $("#windownbg").animate({ opacity: "0.5" }, "normal"); //设置透明度
	/*添加窗口拖拽支持*/
    $("#windown-box").show().draggable();//添加拖拽功能（需要Jquery UI支持）;
	$("#windown-title").mouseover(function(){
		$(this).css("cursor","move");
		});
	/*添加窗口拖拽支持End*/
    if (height >= 527) {
        $("#windown-title").css({ width: (parseInt(width) + 22) + "px" });
        $("#windown-content").css({ width: (parseInt(width) + 17) + "px", height: height + "px" });
    } else {
        $("#windown-title").css({ width: (parseInt(width) + 10) + "px" });
        $("#windown-content").css({ width: width + "px", height: height + "px" });
    }
    var cw = document.documentElement.clientWidth, ch = document.documentElement.clientHeight, est = document.documentElement.scrollTop;
    var _version = $.browser.version;
    if (_version == 6.0) {
        $("#windown-box").css({ left: "50%", top: (parseInt((ch) / 2) + est) + "px", marginTop: -((parseInt(height) + 53) / 2) + "px", marginLeft: -((parseInt(width) + 32) / 2) + "px", zIndex: "999999" });
    } else {
        $("#windown-box").css({ left: "50%", top: "50%", marginTop: -((parseInt(height) + 53) / 2) + "px", marginLeft: -((parseInt(width) + 32) / 2) + "px", zIndex: "999999" });
    };
    $("#windown-content").attr("class", "windown-" + cssName);
	
    var closeWindown = function() {
        $("#windownbg").remove();
        $("#windown-box").fadeOut("slow", function() { $(this).remove(); });
    }
    if (time == "" || typeof (time) == "undefined") {
        $("#windown-close").click(function() {
            $("#windownbg").remove();
            $("#windown-box").fadeOut("slow", function() { $(this).remove(); });
        });
    } else {
        setTimeout(closeWindown, time);
    }
}
