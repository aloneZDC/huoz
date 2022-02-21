"use strict";

function Password() {
    this.name = 'Password';
};
//$('.login_password_input').blur(function() {
//     var login_password_input = $(this).val();
//      if (login_password_input == "") {
//          Array.on_off2 = false;
//          $($(this).parent().find('.error')).html('<em></em>' + tip3);
//          $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
//      } else if (!regNull.test(login_password_input)) {
//          Array.on_off2 = false;
//          $($(this).parent().find('.error')).html('<em></em>' + tip5);
//          $(this).parent().find('.error').css('display', 'block').addClass('blur_error');
//      } else {
//          Array.on_off2 = true;
//          $(this).parent().find('.error').css('display', 'block').html('').addClass('blur_error blur_success');
//      }
//      chebutt()
//  })
Password.prototype.loginPwd = function() {
	var regNull = /^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,20}$/; //正则密码不能有空格
    var oldpwd = $("#tagContent0 input[name='oldpwd']").val();
    var pwd = $("#tagContent0 input[name='pwd']").val();
    var repwd = $("#tagContent0 input[name='repwd']").val();

    if (oldpwd.length < 8) {
        Layer.msg("{:L('lan_correct_login_password')}");
        return false;
    }
    if (pwd.length < 8) {
        Layer.msg("{:L('lan_password_length_between')}");
        return false;
    }else if(!regNull.test(pwd)){
    	Layer.alert("{:L('lan_password_entered_format')}");
        return false;
    }

    if (pwd != repwd) {
        Layer.msg("{:L('lan_user_two_not_same')}");
        return false;
    }

    $.post("/user_index/pwd", {'oldpwd': oldpwd, 'pwd': pwd, 'repwd': repwd}, function(data) {
        if (data.code == 1) {
            Layer.msg(data.msg);
            return false;
        } else {
            Layer.alert(data.msg + ', {:L("lan_need_sign_again")}', function(flag) {
                self.location="/user/logout";
            });
        }
    });

    return false;

};

Password.prototype.tradePwd = function() {

    var oldpwd = $("#tagContent1 input[name='oldpwd']").val();
    var oldpwdtrade = $("#tagContent1 input[name='oldpwdtrade']").val();
    var pwd = $("#tagContent1 input[name='pwd']").val();
    var repwd = $("#tagContent1 input[name='repwd']").val();
	var idcard1 = $("#idcard1_hide").val();
	var idcard2 = $("#idcard2_hide").val();
	var idcard3 = $("#idcard3_hide").val();

    if (oldpwd.length < 6) {
        Layer.msg("{:L('lan_correct_login_password')}");
        return false;
    }

    if (oldpwdtrade.length < 6) {
        Layer.msg("{:L('lan_correct_transaction_password')}");
        return false;
    }

    if (pwd != repwd) {
        Layer.msg("{:L('lan_user_two_not_same')}");
        return false;
    }

    if (pwd.length < 6) {
        Layer.msg("{:L('lan_password_length_between2')}");
        return false;
    }

	if (!idcard1) {
        Layer.msg("{:L('lan_upload_front_photo')}");
        return false;
	}

	if (!idcard2) {
        Layer.msg("{:L('lan_upload_reverse_photo')}");
        return false;
	}

	if (!idcard3) {
        Layer.msg("{:L('lan_upload_handheld_photo')}");
        return false;
	}

    $.post('/user_index/pwdtrade', {'oldpwd': oldpwd, 'oldpwdtrade': oldpwdtrade, 'pwd': pwd, 'repwd': repwd, 'idcard1': idcard1, 'idcard2': idcard2, 'idcard3':idcard3}, function(data) {

        if (data.code == 1) {
            Layer.msg(data.msg);
        } else if(data.code == 3) {
			Layer.msg(data.msg, function() {
				self.location = "/user/logout";
			});
		} else {
            Layer.msg(data.msg + ', {:L("lan_need_sign_again")}', function() {
                self.location = "/user/logout";
            });
        }

    });

    return false;

};

Password.prototype.resetTradePwd = function() {

    var login_pwd = $('#login_pwd').val();
    if (!login_pwd) {
        Layer.msg("{:L('lan_correct_login_password')}");
        return false;
    }

    var phone_code = $('#phone_code').val();
    if (!phone_code) {
        Layer.msg("{:L('lan_correct_verification_code')}");
        return false;
    }

    var new_tradepwd = $('#new_tradepwd').val();
    if (!new_tradepwd) {
        Layer.msg("{:L('lan_please_transaction_password')}");
        return false;

    }
    if (new_tradepwd.length < 6 || new_tradepwd.length > 20) {
        Layer.msg("{:L('lan_password_length_between2')}");
        return false;
    }

    var renew_tradepwd = $('#renew_tradepwd').val();
    if (!renew_tradepwd) {
        Layer.msg("{:L('lan_reg_iput_tradePass_again')}");
        return false;
    }

    if (new_tradepwd != renew_tradepwd) {
        Layer.msg("{:L('lan_user_two_not_same')}");
        return false;
    }

	var idcard1 = $("#idcard1_hide").val();
	var idcard2 = $("#idcard2_hide").val();
	var idcard3 = $("#idcard3_hide").val();
	if (!idcard1) {
        Layer.msg("{:L('lan_upload_front_photo')}");
        return false;
	}

	if (!idcard2) {
        Layer.msg("{:L('lan_upload_reverse_photo')}");
        return false;
	}

	if (!idcard3) {
        Layer.msg("{:L('lan_upload_handheld_photo')}");
        return false;
	}

    var idcard = $('#idcard').val();

    var json = {'oldpwd': login_pwd, 'code': phone_code, 'pwd': new_tradepwd, 'repwd': renew_tradepwd, 'idcard': idcard, 'idcard1': idcard1, 'idcard2': idcard2, 'idcard3':idcard3};

    $.post('/user_index/repwdtrade', json, function(flag) {

        if (flag.code == 1) {
            Layer.msg(flag.msg);
            return false;
        } else if(flag.code == 3) {
			Layer.msg(flag.msg, function() {
				self.location = "/user/logout";
			});
		} else {
            Layer.msg(flag.msg + ', {:L("lan_need_sign_again")}', function() {
                self.location = "/user/logout";
            });
        }

    });

    return false;
};

Password.prototype.getcode = function() {
    $('#trade_get_code').hide();
    $('#trade_get_code2').show();
    $.post("/ajax/sendmsg1", {type:104,voice:1}, function(){});
}


Password.prototype.modifyIdcard = function() {

	var id = $("#id_hide").val();
	var idcard1 = $("#idcard1_hide").val();
	var idcard2 = $("#idcard2_hide").val();
	var idcard3 = $("#idcard3_hide").val();
	if (!idcard1) {
        Layer.msg("{:L('lan_upload_front_photo')}");
        return false;
	}

	if (!idcard2) {
        Layer.msg("{:L('lan_upload_reverse_photo')}");
        return false;
	}

	if (!idcard3) {
        Layer.msg("{:L('lan_upload_handheld_photo')}");
        return false;
	}

    $.post('/user_index/modifyIdcard', {'id': id, 'idcard1': idcard1, 'idcard2': idcard2, 'idcard3':idcard3}, function(data) {
        Layer.msg(data.msg);
    });

    return false;
};

var Password = new Password();
