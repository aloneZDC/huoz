function submitUserinfoForm() {
	var nickName = document.getElementById("nikeName").value;
	nickName = nickName.replace(/^\s*(.*?)[\s\n]*$/g, '$1');
	if (nickName.length > 20) {
		okcoinAlert("昵称不能超过20个字符");
		return;
	}
	var okcoin = nickName.toLowerCase().indexOf("okcoin");
	if (okcoin != -1) {
		okcoinAlert("昵称不能包含特殊字符");
		return;
	}
	document.getElementById("userinfoForm").submit();
}

function submitAddressForm() {
	var faddress = document.getElementById("faddress").value;
	faddress = faddress.replace(/^\s*(.*?)[\s\n]*$/g, '$1');
	if (faddress.length > 100) {
		okcoinAlert("联系地址不能超过100个字符");
		return;
	}
	var okcoin = faddress.toLowerCase().indexOf("okcoin");
	if (okcoin != -1) {
		okcoinAlert("联系地址不能包含特殊字符");
		return;
	}
	document.getElementById("userinfoForm").submit();
}

var fowTime = 5;
function resetPasswordForEmail() {
	var regu = /^[0-9]{6}$/;
	var re = new RegExp(regu);
	var validateCodeType = 0;
	var phoneCode = 0;
	var totpCode = 0;

	var newPassword = document.getElementById("newPassword").value;
	var newPassword2 = document.getElementById("newPassword2").value;
	var passwordType = document.getElementById("passwordType").value;
	var msg = isPassword(newPassword);
	if (msg != "") {
		document.getElementById("msg1").innerHTML = msg;
		return;
	} else {
		document.getElementById("msg1").innerHTML = "";
	}
	if (newPassword != newPassword2) {
		document.getElementById("msg2").innerHTML = "两次密码输入不一致";
		document.getElementById("newPassword2").value = "";
		return;
	} else {
		document.getElementById("msg2").innerHTML = "";
	}
	if (document.getElementById("phoneCode") != null) {
		phoneCode = trim(document.getElementById("phoneCode").value);
		if (!re.test(phoneCode)) {
			document.getElementById("phoneCodeTips").style.display = "";
			document.getElementById("phoneCodeTips").innerHTML = "短信验证码输入不合法";
			return;
		} else {
			document.getElementById("phoneCodeTips").innerHTML = "&nbsp;";
			document.getElementById("phoneCodeTips").style.display = "none";
		}
	}
	if (document.getElementById("totpCode") != null) {
		totpCode = trim(document.getElementById("totpCode").value);
		if (!re.test(totpCode)) {
			document.getElementById("totpCodeTips").innerHTML = "谷歌验证码输入不合法";
			return;
		} else {
			document.getElementById("totpCodeTips").innerHTML = "&nbsp;";
		}
	}

	var fid = document.getElementById("fid").value;
	var ev_id = document.getElementById("ev_id").value;
	var newuuid = document.getElementById("newuuid").value;

	var url = "/validate/resetPassword.html?random="
			+ Math.round(Math.random() * 100);
	var param = {
		validateCodeType : validateCodeType,
		totpCode : totpCode,
		phoneCode : phoneCode,
		newPassword : newPassword,
		newPassword2 : newPassword2,
		passwordType : passwordType,
		fid : fid,
		ev_id : ev_id,
		newuuid : newuuid
	};
	jQuery.post(
					url,
					param,
					function(data) {
						var result = eval('(' + data + ')');
						if (result.resultCode == -1) {
							okcoinAlert("请求超时");
						} else if (result.resultCode == -2) {
							document.getElementById("msg1").innerHTML = "密码格式不合法";
						} else if (result.resultCode == -3) {
							document.getElementById("msg2").innerHTML = "两次密码输入不一致";
							document.getElementById("newPassword2").value = "";
						} else if (result.resultCode == -4) {
							if (passwordType == 2) {
								document.getElementById("msg1").innerHTML = "交易密码不允许与登录密码一致";
							} else if (passwordType == 1) {
								document.getElementById("msg1").innerHTML = "登录密码不允许与交易密码一致";
							}
							document.getElementById("newPassword").value = "";
							document.getElementById("newPassword2").value = "";
						} else if (result.resultCode == -5) {
							okcoinAlert("用户未设置安全验证，不允许修改密码。");
						} else if (result.resultCode == -6) {
							okcoinAlert("邮箱验证未通过，链接仅15分钟有效");
						} else if (result.resultCode == -8) {
							if (result.errorNum == 0) {
								document.getElementById("totpCodeTips").innerHTML = "谷歌验证码错误多次，请2小时后再试！";
							} else {
								document.getElementById("totpCodeTips").innerHTML = "谷歌验证码错误！您还有"
										+ result.errorNum + "次机会";
								document.getElementById("totpCode").value = "";
							}
						} else if (result.resultCode == -9) {
							if (result.errorNum == 0) {
								document.getElementById("phoneCodeTips").style.display = "";
								document.getElementById("phoneCodeTips").innerHTML = "短信验证码错误多次，请2小时后再试！";
							} else {
								document.getElementById("phoneCodeTips").style.display = "";
								document.getElementById("phoneCodeTips").innerHTML = "短信验证码错误！您还有"
										+ result.errorNum + "次机会";
								document.getElementById("phoneCode").value = "";
							}
						} else if (result.resultCode == 0) {
							window.location.href = "/validate/reset.html?type=2";
						}
					});
}

function isPassword(pwd) {
	var desc = "";
	if (pwd == "") {
		desc = "请输入密码！";
	} else if (pwd.length < 6) {
		desc = "密码长度不能小于6！";
	} else if (pwd.length > 16) {
		desc = "密码长度不能大于16！";
	}
	return desc;
}
var secs = 121;
function sendMsg() {
	var type = document.getElementById("messageType").value;
	var url = "/user/sendMsg.html?random=" + Math.round(Math.random() * 100);
	var msgType = 1;
	if (msgType == 1) {
		document.getElementById("msgCodeBtn").disabled = true;
		for ( var num = 1; num <= secs; num++) {
			window.setTimeout("updateNumberRestPwd(" + num + ")", num * 1000);
		}
	}
	var param = {
		type : type
	};
	jQuery
			.post(
					url,
					param,
					function(data) {
						if (data == 0) {
							if (msgType != 1) {
								document.getElementById("msgCodeBtn").disabled = true;
								for ( var num = 1; num <= secs; num++) {
									window.setTimeout("updateNumberRestPwd("
											+ num + ")", num * 1000);
								}
							}
						} else if (data == -2) {
							document.getElementById("phoneCodeTips").innerHTML = "您没有绑定手机";
						} else if (data == -3) {
							if (document.getElementById("phoneCodeTips") != null) {
								document.getElementById("phoneCodeTips").style.display = "";
								document.getElementById("phoneCodeTips").innerHTML = "短信验证码错误多次，请2小时后再试！";
							}
						} else if (data == -1) {
							document.getElementById("phoneCodeTips").innerHTML = "请求超时";
						}
					});
}

function updateNumberRestPwd(num) {
	if (num == secs) {
		document.getElementById("msgCodeBtn").disabled = false;
		if (document.getElementById("validatePhoneNumber") != null) {
			document.getElementById("validatePhoneNumber").disabled = false;
		}
		document.getElementById("msgCodeBtn").value = "发送验证码";
	} else {
		var printnr = secs - num;
		document.getElementById("msgCodeBtn").value = printnr + "秒后可重发";
	}
}

function queryEmail() {
	var email = document.getElementById("uemail").value;
	if (!checkEmail(email) && !checkMobile(email)) {
		document.getElementById("emailMsg").innerHTML = "邮箱或手机格式不正确";
		return;
	} else {
		document.getElementById("emailMsg").innerHTML = "&nbsp;";
	}
	var url = "/validate/queryEmail.html?random="
			+ Math.round(Math.random() * 100);
	var param = {
		uemail : email
	};
	jQuery
			.post(
					url,
					param,
					function(data) {
						if(data == 0){
							window.location.href = "/validate/resetPhonePwd.html?phone="+email;
						} else if (data == 1) {
							window.location.href = "/validate/sendMailBack.html";
						} else if (data == 2) {
							window.location.href = "/validate/sendmailphoneback.html?phone="+email;
						} else if (data == -1) {
							document.getElementById("emailMsg").innerHTML = "邮箱或手机格式不正确";
						} else if (data == -2) {
							document.getElementById("emailMsg").innerHTML = "邮箱或手机错误，请确认后输入。";
						} else if (data == -3) {
							document.getElementById("emailMsg").innerHTML = "该邮件没有通过验证，不可用于找回密码。";
						} else if (data == -4) {
							document.getElementById("emailMsg").innerHTML = "用户请求过于频繁，请5分钟后再试";
						}
					});
}

function showChangeConfigure() {
	document.getElementById("changeConfigure").style.display = "";
	dialogBoxShadow(false);
	addMoveEvent("dialog_title_configure", "dialog_content_configure");
}

function closeChangeConfigure() {
	dialogBoxHidden();
	document.getElementById("changeConfigure").style.display = "none";
}
function configureSubmit() {
	var changeTotpCode = 0;
	var changePhoneCode = 0;
	var regu = /^[0-9]{6}$/;
	var re = new RegExp(regu);
	var desc = "";
	if (document.getElementById("configureTotpCode") != null) {
		changeTotpCode = document.getElementById("configureTotpCode").value;
		if (!re.test(changeTotpCode)) {
			desc = '谷歌验证码不合法';
		}
		if (desc != "") {
			document.getElementById("configureTotpCodeTips").innerHTML = "";
			document.getElementById("configureTotpCodeTips").innerHTML = desc;
			return;
		} else {
			document.getElementById("configureTotpCodeTips").innerHTML = "&nbsp;";
		}
	}
	if (document.getElementById("configurePhoneCode") != null) {
		changePhoneCode = document.getElementById("configurePhoneCode").value;
		if (!re.test(changePhoneCode)) {
			desc = '短信验证码不合法';
		}
		if (desc != "") {
			document.getElementById("configurePhoneCodeTips").innerHTML = "";
			document.getElementById("configurePhoneCodeTips").innerHTML = desc;
			return;
		} else {
			document.getElementById("configurePhoneCodeTips").innerHTML = "&nbsp;";
		}
	}
	var url = "/user/openTradePwd.html?random="
			+ Math.round(Math.random() * 100);
	var param = {
		totpCode : changeTotpCode,
		phoneCode : changePhoneCode
	};
	jQuery
			.post(
					url,
					param,
					function(data) {
						var result = eval('(' + data + ')');
						if (result != null) {
							if (result.resultCode == -11) {
								document
										.getElementById("configureTotpCodeTips").innerHTML = "谷歌验证码不合法";
							} else if (result.resultCode == -12) {
								document
										.getElementById("configurePhoneCodeTips").innerHTML = "短信验证码不合法";
							} else if (result.resultCode == -8) {
								if (result.errorNum == 0) {
									document
											.getElementById("configureTotpCodeTips").innerHTML = "谷歌验证码错误多次，请2小时后再试！";
								} else {
									document
											.getElementById("configureTotpCodeTips").innerHTML = "谷歌验证码错误！您还有"
											+ result.errorNum + "次机会";
									document
											.getElementById("configureTotpCode").value = "";
								}
							} else if (result.resultCode == -9) {
								if (result.errorNum == 0) {
									document
											.getElementById("configurePhoneCodeTips").innerHTML = "短信验证码错误多次，请2小时后再试！";
								} else {
									document
											.getElementById("configurePhoneCodeTips").innerHTML = "短信验证码错误！您还有"
											+ result.errorNum + "次机会";
									document
											.getElementById("configurePhoneCode").value = "";
								}
							} else if (result.resultCode == 0) {
								closeChangeConfigure();
								var callback = {
									okBack : function() {
										window.location.href = document
												.getElementById("coinMainUrl").value
												+ "/user/security.html";
									},
									noBack : function() {
										window.location.href = document
												.getElementById("coinMainUrl").value
												+ "/user/security.html";
									}
								};
								okcoinAlert("修改成功！", null, callback);
							}
						}
					});
}

function openConfigure() {
	if (confirm("确定关闭交易密码交易时免输吗？")) {
		var url = "/user/openTradePwd.html?random="
				+ Math.round(Math.random() * 100);
		jQuery.post(url, null, function(data) {
			var result = eval('(' + data + ')');
			if (result != null) {
				if (result.resultCode == 0) {
					closeChangeConfigure();
					var callback = {
						okBack : function() {
							window.location.href = document
									.getElementById("coinMainUrl").value
									+ "/user/security.html";
						},
						noBack : function() {
							window.location.href = document
									.getElementById("coinMainUrl").value
									+ "/user/security.html";
						}
					};
					okcoinAlert("修改成功！", null, callback);
				}
			}
		});
	}
}

function validateIdentity() {
	var realName = document.getElementById("realName").value;
	var identityType = document.getElementById("identityType").value;
	var identityNo = document.getElementById("identityNo").value;
	// var filedata = document.getElementById("filedata").value;
	// var filedata2 = document.getElementById("filedata2").value;
	if (realName == "") {
		document.getElementById("errorMessage").innerHTML = "请填写姓名";
		return;
	} else {
		document.getElementById("errorMessage").innerHTML = "&nbsp;";
	}
	var realName2 = document.getElementById("realName2").value;
	if (realName != realName2) {
		document.getElementById("errorMessage").innerHTML = "两次输入的姓名不一致";
		return;
	} else {
		document.getElementById("errorMessage").innerHTML = "&nbsp;";
	}
	if (identityNo == "") {
		document.getElementById("errorMessage").innerHTML = "请填写证件号码";
		return;
	} else {
		document.getElementById("errorMessage").innerHTML = "&nbsp;";
	}
	if(!validateIdenNo(true)){
		return;
	}
	else 
	{
		document.getElementById("errorMessage").innerHTML = "&nbsp;";
	}

	if (identityType == 0) {
		var isIDCard = /(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/;
		var re = new RegExp(isIDCard);
		if (!re.test(identityNo)) {
			document.getElementById("errorMessage").innerHTML = "请填写正确身份证号码";
			return;
		}
	} else {
		if (identityNo.length <= 5) {
			document.getElementById("errorMessage").innerHTML = "证件号码不合法";
			return;
		}
	}
	
	// 远程验证身份证号码
//	if (!validateIdenNo() || validateIdenNo() == "false") {
//		return;
//	} else
//	/*
//	 * if(filedata.trim()==""){
//	 * document.getElementById("errorMessage").innerHTML = "必须上传证件正面扫描件";
//	 * return; }else if(!/\.(jpg|jpeg|png|PNG|JPG|JPEG)$/.test(filedata)){
//	 * document.getElementById("errorMessage").innerHTML =
//	 * "证件必须为jpg|jpeg|png|PNG|JPG|JPEG图片格式"; return; }else
//	 * if(filedata2.trim()==""){
//	 * document.getElementById("errorMessage").innerHTML = "必须上传证件反面扫描件";
//	 * return; }else if(!/\.(jpg|jpeg|png|PNG|JPG|JPEG)$/.test(filedata2)){
//	 * document.getElementById("errorMessage").innerHTML =
//	 * "证件必须为jpg|jpeg|png|PNG|JPG|JPEG图片格式"; return; }else
//	 */
//	{
//		document.getElementById("errorMessage").innerHTML = "&nbsp;";
//	}

	document.getElementById("validateIdentityForm").submit();
}

function checkRealName() {
	var realName = document.getElementById("realName").value;
	var realName2 = document.getElementById("realName2").value;
	if (realName != realName2) {
		document.getElementById("errorMessage").innerHTML = "两次输入的姓名不一致";
	}
}

function showApiApplyBlock() {
	dialogBoxShadow();
	document.getElementById("ApiApplyBlock").style.display = "";
}

function closeApiApplyBlock() {
	dialogBoxHidden();
	document.getElementById("ApiApplyBlock").style.display = "none";
	window.location.href = "/user/api.html";
}
function disabledApi(id) {
	if (confirm("确定禁用交易API吗？")) {
		var url = "/json/cancelApi.html?random="
				+ Math.round(Math.random() * 100);
		jQuery.post(url, {
			id : id
		}, function(data) {
			var result = data;
			if (result != null) {
				window.location.reload(true);
			}
		});
	}
}

// 身份证号码验证
var lastIdenNo = "";
var lastidenStatus = false;
function validateIdenNo(force) {
	var idenNo = document.getElementById("identityNo").value;
	if (lastIdenNo == idenNo&&!force) {
		return lastidenStatus;
	}
	lastIdenNo = idenNo;
	var idenType = document.getElementById("identityType").value;
	var nameStr=document.getElementById("realName").value;
	if(!nameStr||nameStr.length<=0){
		document.getElementById("errorMessage").innerHTML="请填写真实姓名";
		nameStr="";
	}
	var reqUrl = "/validate/identity.html?t=" + Math.round(Math.random() * 100);
	jQuery.ajax({
		type : 'post',
		url : reqUrl,
		async : false,
		dataType : 'json',
		data : {
			"idenType" : idenType,
			"idenNo" : idenNo,
			"name" : nameStr
		},
		success : function(data) {
			if (data.resultCode == "10000" || data.resultCode == 10000) {
				document.getElementById("errorMessage").innerHTML = "&nbsp;";
				lastidenStatus = true;
			} else {
				lastidenStatus = false;
				document.getElementById("errorMessage").innerHTML = data.resultMsg;
			}
		},
		error :function(jqXHR, textStatus, errorThrown) {
//            /*弹出jqXHR对象的信息*/
//            alert(jqXHR.responseText);
//            alert(jqXHR.status);
//            alert(jqXHR.readyState);
//            alert(jqXHR.statusText);
//            /*弹出其他两个参数的信息*/
//            alert(textStatus);
//            alert(errorThrown);
       
			lastidenStatus = false;
			document.getElementById("errorMessage").innerHTML = "发生未知错误";
		}
	});

	return lastidenStatus;
}

function submitApi() {
	var password = "";
	var label = "";
	password = trim(document.getElementById("password").value);
	label = trim(document.getElementById("label").value);
	if (label == '') {
		document.getElementById("originPwdTips").innerHTML = "请输入API别名";
		return;
	} else {
		document.getElementById("originPwdTips").innerHTML = "&nbsp;";
	}
	if (password == '') {
		document.getElementById("password").innerHTML = "请输入交易密码";
		return;
	} else {
		document.getElementById("password").innerHTML = "&nbsp;";
	}
	var url = "/json/addApi.html?random=" + Math.round(Math.random() * 100);
	var param = {
		label : label,
		password : password
	};
	jQuery.post(url, param, function(data) {
		var code = data.code;
		var message = data.message;

		var callback = {
			okBack : function() {
				window.location.reload();
			},
			noBack : function() {
			}
		};
		okcoinAlert(message, null, callback);
	}, "json");
}
var phonesecs=121;
function sendPhonCode(){
	var fphone=document.getElementById("fphone").value;
	var imgcode=document.getElementById("oldPhoneCode").value;
	if(!checkMobile(fphone)){
		document.getElementById("updatePhoneinfoTips").innerHTML="手机号码错误，请刷新重试！"
		return;
	}
	if(!/^.{4}$/.test(imgcode)){
		document.getElementById("updatePhoneinfoTips").innerHTML="验证码错误！"
		return;
	}
	var url = "/validate/sendPhoneMsg.html?random=" + Math.round(Math.random() * 100);
	document.getElementById("msgCodeBtn").disabled = true;
	for ( var num = 1; num <= phonesecs; num++) {
		window.setTimeout("updateNumberphoneRestPwd(" + num + ")", num * 1000);
	}
	var param = {phone : fphone,code : imgcode };
	jQuery.post(url,param,function(data) {
		if (data == 0) {
			closeimagecodeDiv();
			document.getElementById("msgCodeBtn").disabled = true;
			for ( var num = 1; num <= phonesecs; num++) {
				window.setTimeout("updateNumberphoneRestPwd("+ num + ")", num * 1000);
			}
		} else if (data == -1) {
			document.getElementById("updatePhoneinfoTips").innerHTML = "验证码错误！";
		} else if (data == -2) {
			document.getElementById("updatePhoneinfoTips").innerHTML = "手机号码错误，请刷新重试！";
		} else if (data == -3) {
			document.getElementById("updatePhoneinfoTips").innerHTML = "短信验证码发送频繁，请稍后再试！";
		} else if (data == -4) {
			document.getElementById("updatePhoneinfoTips").innerHTML = "请求超时";
		}
	});
}

function updateNumberphoneRestPwd(num) {
	if (num == secs) {
		document.getElementById("msgCodeBtn").disabled = false;
		document.getElementById("msgCodeBtn").value = "发送验证码";
	} else {
		var printnr = secs - num;
		document.getElementById("msgCodeBtn").value = printnr + "秒后可重发";
	}
}
var fowTime = 5;
function resetPassword() {
	var regu = /^[0-9]{6}$/;
	var re = new RegExp(regu);
	var validateCodeType = 0;
	var phoneCode = 0;
	var totpCode = 0;
	var fphone=document.getElementById("fphone").value;
	if(!checkMobile(fphone)){
		okcoinAlert("手机号码错误，请刷新重试！");
		return;
	}
	var newPassword = document.getElementById("newPassword").value;
	var newPassword2 = document.getElementById("newPassword2").value;
	var passwordType = document.getElementById("passwordType").value;
	var msg = isPassword(newPassword);
	if (msg != "") {
		document.getElementById("msg1").innerHTML = msg;
		return;
	} else {
		document.getElementById("msg1").innerHTML = "";
	}
	if (newPassword != newPassword2) {
		document.getElementById("msg2").innerHTML = "两次密码输入不一致";
		document.getElementById("newPassword2").value = "";
		return;
	} else {
		document.getElementById("msg2").innerHTML = "";
	}
	if (document.getElementById("phoneCode") != null) {
		phoneCode = trim(document.getElementById("phoneCode").value);
		if (!re.test(phoneCode)) {
			document.getElementById("phoneCodeTips").style.display = "";
			document.getElementById("phoneCodeTips").innerHTML = "短信验证码输入不合法";
			return;
		} else {
			document.getElementById("phoneCodeTips").innerHTML = "&nbsp;";
			document.getElementById("phoneCodeTips").style.display = "none";
		}
	}
	if (document.getElementById("totpCode") != null) {
		totpCode = trim(document.getElementById("totpCode").value);
		if (!re.test(totpCode)) {
			document.getElementById("totpCodeTips").innerHTML = "谷歌验证码输入不合法";
			return;
		} else {
			document.getElementById("totpCodeTips").innerHTML = "&nbsp;";
		}
	}

	var url = "/validate/resetPhonePassword.html?random="+ Math.round(Math.random() * 100);
	var param = {
		phone:fphone,
		totpCode : totpCode,
		phoneCode : phoneCode,
		newPassword : newPassword,
		newPassword2 : newPassword2
	};
	jQuery.post(url,param,function(data) {
		var result = eval('(' + data + ')');
		if (result.resultCode == -1) {
			okcoinAlert("请求超时");
		} else if (result.resultCode == -2) {
			document.getElementById("msg1").innerHTML = "密码格式不合法";
		} else if (result.resultCode == -22) {
			document.getElementById("msg1").innerHTML = "密码必须以字母开头";
		} else if (result.resultCode == -3) {
			document.getElementById("msg2").innerHTML = "两次密码输入不一致";
			document.getElementById("newPassword2").value = "";
		} else if (result.resultCode == -4) {
			document.getElementById("msg1").innerHTML = "登录密码不允许与交易密码一致";
			document.getElementById("newPassword").value = "";
			document.getElementById("newPassword2").value = "";
		} else if (result.resultCode == -5) {
			okcoinAlert("用户未设置安全验证，不允许修改密码。");
		} else if (result.resultCode == -6) {
			okcoinAlert("手机号码不存在，请确认您的账号！");
		} else if (result.resultCode == -8) {
			if (result.errorNum == 0) {
				document.getElementById("totpCodeTips").innerHTML = "谷歌验证码错误多次，请2小时后再试！";
			} else {
				document.getElementById("totpCodeTips").innerHTML = "谷歌验证码错误！您还有"
						+ result.errorNum + "次机会";
				document.getElementById("totpCode").value = "";
			}
		} else if (result.resultCode == -9) {
			if (result.errorNum == 0) {
				document.getElementById("phoneCodeTips").style.display = "";
				document.getElementById("phoneCodeTips").innerHTML = "短信验证码错误多次，请2小时后再试！";
			} else {
				document.getElementById("phoneCodeTips").style.display = "";
				document.getElementById("phoneCodeTips").innerHTML = "短信验证码错误！您还有"
						+ result.errorNum + "次机会";
				document.getElementById("phoneCode").value = "";
			}
		} else if (result.resultCode == 0) {
			window.location.href = "/validate/reset.html?type=2";
		}
	});
}

function showimagecodeDiv(){
	document.getElementById("imagecodeDiv").style.display = "";
	dialogBoxShadow(false);
	addMoveEvent("updatePhone_title_auth", "updatePhone_content_auth");
}
function closeimagecodeDiv(){
	dialogBoxHidden();
	document.getElementById("imagecodeDiv").style.display="none";
}
