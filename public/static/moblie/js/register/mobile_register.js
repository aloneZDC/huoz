$(document).ready(function() {


    var random_code = $(".home_auth_code_input").val();
    var phoneNumber = $(".phone_number").val();
    var loginPassword = $(".login_password_input").val();
    var repeatPassword = $(".repeat_password_input").val();
    var res_pid = $(".res_pid").val();

    if (random_code == "" || phoneNumber == "" || loginPassword == "" || repeatPassword == "") {
        $("#zucenBth").attr("disabled", true);
    } else {
        $("#zucenBth").attr("disabled", false);
    }

    var reg_type = "email"; //0:email 1:phone
    // 切换注册方法
    // phone_progress  手机
    // email_progress  邮箱
    // progressActive  类名

    

    /*
    * by wangqq 2018/12/11
    * 取消按钮的显示
    */
    $(".myForm_box input").on("input",function(){
        if($(this).val().length>0){
            $(this).siblings(".cancel").css("display","block");
        }else{ 
            $(this).siblings(".cancel").css("display","none");
        }
    });

    /*
    * by wangqq 2018/12/11
    * 清除输入框的内容
    */
    $(".myForm_box .cancel").on("click",function(){
        $(this).css("display","none");
        $(this).siblings("input").val("");
    });

    $("#username").blur(function() {
        var user = $(this).val();
        // var reg = /^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/;
        // var reg = /^(?![0-9])[0-9A-Za-z]{6,12}$/;
        var reg = /^(?![0-9])[0-9A-Za-z\u4E00-\uFA29]{6,12}$/u;
        if(user == "") {
            layer.msg("用户名不能为空");
            return false;
        }
        
        if(!reg.test(user)) {
            layer.msg("用户名6-12位字母+数字且开头不能为数字");
            $(this).val("");
        }
    });

    // 下一步
    // $(".next-changes").on("click",function() {
    //     if($("#username").val() == "") {
    //         layer.msg('用户名不能为空');
    //         return;
    //     }else if($("#phone").val() == "") {
    //         layer.msg('手机号不能为空');
    //         return;
    //     }else if($(".home_auth_code_input").val() == "") {
    //         layer.msg('验证码不能为空');
    //         return;
    //     }else if($(".res_pid").val() == "") {
    //         layer.msg('邀请码不能为空');
    //         return;
    //     }
    //     var data = {
    //         type: "phone",
    //         phone: $('input[name="phone"]').val(),
    //         phone_code: $('input[name="home_auth_code_input"]').val(),
    //         pid: $('input[name="pid"]').val(),
    //         platform: "mobile",
    //         country_code: $("#country").val(),
    //         username: $("#username").val()
    //     };
    //     $.ajax({
    //         type: 'post',
    //         dataType: 'json',
    //         data: data,
    //         url: phoneChangeType,
    //         success: function(data) {
    //             if (data.code == 10000) {
    //                 layer.msg(data.message);
    //                 $(".change-one").hide();
    //                 $(".change-two").show();
    //             } else {
    //                 layer.msg(data.message);
    //             }
    //         },
    //         error: function(e) {
    //             layer.msg(tip18);
    //         }
    //     });
    // })
        

    $('.phone_progress_s').click(function() {
        $(".email_number_input").val("");
        reg_type = "phone";
        $(this).addClass('progressActive');
        $('.email_progress_s').removeClass('progressActive');
        $('.phone_number_box').css('display', 'block');
        $(".home_auth_code").show();
        $(".email_auth_code").hide()
        $('.auth_code_box').css('display', 'block');
        $('#countries').parent().parent().css('display', 'block');
        $('.email_number_box').css('display', 'none');
        $("#username").val("");
    })
//     $('.res_pid').blur(function() {
//         var res_pid = $(this).val();
//         if (res_pid == "") {
//             Array.on_off5 = true;
//             chebutt();
//             // $(this).parent().find('.error').css('display', 'block');
//             // $($(this).parent().find('.error')).html('<em></em>' + tip23);
//             // $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
//         } else {
//             $.post('/Reg/ajaxCheckpidCode', {
//                 pid: res_pid
//             }, function(data) {
//                 if (data.status == 0) {
//                     Array.on_off5 = false;
//                     layer.msg(data.info);
// //                  $($('.res_pid').parent().find('.error')).html('<em></em>' + data.info);
// //                  $('.res_pid').parent().find('.error').css('display', 'block').addClass('blur_error');
//                 } else {

//                     Array.on_off5 = true;
//                     //  $('.home_auth_code_input').parent().find('.error').css('display','none');
//                 }
//                 chebutt()
//             }, 'json')
//         }
//     })

    function chebutt() {
        kaiguan = true;
        Array.on_off4 = true;
        $.each(Array, function(k, v) {
            if (v == false) {
                kaiguan = false;
                return false;
            }
        })
        if (kaiguan) {
            $("#zucenBth").attr('disabled', false);
        } else {
            $("#zucenBth").attr('disabled', true);
        }
    }
    // $("#phone").val("");
    // // reg_type = "email";
    // // $(this).addClass('progressActive');
    // $(".home_auth_code").show();
    // $(".email_auth_code").hide();
    // $('.email_progress_s').removeClass('progressActive');
    // $('.email_number_box').css('display', 'none');
    // $('.phone_number_box').css('display', 'block');
    // $('.auth_code_box').css('display', 'none');
    // $('#countries').parent().parent().css('display', 'block');

    $('.email_progress_s').click(function() {
        $("#phone").val("");
        reg_type = "email";
        $(this).addClass('progressActive');
        $(".home_auth_code").hide();
        $(".email_auth_code").show();
        $('.phone_progress_s').removeClass('progressActive');
        $('.email_number_box').css('display', 'block');
        $('.phone_number_box').css('display', 'none');
        $('.auth_code_box').css('display', 'none');
        $('#countries').parent().parent().css('display', 'none');
        $("#username").val("");
    })

    var __phone = /^1(3|4|5|7|8|6|9)\d{9}$/; //手机号正则
    var _phone = /^[0-9]*$/;
    var phone_statue = 0;
    var email_statue = 0;
    var loadingFlag;
    //验证码发送
    $('.send_authCode').click(function() { 
        var num = $("#phone").val();
        var i = 60,
            tid2,
            _btn = $('.send_authCode'),
            phone_num = $("#phone").val();
        if(num==""){
            layer.msg(tip1);
            return false;
        }
        var obj = {
            validate:"",
            phone: encodeURIComponent(phone_num),
            country_code: $("#country").val(),
            type: "register"
        }
        phone_statue = 1
        if ($("#country").val() == 86) {
            if (!(__phone.test(phone_num))) {
                phone_statue = 0;
            }
        } else {
            if (!(_phone.test(phone_num))) {
                phone_statue = 0;
            }
        }
        if (phone_statue === 0) {
            layer.msg(tel_rule_tips);
        } else {
            loadingFlag = layer.load('loading...',{icon: tip26,shade: [0.3, '#000'], time:8000}); 
            $.post(authUrl, obj,
                function(d) {
                    if (d.code == 10000) {
                        layer.close(loadingFlag);
                        layer.msg(d.message);
                        tid2 = setInterval(function() {
                            _btn.attr("disabled", true);
                            _btn.text(i + tip21);
                            _btn.css("color", "#efefef");
                            i--;
                            if (i <= 0) {
                                _btn.removeAttr("disabled").text(tip20);
                                _btn.css("color", "#E94827");
                                clearInterval(tid2);
                            }

                        }, 1000);
                    } else {
                        layer.close(loadingFlag);
                        layer.msg(d.message);
                    }
                }).error(function() {
                    layer.msg(tip27);
                })
        }
    });

    // 邮箱验证
    $('.send_emailCode').click(function() {
        // var img_code=$("#img_code").val();
        var num = $("#userEmail").val() + emailCodeChange;
        var i = 60;
        tid2 = " ",
        _btn = $('.send_emailCode'),
        user_email = $("#userEmail").val() + emailCodeChange;
        var reg = /^\w+((.\w+)|(-\w+))@[A-Za-z0-9]+((.|-)[A-Za-z0-9]+).[A-Za-z0-9]+$/;
        if(num==""){
            layer.msg("邮箱号码不能为空");
            return false;
        }
        var obj = {
            validate:"",
            "email": user_email,
            country_code: $("#countries").val(),
            type: "register"
        };
        if (!(reg.test(user_email))) {
            email_statue = 0;
        } else {
            email_statue = 1
        }
        if (email_statue === 0) {
            layer.msg(email_rule_tips);
        } else {
            loadingFlag = layer.load('loading...',{icon: tip26,shade: [0.3, '#000'], time:8000}); 
            $.post(emailAuth, obj, function(d) {
                if (d.code == 10000) {
                    layer.close(loadingFlag);
                    //i = 120;
                    //tid2;
                    layer.msg(d.message);
                    tid2 = setInterval(function() {
                        _btn.attr("disabled", true);
                        _btn.text(i + tip21);
                        _btn.css("color", "#fff");
                        i--;
                        if (i <= 0) {
                            _btn.removeAttr("disabled").text(tip20);
                            // _btn.css("background", "linear-gradient(-90deg,rgba(5,38,116,1) 0%,rgba(40,103,204,1) 100%)");
                            _btn.css("color", "#E94827");
                            clearInterval(tid2);
                        }

                    }, 1000);
                } else {
                    layer.close(loadingFlag);
                    layer.msg(d.message);
                }
            }).error(function() {
                layer.msg(tip27);
            })
        }
    });

    // 输入验证
    var Array = {
        on_off: false,
        on_off2: false,
        on_off3: false,
        on_off4: false
    };
    var jiaoyi = true;
    var c_jiaoyi = true;


    // 验证是否存在
    $('#phone').bind('blur', function() {

    });


    // 手机号码验证
    var phone = /^1[3456789]\d{9}$/; //手机正则

    $('.phone_number').blur(function() {
        var phone_number_val = $(this).val();

        if (phone_number_val == "") {
            Array.on_off = false;
            layer.msg(tip1);
//          $($(this).parent().find('.error')).html('<em></em>' + tip1);
//          $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else {
            if($('.home_auth_code_input').val()!=""){
                $(".home_auth_code_input").trigger("blur");
            }
            if ($("#country").val() != 86) {
                if (!_phone.test(phone_number_val)) {
                    Array.on_off = false;
                    layer.msg(tip2);
                    return;
                }
            } else if ($("#country").val() == 86) {
                if (!phone.test(phone_number_val)) {
                    Array.on_off = false;
                    layer.msg(tip2);
                    return;
                }
            }
            $.post(checkPhone, {
                phone: encodeURIComponent($(this).val()),
                country_code: $("#country").val()
            }, function(d) {
                if (d.code != 10000) {
                    Array.on_off = false;
                     layer.msg(d.message);
                } else {
                    Array.on_off = true;
                }
                chebutt()
            }, 'json');
        }
    })


    // 验证码验证
    $('.home_auth_code_input').blur(function() {
        var code_val = $(this).val();
        if (code_val == "") {
            Array.on_off = false;
            layer.msg(tip19);
        } else {
            $.post(path_PhoneCode, {
                phone_code: code_val,
                phone: $('#phone').val(),
                type: "register"
            }, function(data) {
                if (data.code != 10000) {
                    Array.on_off = false;
                    layer.msg(data.message);
                } else {
                    Array.on_off = true;
                }
                chebutt()
            }, 'json');
        }
    })

    $('.email_auth_code_input').blur(function() {
        var code_val = $(this).val();
        if (code_val == "") {
            Array.on_off = false;
            layer.msg(tip19);
        } else {
            $.post(path_EmailCode, {
                email_code: code_val,
                email: $('#userEmail').val() + emailCodeChange,
                type: "register"
            }, function(data) {
                if (data.code != 10000) {
                    Array.on_off = false;
                    layer.msg(data.message);
                } else {
                    Array.on_off = true;
                }
                chebutt()
            }, 'json');
        }
    })


    // 登录密码验证
    var regNull = /^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,20}$/; //正则密码不能有空格
    $('.login_password_input').blur(function() {
        var login_password_input = $(this).val();
        if (login_password_input == "") {
            Array.on_off2 = false;
            layer.msg(tip3);
        } else if (!regNull.test(login_password_input)) {
            Array.on_off2 = false;
            layer.msg(tip5);
            $(this).val("");
        } else {
            if($(".repeat_password_input").val()!=""){
                $(".repeat_password_input").trigger("blur");
            }
            Array.on_off2 = true;
        }
        chebutt()
    })



    //再次输入登录密码验证
    $('.repeat_password_input').blur(function() {
        var login_password_input2 = $('.login_password_input').val();
        var repeat_password_input = $(this).val();

        if (repeat_password_input == "") {
            Array.on_off3 = false;
            layer.msg(tip6);
        }else if (repeat_password_input !== login_password_input2) {
            c_jiaoyi = false;
            layer.msg("两次登录密码输入不一致");
            $(this).val("");
        } else {
            Array.on_off3 = true;
        }
        chebutt()
    })
    // 邮箱注册时的正则验证
    var email = /^\w+((.\w+)|(-\w+))@[A-Za-z0-9]+((.|-)[A-Za-z0-9]+).[A-Za-z0-9]+$/;
    $('.email_number_input').blur(function() {
        var email_number_input = $(this).val();
        if (email_number_input == "") {
            Array.on_off = false;
            layer.msg(tip8);
        } else if (!email.test(email_number_input + emailCodeChange)) {
            Array.on_off = false;
            layer.msg(tip9);
        } else {
            if($(".email_auth_code_input").val()!=""){
                $(".email_auth_code_input").trigger("blur");
            }
            $.post(path_Email, {
                email: email_number_input  + emailCodeChange,
                country_code: $("#countries").val()
            }, function(d) {
                if (d.code != 10000) {
                    Array.on_off = false;
                    layer.msg(d.message);
                    $(".send_emailCode").attr("disabled", true); //不能发送验证码
                    // $(".send_emailCode").css("background", "#ccc");
                    $("#zucenBth").attr('disabled', true);
                } else {
                    Array.on_off = true;
                    $(".send_emailCode").attr("disabled", false); //可以发送验证码
                    // $(".send_emailCode").css("background", "linear-gradient(-90deg,rgba(5,38,116,1) 0%,rgba(40,103,204,1) 100%)");
                    $("#zucenBth").attr('disabled', false);
                }
                chebutt()
            }, 'json');
        }
        chebutt()
    })

    // 交易密码
    var regCode = /^\d{6}$/;
    $('.trading_password_input').blur(function() {
        var trading_password_input = $(this).val();
        if (trading_password_input == "") {
            jiaoyi = false;
            layer.msg(tip10);
        } else if (trading_password_input.length < 6) {
            jiaoyi = false;
             layer.msg(tip11);
        } else if (!regCode.test(trading_password_input)) {
            jiaoyi = false;
            layer.msg(tip12);
        } else if (login_password_input == trading_password_input) {
            jiaoyi = false;
            layer.msg(tip13);
        } else {
            jiaoyi = true;
            $(this).parent().find('.error').css('display', 'block').html('').addClass('blur_error blur_success');
        }
    })

    // 再次输入交易密码验证
    $('.c_trading_password_input').blur(function() {
        var trading_password_input = $('.trading_password_input').val();
        var c_trading_password_input = $(this).val();
        if (c_trading_password_input == "") {
            c_jiaoyi = false;
            layer.msg(tip14);
        } else if (c_trading_password_input !== trading_password_input) {
            c_jiaoyi = false;
            layer.msg("两次交易密码输入不一致");
            $(this).val("");
        } else {
            c_jiaoyi = true;
            $(this).parent().find('.error').css('display', 'block').html('').addClass('blur_error blur_success');
        }
    })


    // 下一步按钮点击事件
    // 第一个  注册点击事件
    var login_password_input = '';
    var login_url = '';
    // 提交
    $("#sure").click(function() {  
        var pwd = $(".login_password_input").val();
        var repwd = $(".repeat_password_input").val();
        var res_pid = $(".res_pid").val();
        var jpwd = $(".trading_password_input").val();
        var rejpwd = $(".c_trading_password_input").val();
        if($("#username").val() == "") {
            layer.msg('用户名不能为空');
            return;
        }else if(pwd == ""){
            layer.msg("登录密码不能为空");
            return;
        }else if(repwd == ""){
            layer.msg("确认登录密码不能为空");
            return;
        } else if(jpwd == ""){
            layer.msg("交易密码不能为空");
            return;
        }else if(rejpwd == ""){
            layer.msg("确认交易密码不能为空");
            return;
        }else if(jpwd.length < 6 || rejpwd.length < 6) {
            layer.msg("交易密码长度为6位");
            return;
        }
        else {
            var kaiguan = true;
            Array.on_off4 = true;
            $.each(Array, function(index, val) {

                if (val == false) {
                    kaiguan = false;
                    return false;
                }
            })
            if (kaiguan) {
                // $('.progress_box_show').css('display', 'none');
                $('.trading_box').css('display', 'block');
                $('.myForm_UL li').eq(1).addClass('active');
            }
        }
        
    	var yanzehngma = true;
        var trading_password_input = $('.trading_password_input').val();
        var c_trading_password_input = $('.c_trading_password_input').val();

        var data = {
            type: reg_type,
            phone: $('input[name="phone"]').val(),
            email: $('input[name="email"]').val() + emailCodeChange,
            phone_code: $('input[name="home_auth_code_input"]').val(),
            email_code: $('input[name="email_auth_code_input"]').val(),
            pwd: $('input[name="pwd"]').val(),
            repwd: $('input[name="repwd"]').val(),
            pwdtrade: $('input[name="pwdtrade"]').val(),
            repwdtrade: $('input[name="repwdtrade"]').val(),
            pid: $('input[name="pid"]').val(),
            platform: "mobile",
            country_code: $("#country").val(),
            username: $("#username").val()
        };

        // if ((!data.email.length > 0) && (!data.phone.length > 0)) {
        //     layer.msg(tip17);
        //     return false;
        // }

        $.ajax({
            type: 'post',
            dataType: 'json',
            data: data,
            url: regUrl,
            success: function(data) {
                if (data.code == 10000) {
                    layer.msg(data.message);
					setTimeout(function(){
						window.location.href = downLoadUrl;
					},3000);

                } else {
                    layer.msg(data.message);
                }
            },
            error: function(e) {
                layer.msg(tip18);
            }
        });
        return false;
    });

});
  