$(function(){


        // 设置昵称弹窗的关闭按钮
        $(".vo_all_close_x,.vo_all_button_o").click(function(){
            $(".vo_all").hide();
            $(".vo_all_zh").hide();
            $(".vo_all_cont_ni").val("");
        })
        // 设置昵称成功之后
        $(".vo_all_button_t").click(function(){
            if($(".vo_all_cont_ni").val() == ""){
                layer.msg("昵稱不能為空");
            }else if($(".vo_all_cont_ni").val().length < "4" || $(".vo_all_cont_ni").val().length > "30"){
                layer.msg("昵稱限制4~30個字符");
            }else{
                var nick = $(".vo_all_cont_ni").val();
                $.ajax({
                    type:"POST",
                    url:setNickAPI,
                    data:{
                        nick:nick
                    },
                    success: function (d) {
                        if (d.status != 1) {
                            layer.msg(d.info);
                        } else {
                            layer.msg(d.info);
                            setTimeout(function(){
                                    location.reload();
                            },1000)
                        }
                    }
                });
            }
        })
        // 搜索下拉框显示隐藏
        $(".otc_cheak").click(function(){
            $(".shade").show();
            $(".otc_search").slideToggle();
        })
        //点击取消
        $(".searchcancel").click(function(){
            money_type ="";
            pricesearch = 0;
            $(".payment").html("1");
            $("#price").val("")
            $(".otc_search").slideToggle();
            $(".shade").hide();
        })
        //点击搜索按钮
        $(".searchbtn").click(function(){
            price = $("#price").val();
            location.href = search_url+"?money_type="+money_type+"&price="+price;
            })
            
            layui.use(['form', 'layedit'], function(){
                var form = layui.form
                ,layer = layui.layer
                ,layedit = layui.layedit;
                form.on('select', function(data){
                    money_type = data.value;//得到select被选中的值]
                });
            });
        $(".criteria>ul>li").click(function(){
            $(".payment").html($(this).html());
            // money_type = $(this).attr("data_pay_way");
        })
        //点击下单
        $(".determine").click(function(){
            var url =  transaction_type == 'sell' ? buyAPI : sellAPI;
            if(!$("#trade_pwd").val() && transaction_type !="sell"){
                $(".trade_password>p").show();
                $("#trade_pwd").focus();
                return;
            }
            if(transaction_type == "buy"){
                if(!$(".checkbox input[type='radio']:checked").val()){
                    layer.alert(langreminder);
                    return;
                }
            }
            var money_type=$(".checkbox input[type='radio']:checked").attr("data-attr")?$(".checkbox input[type='radio']:checked").attr("data-attr"):"";
            $.ajax({
                "url": url,
                "type": "POST",
                "data": {
                    orders_id:orders_id,
                    num:inputval_r,
                    price:inputval_l,
                    pwd:$("#trade_pwd").val(),
                    currency_type : transaction_type,
                    money_type:money_type,
                },
                success: function (data) {
                    if(data.code==10000){
                        resetval();
                        location.href= trade_infoURL + "?trade_id=" + data.result.trade_id ;
                    }else if(data.status==10100){
                        location.href = LoginURL ;
                    }else{
                        if(data.message){
                            layer.msg(data.message);
                        }else{
                            layer.msg(data.info);
                        }
                    }
                }
            });
        })
        //取消按钮
        $(".cancel").click(function(){
            resetval();
            $(".otc_ordertk").hide();
            $(".shade").hide();
            $(".trade_l>p").hide();
        })
        //点击全部
        $(".allcny,.allcurrency").click(function(){
            max_val();
        });
        //输入的金额
        $(".trade_sum").keyup(function(){
            clearNoNum1(this);
        //  $(".trade_sum").val(String($(".trade_sum").val()).replace(/^(.*\..{2}).*$/, "$1"));
        inputval_r = ($(".trade_sum").val()/unit_price).toFixed(6);
        inputval_l = $(".trade_sum").val()
        $(".trade_num").val(inputval_r);
        judgementofsize();
    })
        //输入的币种数量
        $(".trade_num").keyup(function(){
            clearNoNum(this);
            $(".trade_num").val(String($(".trade_num").val()).replace(/^(.*\..{6}).*$/, "$1"));
            inputval_l = ($(".trade_num").val()*unit_price).toFixed(2);
            inputval_r = $(".trade_num").val();
            $(".trade_sum").val(inputval_l);
            if($(".trade_num").val()>max_num){
                max_val();
            }
            judgementofsize();
        })
        //输入交易密码
        $("#trade_pwd").keyup(function(){
            $(".trade_password>p").hide();
        })

})