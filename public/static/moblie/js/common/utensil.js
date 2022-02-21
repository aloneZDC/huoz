	/*
	 * by wangqq 2018/12/12
	 * 修改html的font-size,使rem生效
	*/
	function htmlFontSize(){
		var windowW = document.body.clientWidth;// 750 24
		var htmlPx = windowW / 750 * 24;
		document.getElementsByTagName('html')[0].style.fontSize = htmlPx + 'px';
	}

	/*
	 * by wangqq 2018/12/12
	 * 获取cookie
	*/
	function getCookie(name){
		var strcookie=document.cookie;
		var arrcookie=strcookie.split("; ");
		for(var i=0;i<arrcookie.length;i++){
		var arr=arrcookie[i].split("=");
		if(arr[0]==name)return unescape(arr[1]);
		}
		return null;
	}

  //获取cookie
function getCookie(name){
    var strcookie=document.cookie;
    var arrcookie=strcookie.split("; ");
    for(var i=0;i<arrcookie.length;i++){
    var arr=arrcookie[i].split("=");
    if(arr[0]==name)return unescape(arr[1]);
    }
    return null;
}
//删除cookie
function delCookie(name) {
    var exp = new Date();
    exp.setTime(exp.getTime() - 1);
    var cval = getCookie(name);
    if (cval != null) document.cookie = name + "=" + cval + "; path=/;expires=" + exp.toGMTString();
}
  
function addCookie(name, value, expireHours) {
    var cookieString = name + "=" + escape(value) + "; path=/";
    //判断是否设置过期时间
    if (expireHours > 0) {
      var date = new Date();
      date.setTime(date.getTime() + expireHours * 3600 * 1000);
      cookieString = cookieString + ";expires=" + date.toGMTString();
    }
    document.cookie = cookieString;
  }
function clearNoNum(obj) {
    obj.value = obj.value.replace(/[^\d.]/g, "");  //清除“数字”和“.”以外的字符
    obj.value = obj.value.replace(/\.{4,}/g, "."); //只保留第一个. 清除多余的
    obj.value = obj.value.replace(".", "$#$").replace(/\./g, "").replace("$#$", ".");
    obj.value = obj.value.replace(/^(\-)*(\d+)\.(\d\d\d\d).*$/, '$1$2.$3');//只能输入两个小数
    if (obj.value.indexOf(".") < 0 && obj.value != "") {//以上已经过滤，此处控制的是如果没有小数点，首位不能为类似于 01、02的金额
      obj.value = parseFloat(obj.value);
    }
  }
  
 /**
 * 两个数相加
 * @param arg1
 * @param arg2
 * @returns {number} 
 */
 function add(num1, num2) {
    var r1, r2, m, n;
    try {
      r1 = num1.toString().split(".")[1].length
    } catch (e) {
      r1 = 0
    }
    try {
      r2 = num2.toString().split(".")[1].length
    } catch (e) {
      r2 = 0
    }
    m = Math.pow(10, Math.max(r1, r2));
    n = (r1 >= r2) ? r1 : r2;
    return ((num1 * m + num2 * m) / m).toFixed(n);
  }
 /**
 * 两个数相减
 * @param arg1
 * @param arg2
 * @returns {number} 
 */
 function sub(num1, num2) {
    var r1, r2, m, n;
    try {
      r1 = num1.toString().split(".")[1].length
    } catch (e) {
      r1 = 0
    }
    try {
      r2 = num2.toString().split(".")[1].length
    } catch (e) {
      r2 = 0
    }
    n = (r1 >= r2) ? r1 : r2;
    m = Math.pow(10, Math.max(r1, r2));
    return ((num1 * m - num2 * m) / m).toFixed(n);
  }
  
 /**
 * 两个数相除
 * @param arg1
 * @param arg2
 * @returns {number} 
 */
 function div(arg1, arg2) {
    var t1 = 0, t2 = 0, r1, r2;
    try {
      t1 = arg1.toString().split(".")[1].length
    } catch (e) {
    }
    try {
      t2 = arg2.toString().split(".")[1].length
    } catch (e) {
    }
    r1 = Number(arg1.toString().replace(".", ""));
    r2 = Number(arg2.toString().replace(".", ""));
    return (r1 / r2) * Math.pow(10, t2 - t1);
  }
  
 /**
 * 两个数相乘
 * @param arg1
 * @param arg2
 * @returns {number} 
 */
function accMul(arg1,arg2){
    var m=0,s1=arg1.toString(),s2=arg2.toString();
    try{m+=s1.split(".")[1].length}catch(e){}
    try{m+=s2.split(".")[1].length}catch(e){}
    return Number(s1.replace(".",""))*Number(s2.replace(".",""))/Math.pow(10,m)
}
/*
 *
 * toast弹窗
 * */
 function msgToast(msg){
	var bodyroot=document.body;
		var newnode =$("<div class='com_tsk'></div>");
		newnode.html(msg);
		newnode.appendTo(bodyroot).fadeIn().fadeOut(2000,function(){
    $(".com_tsk").remove();
	})
}
/*
 * 从URL中获取参数
 * 
 * */
function getURLParam(param){
	var str =location.search.substring(1);
	var arr =str.split("&");
	for(var i=0;i<arr.length;i++){
		var newArr= arr[i].split("=");
		for(var j=0;j<newArr.length;j++){
			if(newArr[0]==param){
				return newArr[1]
			}
		}
	}
	return str
}

/*
 * 阻止事件冒泡 以及默认行为
 * 
 * */
	function stopPropagations(e){
		if ( e && e.stopPropagation ){
			e.stopPropagation(); 
		}else{
			window.event.cancelBubble = true; 
		}
		if(e.preventDefault){
			e.preventDefault();
		}else{
			window.event.returnValue == false;
    }	
  }
  // 只能输入数字，且保留2位小数
  function clearNoNum1(obj){ 
    obj.value = obj.value.replace(/[^\d.]/g,"");  //清除“数字”和“.”以外的字符  
    obj.value = obj.value.replace(/\.{4,}/g,"."); //只保留第一个. 清除多余的  
    obj.value = obj.value.replace(".","$#$").replace(/\./g,"").replace("$#$","."); 
    obj.value = obj.value.replace(/^(\-)*(\d+)\.(\d\d).*$/,'$1$2.$3');//只能输入2位小数  
    if(obj.value.indexOf(".")< 0 && obj.value !=""){
        obj.value= parseFloat(obj.value); 
    } 
};

  // 只能输入数字，且保留6位小数
  function clearNoNum(obj){ 
      obj.value = obj.value.replace(/[^\d.]/g,"");  //清除“数字”和“.”以外的字符  
      obj.value = obj.value.replace(/\.{4,}/g,"."); //只保留第一个. 清除多余的  
      obj.value = obj.value.replace(".","$#$").replace(/\./g,"").replace("$#$","."); 
      obj.value = obj.value.replace(/^(\-)*(\d+)\.(\d\d\d\d\d\d).*$/,'$1$2.$3');//只能输入6位小数  
      if(obj.value.indexOf(".")< 0 && obj.value !=""){
          obj.value= parseFloat(obj.value); 
      } 
  };
  /**
   * 复制input框的值
   * @param {id} 
   */
  function copytext(id){
    document.getElementById(id).select(); // 选择对象
    document.execCommand("Copy"); // 执行浏览器复制命令
}

function loading(){
  layer.load(1, {
      shade: [0.3, '#000'] //0.1透明度的白色背景
  });
}

