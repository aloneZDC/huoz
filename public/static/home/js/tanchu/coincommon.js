
function boxFloat(obj,elem){
	var nmove,mmove,
		d = document,
		o = d.getElementById(obj),
		s = d.getElementById(elem);
	if(!o){ return false;}
	if(!s){ return false;}
	
	s.onmouseover=function(){
		clearTimeout(nmove);
		s.style.display="block";
		s.style.cursor="pointer";
	};
	o.onmouseover=function(){
		clearTimeout(nmove);
		mmove=setTimeout(function(){
			
			s.style.display="block";
			if(obj.indexOf("ordersStatus_") != -1){
				var id = obj.substring(obj.indexOf("_")+1,obj.length);
				 jQuery("#detailOrdersStatus_"+id).load("/orders/status.html?id="+id,function (data){
				});
			}
			if(obj=="orderStatusIndex"){
				var id = document.getElementById("orderStatusId").value;
				indexOrdersStatus(id);
			}
			
		},100);
		
	};
	o.onmouseout=function(){
		clearTimeout(mmove);
		nmove=setTimeout(function(){s.style.display="none";},500);
	};
	s.onmouseout=function(){
		nmove=setTimeout(function(){s.style.display="none";},500);
	};
	s.onmousedown=function(e){
		stopBubble(e);
	};
}
boxFloat("accountlink","accountpop");
boxFloat("personalNetAssetsExplain","personalNetAssetsExplainBlock");

function ShowMemo(obj,id)
{
	$("Memo"+id).style.display = "";
}

function HideMemo(id)
{
	$("Memo"+id).style.display = "none";
}

function dialogBoxHidden(){
	var d=document,
  o=d.getElementById("dialogBoxShadow");
 if(!o) return false;
	d.body.removeChild(o);	
}

function dialogBoxShadow(f){ 
	dialogBoxShadowMove(f,true);
}

function dialogBoxShadowMove(f,canmove){
	 var d = document,
	  divs=d.createElement("div"),
	  doc = d[d.compatMode == "CSS1Compat"?'documentElement':'body'],
	  h = f?doc.clientHeight:Math.max(doc.clientHeight,doc.scrollHeight);
	 divs.setAttribute("id","dialogBoxShadow");
	 d.body.appendChild(divs);
	 var o = d.getElementById('dialogBoxShadow');
	 o.style.cssText +="	;position:absolute;top:0;left:0;z-index:100;background:#000;opacity:0.4;filter:Alpha(opacity=20);width:100%;height:"+h+"px";
	 if(canmove) addMoveEvent("dialog_title","dialog_content");
}

function addMoveEvent(titleobj,contentobj){
	 var titleobj = document.getElementById(titleobj);
	 var contentobj=document.getElementById(contentobj);
	 if(titleobj!=null&&contentobj!=null){
		var bDrag = false;
		var disX = disY = 0;
		titleobj.onmousedown = function (event)
		{		
			var event = event || window.event;
			bDrag = true;
			disX = event.clientX - contentobj.offsetLeft;
			disY = event.clientY - contentobj.offsetTop;	
			this.setCapture && this.setCapture();	
			return false;
		};
		document.onmousemove = function (event)
		{
			if (!bDrag) return;
			var event = event || window.event;
			var iL = event.clientX - disX;
			var iT = event.clientY - disY;
			var maxL = document.documentElement.clientWidth - contentobj.offsetWidth;
			var maxT = document.documentElement.clientHeight - contentobj.offsetHeight;		
			iL = iL < 0 ? 0 : iL;
			iL = iL > maxL ? maxL : iL; 		
			iT = iT < 0 ? 0 : iT;
			iT = iT > maxT ? maxT : iT;
			
			contentobj.style.marginTop = contentobj.style.marginLeft = 0;
			contentobj.style.left = iL + "px";
			contentobj.style.top = iT + "px";		
			return false;
		};
		document.onmouseup = window.onblur = titleobj.onlosecapture = function ()
		{
			bDrag = false;				
			titleobj.releaseCapture && titleobj.releaseCapture();
		};
	 }

}

// -----------------弹出层定位--------------------//
function skillsPosition(obj,x){
var o=$(obj),h,oh,w,oc;
if(!o) return false;
o.style.display="block";
h=parseInt(getStyle(o,"height"));
w=parseInt(getStyle(o,"width"));
oh=";display:block;top:50%;margin-top:"+(-h/2)+"px";
o.style.cssText=!x?oh:(oh+";left:50%;margin-left:"+(-w/2)+"px");
}


/* 弹出层绝对居中定位 */
function setObjCenter(id){
	var d=document;
	var obj = d.getElementById(id);
	var data={
		ow:obj.clientWidth,
		oh:obj.clientHeight,
		vw:(function(){
		if (d.compatMode == "BackCompat"){
			return d.body.clientWidth;
		} else {
			return d.documentElement.clientWidth;
		}				
		})(),
		vh:(function(){
		if (d.compatMode == "BackCompat"){
			return d.body.clientHeight;
		} else {
			return d.documentElement.clientHeight;
		}			
		})(),
		st:(d.body.scrollTop||d.documentElement.scrollTop)
	};
	// obj.style.display="block";
	obj.style.left=(data.vw-data.ow)/2+"px";
	obj.style.margin=0;			
	if(!!window.XMLHttpRequest){
		obj.style.position="fixed";
		obj.style.top=(data.vh-data.oh)/2+"px";
	}else{
		obj.style.position="absolute";
		obj.style.top=(data.vh-data.oh)/2+data.st+"px";		
		if(obj.style.backgroundAttachment)
			obj.style.backgroundAttachment="absolute !important";		
		window.onscroll=function(){obj.style.top=(d.body.scrollTop||d.documentElement.scrollTop)+(data.vh-data.oh)/2+'px';};
	}						
}
// id 0:登录层 1:注册层
function showlogin(id){
	document.getElementById("okcoinPop").style.display="block";
	
	jQuery("#okcoinPop").load("/Home/Reg/user2/type="+id,function (data){
		dialogBoxShadow();
		showDialog(id);
	});
}
function closelogin(){
	dialogBoxHidden();
	document.getElementById("okcoinPop").style.display="none";
	document.getElementById("okcoinPop").innerHTML="";
	 
}



function showDialog(id){
	if(id == 0){
		// document.getElementById("loginDialog").style.display="block";
		document.getElementById("regDialog").style.display="none";
		document.getElementById("regLi").className="";
		document.getElementById("loginLi").className="cur";
		// document.getElementById("dialog_content").style.display="block";
		
		//document.getElementById("loginUserName").focus();
		//callbackEnter(loginSubmit);
	}else {
		document.getElementById("MasklayerBlock").style.display="block";
		document.getElementById("regDialog").style.display="block";
		document.getElementById("loginDialog").style.display="none";
		document.getElementById("regLi").className="cur";
		document.getElementById("dialog_content").style.display="none";



		document.getElementById("loginLi").className="";
		//callbackEnter(regSubmit);
		useRegType(0);
	}
}
function loginSubmit(){
	var space="&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
	if(checkLoginUserName()  && checkLoginPassword()){
		var url = "/user/login/index.html?random="+Math.round(Math.random()*100);
		var uName = document.getElementById("loginUserName").value;
		var pWord = document.getElementById("loginPassword").value;
		var longLogin = 0;
		if(checkEmail(uName)){
			longLogin = 1;
		}
		var forwardUrl = "" ;
		if(document.getElementById("forwardUrl")!=null){
			forwardUrl = document.getElementById("forwardUrl").value ;
		}
		var param={loginName:uName,password:pWord,type:longLogin};
		jQuery.post(url,param,function(result){
				if(result!=null){
					var desc=""
					if(result.resultCode == -1){
						desc="用户名或密码错误";
					}else if(result.resultCode == -2){
						desc="此ip登录频繁，请2小时后再试";
					}else if(result.resultCode == -3){
						if(result.errorNum == 0){
							desc="此ip登录频繁，请2小时后再试";
						}else{
							desc="用户名或密码错误，您还有"+result.errorNum+"次机会";
						}
						document.getElementById("loginPassword").value="";
					}else if(result.resultCode == -4){
						desc="您的浏览器还未开启COOKIE,请设置启用COOKIE功能";
					}else if(result.resultCode == 1){
						if(forwardUrl.trim()==""){
							window.location.href = document.getElementById("coinMainUrl").value;
						}else{
							window.location.href = forwardUrl;
						}
					}else if(result.resultCode == 2){
						desc="账户出现安全隐患被冻结，请尽快联系客服。";
					}else if(result.resultCode == -404){
						desc="系统升级中，暂停登录";
					}
					if(desc!=""){
						document.getElementById("loginTips").innerHTML=space+desc;
					}
				}
		},"json");	
	}
}
/**
 * 是否登录完成后跳转页面
 */
function isForward(){
	if(document.getElementById("forwardUrl")!=null){
		var forward = document.getElementById("forwardUrl").value;
		if(forward != ""){
			showlogin(0);
		}
	}
	
}
function loginNameOnblur(){
	var space="&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
	var uName = document.getElementById("loginUserName").value;
	if(! checkEmail(uName) && !checkMobile(uName)){
		document.getElementById("loginTips").innerHTML=space+"邮箱或手机号格式不正确";
	}else{
		document.getElementById("loginTips").innerHTML="";
	}
}
function checkLoginUserName(){
	var space="&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
	var uName = document.getElementById("loginUserName").value;
	if(uName == ""){
		document.getElementById("loginTips").innerHTML=space+"邮箱或手机号不能为空";
		return false;
	}else if(! checkEmail(uName) && !checkMobile(uName)){
		document.getElementById("loginTips").innerHTML=space+"邮箱或手机号格式不正确";
		return false;
	}
	document.getElementById("loginTips").innerHTML="";
	return true;
}
function checkLoginPassword(){
	var space="&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
	var password = document.getElementById("loginPassword").value;
	if(password == ""){
		document.getElementById("loginTips").innerHTML=space+"密码不能为空";
		return false;
	}else if(password.length <6){
		document.getElementById("loginTips").innerHTML=space+"密码长度不能小于6！";
		return false;
	}
	document.getElementById("loginTips").innerHTML="";
	return true;
}
function termsService(){
	if(!document.getElementById("agree").checked){
		document.getElementById("regBtn").disabled=true;
		document.getElementById("regBtn").className="falsebutton buttonfalse";
	}else{
		document.getElementById("regBtn").disabled=false;
		document.getElementById("regBtn").className="button-dialog";
	}
}

function useRegType2(){
	document.getElementById("regDialog").style.display="block";
	document.getElementById("loginDialog").style.display="none";
	
}


function useRegType(id){
	document.getElementById("regDialog").style.display="block";
	document.getElementById("loginDialog").style.display="none";
	//callbackEnter(regSubmit);
	// if(id == 0){
	// 	//document.getElementById("emialtips").style.display="none";
	// 	//document.getElementById("phonecode").style.display="block";
	//
	// 	document.getElementById("regUserName").focus();
	// }else{
	// 	//document.getElementById("emialtips").style.display="block";
	// 	//document.getElementById("phonecode").style.display="none";
	//
	// 	document.getElementById("regUserName").focus();
	// }
}
