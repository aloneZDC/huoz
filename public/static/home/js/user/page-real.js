$(document).ready(function () {
    var re1 = /^[a-zA-Z]{5,17}$/;   //护照正则2
    var re2 = /^[a-zA-Z0-9]{5,17}$/;	//护照正则1
    var junguan = /^[a-zA-Z0-9]{7,21}$/;  //军官正则
    var pattern = /(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/;  //身份证正则
    var reg = /^[\u4E00-\u9FA5]{1,6}$/; //  名字正则只能是中文，长度为2-7位
    var __phone = /^1(3|4|5|7|8)\d{9}$/; //手机号正则
    var publish = true,
        phone_statue = 0;

    $("#send_code").click(function () {
        var i = 120,
            tid2,
            _btn = $(this),
            phone_num = $("#phone_num").val();
        $.get(check_phone,{email:encodeURIComponent(phone_num)}, function(d){
            if(d.status != 4){
                layer.msg(d.msg);
                $(this).attr("data-key","off");
                publish = false;
            }else{
                $(this).attr("data-key","on");
                publish = true;
            }
        }, 'json');
        $.ajax({
            url: check_phone,
            data:{email:encodeURIComponent(phone_num)},
            type:'get',
            datatype:'json',
            success:function (d) {
                if(d.status != 4){
                    layer.msg(d.msg);
                    return false;
                }else{
                    tid2 = setInterval(function () {
                        if (_btn.attr("data-key") === 'off') {
                            _btn.attr("disabled", true);
                            _btn.removeClass("class");
                            _btn.addClass("button again");
                            i--;
                            _btn.text(i + send_resend);
                            if (i <= 0) {
                                _btn.removeAttr("disabled").text(send_resend2);
                                _btn.attr("data-key", "on");
                                clearInterval(tid2);
                            }
                        }

                    }, 1000);

                    if (!(__phone.test(phone_num))) {
                        phone_statue = 0;
                    } else {
                        phone_statue = 1;
                    }
                    if (phone_statue === 0) {
                        layer.msg(tel_rule_tips);
                    } else {
                        $.post("/ModifyMember/ajaxSandPhone", {phone: encodeURIComponent(phone_num)},
                            function (d) {
                                layer.msg(d.info);
                                if (d.status == 1) {
                                    i = 120;
                                    tid2;
                                    _btn.attr("data-key", "off");
                                }
                            });
                    }
                }
            }

        });
    });

    $('#submit_btn').click(function () {
        // var str = $('#input_name').val();
        // if (str == '') {
        //     $('#input_name').parent().find('.v_info').css('display', 'block').addClass('v_error').html(name_not_empty);
        //     publish = false;
        //     return false;
        // } else if (!reg.test(str)) {
        //     $('#input_name').parent().find('.v_info').css('display', 'block').addClass('v_error').html(namr_rules_required);//请将“字符串类型”要换成你要验证的那个属性名称！
        //     publish = false;
        //     return false;
        // } else {
        //     publish = true;
        //     $('#input_name').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
        // }

        // var val = $('#card_type').val();
        // var card = $('#card_id').val();
        // if (val == 1) {
        //     if (card == '') {
        //         $('#card_id').parent().find('.v_info').css('display', 'block').addClass('v_error').html(id_not_empty);
        //         publish = false;
        //         return false;
        //     } else if (!pattern.test(card)) {
        //         $('#card_id').parent().find('.v_info').css('display', 'block').addClass('v_error').html(id_error);
        //         publish = false;
        //         return false;
        //     } else {
        //         publish = true;
        //         $('#card_id').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
        //     }
        // } else if (val == 2) {
        //     if (card == '') {
        //         $('#card_id').parent().find('.v_info').css('display', 'block').addClass('v_error').html(id_not_empty);
        //         publish = false;
        //         return false;
        //     } else if (!re2.test(card) || !re1.test(card)) {
        //         $('#card_id').parent().find('.v_info').css('display', 'block').addClass('v_error').html(passport_not_corrct);
        //         publish = false;
        //         return false;
        //     } else {
        //         publish = true;
        //         $('#card_id').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
        //     }
        // } else if (val == 3) {
        //     if (card == '') {
        //         $('#card_id').parent().find('.v_info').css('display', 'block').addClass('v_error').html(id_not_empty);
        //         publish = false;
        //         return false;
        //     } else if (!junguan.test(card)) {
        //         $('#card_id').parent().find('.v_info').css('display', 'block').addClass('v_error').html(lan_account_Your_officer_incorrectly);
        //         publish = false;
        //         return false;
        //     } else {
        //         publish = true;
        //         $('#card_id').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
        //     }
        // }

        var phone_num = $("#phone_num").val();
        if (!$.trim(phone_num).length > 0) {
            $('#phone_num').parent().find('.v_info').css('display', 'block').addClass('v_error').html(tel_required);
            phone_statue = 0;
            publish = false;
            return false;
        } else {
            publish = true;
            phone_statue = 1;
            $('#phone_num').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
        }

        if (!(__phone.test(phone_num))) {
            $('#phone_num').parent().find('.v_info').css('display', 'block').addClass('v_error').html(tel_rule_tips);
            phone_statue = 0;
            publish = false;
            return false;
        } else {
            publish = true;
            phone_statue = 1;
            $('#phone_num').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
        }

        var phone_code = $("#phone_code").val();
        if (!$.trim(phone_code).length > 0) {
            $('#phone_code').parent().find('.v_info').css('display', 'block').addClass('v_error').html(tel_verification_coede);
            publish = false;
            return false;
        } else {
            publish = true;
            $('#phone_code').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
        }

        /*
         var l = $('#career_text').val().length;
         if (l == 0) {
         $('#career_text').parent().find('.v_info').css('display', 'block').addClass('v_error').html('职业格式不对!');
         publish = false;
         return false;
         } else {
         publish = true;
         $('#career_text').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
         }

         var l = $('#address_text').val().length;
         if (l == 0) {
         $('#address_text').parent().find('.v_info').css('display', 'block').addClass('v_error').html('地址格式不对!');
         publish = false;
         return false;
         } else {
         publish = true;
         $('#address_text').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
         }
         */
        if (publish === true) {
            var data = {
                   // name: str,
                    //countries: $("#countries").val(),
                   //  card_type: val,
                     //card_id: card,
                    phone: phone_num,
                    phone_code: phone_code,
                    //gender: $("#gender").val(),
                   // birthday: $("#birthday").val(),
                    //career: $("#career_text").val(),
                    //commonly_used_address: $("#address_text").val(),
                },
                load = layer.msg('提交中，请稍等···', {
                    icon: 16
                    , shade: 0.3
                    , time: 0
                    , scrollbar: false
                }),
                url = "/ModifyMember/simple_verify";

            $.ajax({
                type: 'post',
                dataType: 'json',
                data: data,
                url: url,
                success: function (callback) {
                    layer.close(load);
                    if (callback.Code == 1) {
                        layer.alert(callback.Msg, {icon: 6});
                        setInterval(function () {
                            alert(regester_success);
                            window.location.href = url;
                        }, 2500);
                    } else {
                        layer.alert(callback.Msg, {icon: 5});
                    }
                },
                error: function (e) {
                    layer.close(load);
                    layer.alert(netWork_error, {icon: 5});
                }
            });
            return false;
        }
    });

    var seniorBool = true;
    $('#submit_senior_btn').click(function () {
        if(seniorBool){
            seniorBool = false;
            var name = $('#name').val();
            if(name == ''){
                layer.msg(user_namenot_empty);
                seniorBool = true;
                return false;
            }
            var number_id = $("#box_id_number").val();
             if(number_id == ''){
                layer.msg(idcard_number);
                seniorBool = true;
                return false;
            }
            
            var zhengmian = $('#zhengmian').val();
            if (zhengmian == '') {
                $('#zhengmian').parent().find('.v_info').css('display', 'block').addClass('v_error').html(idcard_not_posted);
                layer.msg(idcard_not_posted);
                publish = false;
                seniorBool = true;
                return false;
            } else {
                publish = true;
                $('#zhengmian').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
            }


            var beimian = $('#beimian').val();
            if (beimian == '') {
                $('#beimian').parent().find('.v_info').css('display', 'block').addClass('v_error').html(idcard_not_handed);
                layer.msg(idcard_not_handed);
                publish = false;
                seniorBool = true;
                return false;
            } else {
                publish = true;
                $('#beimian').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
            }

            var shouchi = $('#shouchi').val();
            if (shouchi == '') {
                $('#shouchi').parent().find('.v_info').css('display', 'block').addClass('v_error').html(no_idcard_in_hand);
                layer.msg(no_idcard_in_hand);
                publish = false;
                seniorBool = true;
                return false;
            } else {
                publish = true;
                $('#shouchi').parent().find('.v_info').css('display', 'none').removeClass('v_error').html('');
            }

            var juzhuzheng = $('#juzhuzheng').val();

            if (publish === true) {
                var form = document.querySelector("form");
                var formData = new FormData(form);
                // formData.append("auth_1", $('[name="auth_1"]')[0].files[0]);
                // formdata.append("auth_2", $('[name="auth_2"]')[0].files[0]);
                // formdata.append("auth_3", $('[name="auth_3"]')[0].files[0]);
                // formdata.append("countries", $('[name="countries"]').val());
                // formdata.append("name", $('[name="name"]').val());
                // formdata.append("idcard", $('[name="idcard"]').val());
                // formdata.append("nation_id", $('[name="nation_id"]').val());
                // formdata.append("sex", $('[name="sex"]').val());

                $.ajax({
                    type: "POST", // 数据提交类型
                    url: senior_verify_url, // 发送地址
                    data: formData, //发送数据
                    async: true, // 是否异步
                    processData: false, //processData 默认为false，当设置为true的时候,jquery ajax 提交的时候不会序列化 data，而是直接使用data
                    contentType: false ,//
                    dataType: 'json',
                    beforeSend:function(){
                        $(".shade").css("display","block");
                        $(".loading").css("display","block");
                    },
                    success:function(data) {
                        $(".shade").css("display","none");
                        $(".loading").css("display","none");
                        layer.msg(data.message);
                        if(data.status==1){
                            setTimeout(function(){
                                window.location.href = senior_verify_url;
                            },1000);
                        }
                        seniorBool = true;
                    }
                });
             //   $("form.real_auth_c").submit();
            }
        }
    });

});

