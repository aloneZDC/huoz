$(function(){

    function getrefreshtime(){
        var refreshtime='';
        var date = new Date();
        var hour=date.getHours();
        var minutes=date.getMinutes();
        minutes =minutes>10?minutes:"0"+(minutes+"");
        refreshtime=hour+":"+minutes;
        return refreshtime;
    }
    function newrefreshtimeval(){
        var newrefreshtime=getrefreshtime();
        $(".downpushtime").html(translation1 + translation2+newrefreshtime)
    }
    function creatScroll(id){
        return  new IScroll(document.querySelector(id), {
            //bounceEasing: 'elastic', bounceTime: 1200
            //滚动视图的常见配置项
            bounce: true, //弹簧效果
            //iscroll为了性能考虑，将touch相关的点击事件关闭了。
            click: true,
            tap: true,//打开移动端的点击事件
            mouseWheel: true,//支持滚轮事件
            scrollbars: true,//滚动条
            scrollX: true,//默认为false ,x轴方向是否可以滚动
            scrollY: true,//默认为true ,y轴方向是否可以滚动
            startX: 0,//x轴滚动的起始位置
            startY: 0,//y轴滚动的起始位置
            fadeScrollbars: true,//当滚动条显示时，不滚动时就看不到滚动条
            //配置滚动事件的侦听方式
            probeType: 1//1,2,3
        });
    }
    var myScroll =creatScroll(".content");
    var downpushimg = document.querySelector('.downpushimg img');
    var flag=true;//如果请求未结束   不允许第二次请求
    newrefreshtimeval();
    myScroll.on('scrollEnd', function(){
        if(myScroll.y<0 && myScroll.y>-50){
            //下拉刷新的div没有完全展示，回到-40的位置
            myScroll.scrollTo(0, -50, 200);
        }else if(myScroll.y>=0){
            if(flag){
                flag=false;
                page=1;
                // getmyprize();
            }
        }
        newrefreshtimeval();
        myScroll.refresh();
    })
    //上拉加载
    myScroll.on('scrollEnd', function(){
        if(myScroll.y>myScroll.maxScrollY && myScroll.y<myScroll.maxScrollY+40){
            //未达到加载条件
            myScroll.scrollTo(0, myScroll.maxScrollY+40, 200);
        }
        else if(myScroll.y==myScroll.maxScrollY){
            //达到了加载的条件
            if(flag){
                flag=false;
                getmyprize();
               
            }
        }
        myScroll.refresh();
    })
    
    var page=1;
    function getmyprize(){
        //加载时渲染页面
        $.ajax({
             type: "post",
             url: get_messages_url,
             data: {
                 page:page,
             },
             async: true,
             success: function (d) {
                $(".prize_list").empty();
                $(".loadmore").hide(); 
                 if (d.code = 10000) {
                     var prizelisthtml = '';
                     var content = '';
                    for(var i=0;i<d.result.length;i++){
                        if((d.result[i].msg_content).indexOf("http")!=-1){
                            content =  "<img src='"+d.result[i].msg_content+"' class='small' />"
                        }else{
                            content =   "<p>"+d.result[i].msg_content+"</p>"
                        }
                        if(d.result[i]._position=="l"){
                            prizelisthtml += "<li class='left_box'>"+
                                                "<div class='left_userinfo'>"+
                                                    "<img src='"+d.result[i].head +"' />"+
                                                    "<p>"+d.result[i].nick+"</p>"+
                                                    "</div>"+
                                                    "<p>"+d.result[i].msg_time+"</p>"+
                                                "<div class='leftuser'>"+
                                                    content+
                                                "</div>"+
                                            "</li>";
                        }else{
                            prizelisthtml += "<li class='right_box'>"+
                                               "<div class='rightuser'>"+
                                                    content+
                                               "</div>"+
                                                "<p>"+d.result[i].msg_time+"</p>"
                                           " </li>";
                        }
                    }
                    $(".prize_list").html(prizelisthtml);
                   
                    //当内容高度不够时 设置最小高度
                    var contentheight = $(".contentlist").height();
                    if(contentheight<myScroll.wrapperHeight){
                        $(".contentlist").css("height",myScroll.wrapperHeight+40);
                    }
                         myScroll.refresh();
                 }
                        myScroll.scrollTo(0, myScroll.maxScrollY+40, 200);
                        console.log(myScroll.maxScrollY)
                        page++;
                        flag=true;
             },
        });
    }
    //获取首次加载时的数据
    getmyprize();
    function isempty(){
       if($("#message_val").val()=="") {
           layer.msg("请输入留言信息");
           return false;
       }
       return true;
    }
    $(".send_btn span").click(function(){
       if(isempty()){
        $.ajax({
            "url": send_message_url,
            "type": "POST",
            "data": {
                msg_body:$("#message_val").val(),
            },
            success: function (data) {
                location.reload();
                console.log(data)
            }
        });
       }
    })
    // 上传图片
    $(".upload").on("change",function(){
        var file = this.files[0];;
        var reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = function (e) {
            var dx = (e.total / 1024) / 1024;
            if(this.result){
                $.ajax({
                    url: upload_url,
                    type: 'post',
                    dataType: 'json',
                    data: {img:this.result},
                    success: function (callback) {
                        if(callback.code==10000) {
                            getmyprize();
                        } else {
                            layer.msg("网络异常,请重试");
                        }
                    },
                    error: function (e) {
                        layer.alert("网络异常,请重试", {icon: 5});
                        return false;
                    }
                });
            }
        };
    })
    })