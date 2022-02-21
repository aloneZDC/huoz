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
myScroll.scrollTo(0, -50, 200);
//下拉刷新
myScroll.on('scroll', function(){
    if(myScroll.y<0 && myScroll.y>-50){
        //未达到刷新条件
        $(".downpushtext").html(translation3);//下拉可以刷新
        $(".downpushimg>img").css({"transition": "300ms transform","transform":"rotate(360deg)"})
    }
    else if(myScroll.y>=0){
        //达到了刷新的条件
        $(".downpushtext").html(translation4);
        $(".downpushimg>img").css({"transition": "300ms transform","transform":"rotate(180deg)"})
    }
})
myScroll.on('scrollEnd', function(){
    if(myScroll.y<0 && myScroll.y>-50){
        //下拉刷新的div没有完全展示，回到-40的位置
        myScroll.scrollTo(0, -50, 200);
    }else if(myScroll.y>=0){
        $(".downpushimg>img").css({"transform":"rotate(0deg)"});
        $(".downpushimg>img").attr("src",commonURL+"ajax-loader.gif")
        $(".downpushtext").html(translation5)
        if(flag){
            flag=false;
            page=1;
            getmyprize();
        }
    }
    newrefreshtimeval();
    myScroll.refresh();
})
//上拉加载
myScroll.on('scroll', function(){
    if(myScroll.y>myScroll.maxScrollY && myScroll.y<myScroll.maxScrollY+40){
        //未达到加载条件
        $(".loadmoretext").html(translation6)
    }
    else if(myScroll.y<=myScroll.maxScrollY){
        //达到了加载的条件
        $(".loadmoretext").html(translation6);//"松开立即加载"
        $(".loadmoreimg>img").css({"transition": "300ms transform","transform":"rotate(180deg)"})
    }
})
myScroll.on('scrollEnd', function(){
    if(myScroll.y>myScroll.maxScrollY && myScroll.y<myScroll.maxScrollY+40){
        //未达到加载条件
        myScroll.scrollTo(0, myScroll.maxScrollY+40, 200);
    }
    else if(myScroll.y==myScroll.maxScrollY){
        //达到了加载的条件
        $(".loadmoreimg>img").css({"transform":"rotate(360deg)"});
        $(".loadmoreimg>img").attr("src",commonURL+"ajax-loader.gif")
        $(".loadmoretext").html(translation8)//正在加载
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
         url: _url,
         data: {
             page:page,
         },
         async: true,
         success: function (d) {
            if(page==1){
                $(".prize_list").empty();
                myScroll.scrollTo(0, -50, 200);
                $(".loadmore").hide();
            }
                 
             if (d.code = 10000) {
                 var addressname= "";
                for(var i=0;i<d.result.length;i++){
                    addressname="addressname"+i;
                    var prizelisthtml="<li class='userinfo'>"+
                                        "<div class='left'>"+
                                            "<p>"+lan_order_number+":</p>"+
                                            "<p>"+lan_event_registration2+":</p>"+
                                            "<p>"+lan_event_registration5+":</p>"+
						                    "<p>"+lan_event_registration9+":</p>"+
                                            "<p>"+lan_event_registration3+":</p>"+
                                            "<p>"+lan_event_registration4+":</p>"+
                                           " <p>"+lan_event_registration8+":</p>"+
                                            "<p>"+translation9+":</p>"+
                                            // "<p>"+translation10+":</p>"+
                                            "<p>"+translation11+":</p>"+
                                            // "<p>"+translation12+"</p>"+
                                        "</div>"+
                                        " <div class='right'>"+
                                            "<p>"+d.result[i].trade_no+"</p>"+
                                            "<p>"+d.result[i].name+"</p>"+
                                            "<p>"+d.result[i].sex+"</p>"+
						                    "<p>"+d.result[i].age+"</p>"+
                                            "<p>"+d.result[i].idcard+"</p>"+
                                            "<p>"+d.result[i].phone+"</p>"+
                                            "<p>"+d.result[i].passport+"</p>"+
                                            "<p>"+d.result[i].add_time+"</p>"+
                                            // "<p>"+d.result[i].start_time+"</p>"+
                                            "<p>"+d.result[i].pay_num+"</p>"+
                                            // "<p>"+
                                            // "<input type='text'" + " value="+d.result[i].qq+ " readonly='readonly'"+" id='"+addressname+"'>"+
                                            // "<span class='copybtn' onclick=copy('"+addressname+"')"+">"+translation13+"</span>"+
                                            // "</p>"+
                                       " </div>"+
                                    "</li>";
                    $(".prize_list").append(prizelisthtml);
                }
                page++;
                //当内容高度不够时 设置最小高度
                var contentheight = $(".contentlist").height();
                if(contentheight<myScroll.wrapperHeight){
                    $(".contentlist").css("height",myScroll.wrapperHeight+40);
                }
                    $(".loadmore").show();
                     myScroll.refresh();
             }
             if(d.result.length==0){
                    myScroll.scrollTo(0, myScroll.maxScrollY+40, 200);
                   layer.msg(translation14);
            }
            
            $(".loadmoreimg>img").attr("src",commonURL+"up.png");
            $(".downpushimg>img").attr("src",commonURL+"down.png");
            flag=true;
         },
    });
}
//获取首次加载时的数据
getmyprize();
var client = getCookie('odrplatform');//客户端
$(".back").on("click",function(){
    if(client == 'android'){
          apps.goback();
      }else if(client == 'ios'){
           window.webkit.messageHandlers.iosAction.postMessage("goback");
      }else{
          history.go(-1);
      }
});

})