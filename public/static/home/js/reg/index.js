$(function () {
    // 开关
    var Array = {
        on_off: false,
        on_off2: false,
        on_off3: false,
        on_off4: false
    };

    // 注册类型
    var reg_type = "phone";

    // 切换注册方式
    $(".reg_choose button").on("click", function () {
        $(this).addClass('active').siblings('button').removeClass('active');
        var showClass = $(this).attr("data-type"),
            noneClass = $(this).siblings('button').attr("data-type");
        reg_type = $(this).attr("data-way");
        $("." + showClass).css("display", "block");
        $("." + showClass).find("input").val("");
        $("." + noneClass).css("display", "none");
        $("." + noneClass).find("input").val("");
    });

    // 国籍切换
    $('#countries').change(function () {
        var country_code = $(this).val();
        $('#country').html("00" + country_code);
    });

    //手机号正则
    var _phone = /^1(3|4|5|7|8|6|9)\d{9}$/;

    // 手机号验证
    $('#phone').blur(function () {
        var phone_number_val = $(this).val();
        if (phone_number_val == "") {
            Array.on_off = false;
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip1);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else {
            if ($('#phone_auth').val() != "") {
                $("#phone_auth").trigger("blur");
            }
            if ($("#countries").val() != 86) {
                if (!_phone.test(phone_number_val)) {
                    Array.on_off = false;
                    layer.msg(tip2);
                    $(this).addClass('wrong');
                    $($(this).parent().find('.error')).html('<em></em>' + tip2);
                    $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
                    return;
                }
            } else if ($("#countries").val() == 86) {
                if (!_phone.test(phone_number_val)) {
                    Array.on_off = false;
                    $(this).addClass('wrong');
                    $($(this).parent().find('.error')).html('<em></em>' + tip2);
                    $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
                    return;
                }
            }
            var that = this;
            $.post(checkPhone, {
                phone: encodeURIComponent($(this).val()),
                country_code: $("#countries").val()
            }, function (d) {
                if (d.code != 10000) {
                    Array.on_off = false;
                    layer.msg(d.message);
                    $(that).addClass('wrong');
                    $($(that).parent().find('.error')).html('<em></em>' + d.message);
                    $(that).parent().find('.error').css('display', 'block').addClass('blur_error');
                    $(".send_authCode").attr("disabled", true); //不能发送验证码
                    $(".send_authCode").css("background", "#ccc");
                } else {
                    Array.on_off = true;
                    $(that).removeClass('wrong');
                    $(that).parent().find('.error').css('display', 'none')
                    $(".send_authCode").attr("disabled", false); //可以发送验证码
                    $(".send_authCode").css("background", "#3076cc");
                }
            }, 'json');
        }
    });

    // 手机验证码发送
    var regPhone = /^[0-9]*$/;
    var phone_statue = 0;
    $('.send_authCode').click(function () {
        var i = 120,
            tid2,
            _btn = $('.send_authCode'),
            phone_num = $("#phone").val();
        var obj = {
            phone: encodeURIComponent(phone_num),
            country_code: $("#countries").val(),
            validate: $('#img_code').val(),
            type: "register"
        }
        phone_statue = 1;
        if ($("#countries").val() == 86) {
            if (!(_phone.test(phone_num))) {
                phone_statue = 0;
            }
        } else {
            if (!(regPhone.test(phone_num))) {
                phone_statue = 0;
            }
        }
        if (phone_statue === 0) {
            layer.msg(tel_rule_tips);
        } else {
            if ($('#img_code').val() == '') {
                layer.msg('请先输入图形验证码');
                return;
            }
            $.post(authUrl, obj,
                function (d) {
                    if (d.code == 10000) {
                        //i = 120;
                        //tid2;
                        layer.msg(d.message);
                        tid2 = setInterval(function () {
                            _btn.attr("disabled", true);
                            _btn.text(i + tip21);
                            _btn.css("background", "#ccc");
                            i--;
                            if (i <= 0) {
                                _btn.removeAttr("disabled").text(tip20);
                                _btn.css("background", "#6189C5");
                                clearInterval(tid2);
                            }

                        }, 1000);
                    } else {
                        layer.msg(d.message);
                    }
                });
        }

    });


    // 手机验证码验证
    $('#phone_auth').blur(function () {
        var code_val = $(this).val();
        if (code_val == "") {
            Array.on_off = false;
            layer.msg(tip19);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip19);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else {
            var that = this;
            $.post(path_PhoneCode, {
                phone_code: code_val,
                phone: $('#phone').val(),
                type: "register"
            }, function (data) {
                if (data.code != 10000) {
                    layer.msg(data.message);
                    $(that).addClass('wrong');
                    $($(that).parent().find('.error')).html('<em></em>' + data.message);
                    $(that).parent().find('.error').css('display', 'block').addClass('blur_error');
                } else {
                    $(that).removeClass('wrong');
                    $(that).parent().find('.error').css('display', 'none');
                }
            }, 'json');
        }
    });

    // 邮箱
    // 邮箱注册时的正则验证
    var email = /^\w+((.\w+)|(-\w+))@[A-Za-z0-9]+((.|-)[A-Za-z0-9]+).[A-Za-z0-9]+$/;
    $('#email').blur(function () {
        var email_number_input = $(this).val();
        if (email_number_input == "") {
            layer.msg(tip8);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip8);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else if (!email.test(email_number_input)) {
            layer.msg(tip9);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip9);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else {
            if ($(".email_auth_code_input").val() != "") {
                $(".email_auth_code_input").trigger("blur");
            }

            var that = this;
            $.post(path_Email, {
                email: $(this).val(),
                country_code: $("#countries").val()
            }, function (d) {
                if (d.code != 10000) {
                    layer.msg(d.message);
                    $(that).addClass('wrong');
                    $($(that).parent().find('.error')).html('<em></em>' + d.message);
                    $(that).parent().find('.error').css('display', 'block').addClass('blur_error');
                    $(".send_emailCode").attr("disabled", true); //不能发送验证码
                    $(".send_emailCode").css("background", "#ccc");
                } else {
                    $(that).removeClass('wrong');
                    $(that).parent().find('.error').css('display', 'none');
                    $(".send_emailCode").attr("disabled", false); //可以发送验证码
                    $(".send_emailCode").css("background", "#3076cc");
                }
            }, 'json');
        }
    });

    // 邮箱验证
    var email_statue = 0;
    $('.send_emailCode').click(function () {
        var i = 120;
        tid2 = " ",
            _btn = $('.send_emailCode'),
            user_email = $("#email").val();
        var reg = /^\w+((.\w+)|(-\w+))@[A-Za-z0-9]+((.|-)[A-Za-z0-9]+).[A-Za-z0-9]+$/;
        var obj = {
            "email": user_email,
            country_code: $("#countries").val(),
            validate: $('#img_code').val(),
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
            $.post(emailAuth, obj, function (d) {
                if (d.code == 10000) {
                    layer.msg(d.message);
                    tid2 = setInterval(function () {
                        _btn.attr("disabled", true);
                        _btn.text(i + tip21);
                        _btn.css("background", "#ccc");
                        i--;
                        if (i <= 0) {
                            _btn.removeAttr("disabled").text(tip20);
                            _btn.css("background", "#6189C5");
                            clearInterval(tid2);
                        }

                    }, 1000);
                } else {
                    layer.msg(d.message);
                }
            });
        }
    });

    // 邮箱验证码
    $('#email_auth').blur(function () {
        var code_val = $(this).val();
        if (code_val == "") {
            layer.msg(tip19);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip19);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else {
            var that = this;
            $.post(path_EmailCode, {
                email_code: code_val,
                email: $('#email').val(),
                type: "register"
            }, function (data) {
                if (data.code != 10000) {
                    layer.msg(data.message);
                    $(that).addClass('wrong');
                    $($(that).parent().find('.error')).html('<em></em>' + data.message);
                    $(that).parent().find('.error').css('display', 'block').addClass('blur_error');
                } else {
                    $(that).removeClass('wrong');
                    $(that).parent().find('.error').css('display', 'none');
                }
            }, 'json');
        }
    })

    // 登录密码验证
    //正则密码不能有空格
    var regNull = /^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,20}$/;
    $('#pass').blur(function () {
        var login_password_input = $(this).val();
        if (login_password_input == "") {
            Array.on_off2 = false;
            layer.msg(tip3);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip3);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else if (!regNull.test(login_password_input)) {
            Array.on_off2 = false;
            layer.msg(tip5);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip5);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else {
            if ($("#repass").val() != "") {
                $("#repass").trigger("blur");
            }
            $(this).removeClass('wrong');
            $(this).parent().find('.error').css('display', 'none');
            Array.on_off2 = true;
        }
    });

    // 再次输入登录密码验证
    $('#repass').blur(function () {
        var login_password_input2 = $('#pass').val();
        var repeat_password_input = $(this).val();
        if (repeat_password_input == "") {
            Array.on_off3 = false;
            layer.msg(tip6);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip6);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else if (repeat_password_input !== login_password_input2) {
            Array.on_off3 = false;
            layer.msg(tip7);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip7);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else {
            Array.on_off3 = true;
            $(this).removeClass('wrong');
            $(this).parent().find('.error').css('display', 'none');
        }
    });

    // 安全密码
    var regCode = /^\d{6}$/;
    $('#pwdtrade').blur(function () {
        var trading_password_input = $(this).val();
        var login_password_input = $("#pass").val();
        if (trading_password_input == "") {
            layer.msg(tip10);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip10);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else if (trading_password_input.length < 6) {
            layer.msg(tip11);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip11);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else if (!regCode.test(trading_password_input)) {
            layer.msg(tip12);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip12);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else if (login_password_input == trading_password_input) {
            layer.msg(tip13);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip13);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else {
            $(this).removeClass('wrong');
            $(this).parent().find('.error').css('display', 'none');
        }
    });

    // 再次输入安全密码验证
    $('#repwdtrade').blur(function () {
        var trading_password_input = $('#pwdtrade').val();
        var c_trading_password_input = $(this).val();
        if (c_trading_password_input == "") {
            c_jiaoyi = false;
            layer.msg(tip14);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip14);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else if (c_trading_password_input !== trading_password_input) {
            c_jiaoyi = false;
            layer.msg(tip15);
            $(this).addClass('wrong');
            $($(this).parent().find('.error')).html('<em></em>' + tip15);
            $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
        } else {
            c_jiaoyi = true;
            $(this).removeClass('wrong');
            $(this).parent().find('.error').css('display', 'none');
        }
    });

    // 提交
    $("#sure").click(function () {
        var pwd = $("#pass").val();
        var repwd = $("#repass").val();
        var res_pid = $("#pid").val();
        if (pwd != repwd) {
            layer.msg(tip7);
            return;
        }

        var yanzehngma = true;
        var trading_password_input = $('#pwdtrade').val();
        var c_trading_password_input = $('#repwdtrade').val();

        if (!regCode.test(trading_password_input)) {
            layer.msg(tip12);
            return false;
        }

        if (trading_password_input != c_trading_password_input) {
            layer.msg(tip15);
            return false;
        }

        var data = {
            type: reg_type,
            email: $('input[name="email"]').val(),
            phone: $('input[name="phone"]').val(),
            phone_code: $('input[name="phone_auth"]').val(),
            email_code: $('input[name="email_auth"]').val(),
            pwd: $('input[name="pass"]').val(),
            repwd: $('input[name="repass"]').val(),
            pwdtrade: $('input[name="pwdtrade"]').val(),
            repwdtrade: $('input[name="repwdtrade"]').val(),
            pid: $('input[name="pid"]').val(),
            country_code: $("#countries").val(),
            platform: "pc",
        };

        if ((!data.email.length > 0) && (!data.phone.length > 0)) {
            layer.msg(tip17);
            return false;
        }

        $.ajax({
            type: 'post',
            dataType: 'json',
            data: data,
            url: regUrl,
            success: function (data) {
                if (data.code == 10000) {
                    layer.msg(data.message);
                    setTimeout(function () {
                        window.location.href = "/index/User/safe";
                    }, 1000);
                } else {
                    layer.msg(data.message);
                }
            },
            error: function (e) {
                layer.msg(tip18);
            }
        });
        return false;
    });
});