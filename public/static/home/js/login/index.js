$(function() {
	vali = {
		user: 0,
		pwd: 0
	};

	function validateMsg(id, msg) {
		$('#' + id + 'msg').html('<font style="color:red">' + msg + '</font>');
	}

	$('#user').bind('blur', function() {
		$.post(checkUrl, {
				username: $(this).val()
			},
			function(d) {
				if (d.code != 10000) {
					$("#birthcheck").show();
					$('#birthmsg').html(d.message);
				} else {
					return validateMsg('user', '');
				}
			}, 'json');
	});

	$('#pass').bind('blur', function() {
		var pLen = $(this).val().length;
		if (pLen < 8 || pLen > 20) {
			return validateMsg('pass', pwdReminder);
		}
		return validateMsg('pass', '');
	});

	$("#authSure").on("click",function(){
		if($("#auth").val()==""){
			layer.msg(emptyAuth);
			return;
		}
		var obj = {
			username: $('#user').val(),
			password: $('#pass').val(),
			phone_code: $("#auth").val(),
		}
		$.ajax({
			type:"POST",
			url:authUrl,
			data:obj,
			success:function(data){
				if(data.code == 10000){
					layer.msg(data['message']);
					window.location.href = document.referrer;
				}else{
					layer.msg(data['message']);
				}
			},
			error:function(error){
				console.log(error)
			}
		})	
	});

	var authBool = false;

	function submitFrom() {
		var obj = {
			username: $('#user').val(),
			password: $('#pass').val(),
			validate: $('#img_code').val(),
		};
		$.post(loginUrl, obj, function(data) {
			if (data.code == 10000) {
				layer.msg(data['message']);
				window.location.href = document.referrer;
			}else if(data.code == 11000){
				authBool = true;
				$(".choose").css("display","none");
				$("#userBox").css("display","none");
				$("#authBox").css("display","block");
				$("#username").html($('#user').val());
			} else {
				layer.msg(data['message']);
			}
		})
	}


	$("#sure").on("click", function() {
        submitFrom();
	});

	$(document).on("keyup", function(e) {
		// 兼容FF和IE和Opera
		var event = e || window.event;
		var key = event.which || event.keyCode || event.charCode;
		if (key == 13) {
			if(!authBool){
				captchaIns.popUp();
				captchaIns.refresh();
			}else{
				$("#authSure").trigger('click');
			}
		}
	});
});