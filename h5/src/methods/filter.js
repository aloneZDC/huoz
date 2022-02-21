let dateServer = value => {
  return value.replace(/(\d{4})(\d{2})(\d{2})/g, "$1-$2-$3");
};
let dateServer1 = value => {
  return value + "123";
};
// '****'替代手机号码中间几位
let starsInstead = tel => {
  tel = String(tel);
  return tel.substr(0, 3) + "****" + tel.substr(7);
};
//保留4位小数
let substring4 = value => {
  value = String(value);
  value = value.substring(0, value.indexOf(".") + 5); //截取小數後四位
  return value;
};
//保留2位小数
let substring2 = value => {
  value = String(value);
  value = value.substring(0, value.indexOf(".") + 3); //截取小數後四位
  return value;
};
//保留6位小数
let substring6 = value => {
  value = String(value);
  value = value.substring(0, value.indexOf(".") + 7); //截取小數後六位
  return value;
};
let nFormatter = value => {
  value = String(value);
  if (value - 1000000000 > 0) {
    value = String(parseInt(value));
    value = value.substring(0, value.length - 3) + " k";
    return value;
  }
  if (value - 100000000 > 0 && value.indexOf(".") > 0) {
    value = (value - 0).toFixed(1);
    return value;
  }
  if (value - 10000000 > 0 && value.indexOf(".") > 0) {
    value = (value - 0).toFixed(2);
    return value;
  }
  if (value - 1000000 > 0 && value.indexOf(".") > 0) {
    value = (value - 0).toFixed(3);
    return value;
  }
  if (value - 100000 > 0 && value.indexOf(".") > 0) {
    value = (value - 0).toFixed(4);
    return value;
  }

  return substring4(value);
};

export {
  dateServer,
  dateServer1,
  starsInstead,
  substring2,
  substring4,
  substring6,
  nFormatter
};
// import * as custom from '@/utils/filters.js'
// Object.keys(custom).forEach(key => {
//   Vue.filter(key, custom[key])
// })