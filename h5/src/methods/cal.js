var countDecimals = function(num) {
  var len = 0;
  try {
    num = Number(num);
    var str = num.toString().toUpperCase();
    if (str.split("E").length === 2) {
      // scientific notation
      var isDecimal = false;
      if (str.split(".").length === 2) {
        str = str.split(".")[1];
        if (parseInt(str.split("E")[0]) !== 0) {
          isDecimal = true;
        }
      }
      let x = str.split("E");
      if (isDecimal) {
        len = x[0].length;
      }
      len -= parseInt(x[1]);
    } else if (str.split(".").length === 2) {
      // decimal
      if (parseInt(str.split(".")[1]) !== 0) {
        len = str.split(".")[1].length;
      }
    }
  } catch (e) {
     e;
  } finally {
    if (isNaN(len) || len < 0) {
      len = 0;
    }
   
  }
  return len;
};

var convertToInt = function(num) {
  num = Number(num);
  var newNum = num;
  var times = countDecimals(num);
  var temp_num = num.toString().toUpperCase();
  if (temp_num.split("E").length === 2) {
    newNum = Math.round(num * Math.pow(10, times));
  } else {
    newNum = Number(temp_num.replace(".", ""));
  }
  return newNum;
};

var getCorrectResult = function(type, num1, num2, result) {
  var temp_result = 0;
  switch (type) {
    case "add":
      temp_result = num1 + num2;
      break;
    case "sub":
      temp_result = num1 - num2;
      break;
    case "div":
      temp_result = num1 / num2;
      break;
    case "mul":
      temp_result = num1 * num2;
      break;
  }
  if (Math.abs(result - temp_result) > 1) {
    return temp_result;
  }
  return result;
};
export default {
  //加法
  accAdd(num1, num2) {
    num1 = Number(num1);
    num2 = Number(num2);
    var dec1, dec2, times;
    try {
      dec1 = countDecimals(num1) + 1;
    } catch (e) {
      dec1 = 0;
    }
    try {
      dec2 = countDecimals(num2) + 1;
    } catch (e) {
      dec2 = 0;
    }
    times = Math.pow(10, Math.max(dec1, dec2));
    // var result = (num1 * times + num2 * times) / times;
    var result = (this.accMul(num1, times) + this.accMul(num2, times)) / times;
    return getCorrectResult("add", num1, num2, result);
    // return result;
  },
  //减法
  accSub(num1, num2) {
    num1 = Number(num1);
    num2 = Number(num2);
    var dec1, dec2, times;
    try {
      dec1 = countDecimals(num1) + 1;
    } catch (e) {
      dec1 = 0;
    }
    try {
      dec2 = countDecimals(num2) + 1;
    } catch (e) {
      dec2 = 0;
    }
    times = Math.pow(10, Math.max(dec1, dec2));
    // var result = Number(((num1 * times - num2 * times) / times);
    var result = Number(
      (this.accMul(num1, times) - this.accMul(num2, times)) / times
    );
    return getCorrectResult("sub", num1, num2, result);
    // return result;
  },
  //除法
  accDiv(num1, num2) {
    num1 = Number(num1);
    num2 = Number(num2);
    var t1 = 0,
      t2 = 0,
      dec1,
      dec2;
    try {
      t1 = countDecimals(num1);
    } catch (e) {e;}
    try {
      t2 = countDecimals(num2);
    } catch (e) {e;}
    dec1 = convertToInt(num1);
    dec2 = convertToInt(num2);
    var result = this.accMul(dec1 / dec2, Math.pow(10, t2 - t1));
    return getCorrectResult("div", num1, num2, result);
    // return result;
  },
  //乘法
  accMul(num1, num2) {
    num1 = Number(num1);
    num2 = Number(num2);
    var times = 0,
      s1 = num1.toString(),
      s2 = num2.toString();
    try {
      times += countDecimals(s1);
    } catch (e) {
      e;
    }
    try {
      times += countDecimals(s2);
    } catch (e) {e;}
    var result = (convertToInt(s1) * convertToInt(s2)) / Math.pow(10, times);
    return getCorrectResult("mul", num1, num2, result);
    // return result;
  },
  clearNoNum6(input_value) {
    input_value = input_value.replace(/[^\d.]/g, "");  //清除“数字”和“.”以外的字符  
    input_value = input_value.replace(/\.{4,}/g, "."); //只保留第一个. 清除多余的  
    input_value = input_value.replace(".", "$#$").replace(/\./g, "").replace("$#$", ".");
    input_value = input_value.replace(/^()*(\d+)\.(\d\d\d\d\d\d).*$/, '$1$2.$3');//只能输入2位小数  
    if (input_value.indexOf(".") < 0 && input_value != "") {
      input_value = parseFloat(input_value);
    }
    return input_value
  },
  clearNoNum(input_value) {
    input_value = input_value.replace(/[^\d]/g, "");  //清除“数字”和“.”以外的字符  
    input_value = input_value.replace(/\.{4,}/g, "."); //只保留第一个. 清除多余的  
    input_value = input_value.replace(".", "$#$").replace(/\./g, "").replace("$#$", ".");
    input_value = input_value.replace(/^()*(\d+)\.().*$/, '$1$2.$3');//只能输入2位小数  
    if (input_value.indexOf(".") < 0 && input_value != "") {
      input_value = parseFloat(input_value);
    }
    return input_value
  },
  clearNoNum2(input_value) {
    input_value = input_value.replace(/[^\d.]/g,""); //清除"数字"和"."以外的字符
    input_value = input_value.replace(/^\./g,""); //验证第一个字符是数字
    input_value = input_value.replace(/\.{2,}/g,"."); //只保留第一个, 清除多余的
    input_value = input_value.replace(".","$#$").replace(/\./g,"").replace("$#$",".");
    input_value = input_value.replace(/^(\-)*(\d+)\.(\d\d).*$/,'$1$2.$3'); //只能输入两个小数
    if (input_value.indexOf(".") < 0 && input_value != "") {
      input_value = parseFloat(input_value);
    }
    return input_value
  }
  
};
