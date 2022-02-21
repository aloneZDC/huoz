
// 只能输入数字，且保留6位小数
export function clearNum6(input_value) {
  input_value = input_value.replace(/[^\d.]/g, "");  //清除“数字”和“.”以外的字符  
  input_value = input_value.replace(/\.{4,}/g, "."); //只保留第一个. 清除多余的  
  input_value = input_value.replace(".", "$#$").replace(/\./g, "").replace("$#$", ".");
  input_value = input_value.replace(/^(\-)*(\d+)\.(\d\d\d\d\d\d).*$/, '$1$2.$3');//只能输入2位小数  
  if (input_value.indexOf(".") < 0 && input_value != "") {
    input_value = parseFloat(input_value);
  }
  return input_value
}
// 只能输入数字，
export function clearNum(input_value) {
  input_value = input_value.replace(/[^\d]/g, "");  //清除“数字”和“.”以外的字符  
  input_value = input_value.replace(".", "$#$").replace(/\./g, "").replace("$#$", ".");
  if (input_value.indexOf(".") < 0 && input_value != "") {
    input_value = parseFloat(input_value);
  }
  return input_value
}
