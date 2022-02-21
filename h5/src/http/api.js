const api = {
  buy_list:"/h5/Rocket/buy_list",//推进记录
  my_advance:"/h5/Rocket/buy_detail",//我的推进
  buy_info:"/h5/Rocket/community_info",//推进社区
  my_info:"/h5/Rocket/my_info",//我的辅导
  help_log:"/h5/Rocket/help_log",//助力燃料
  power_log:"/h5/Rocket/power_log",//动力燃料
  buy_detail:"/h5/Rocket/buy_detail",//动力燃料
  Index:"/h5/Rocket/index",//火箭首页
  getList:"/h5/Rocket/get_list",//闯关列表
  get_pay_info:"/h5/Rocket/get_pay_info",//支付信息
  Pay:"/h5/Rocket/pay",//支付
  user_info:"/h5/Rocket/user_info",//获取币种信息
  transfer:"/h5/Votes/transfer",//互转
  kmt_log:"/h5/Rocket/kmt_log",//燃料记录
  rocket_index:"/h5/Rocket/rocket_index",//应用仓列表
  rocket_log:"/h5/Rocket/rocket_log",//应用仓记录
  fire_info:"/h5/Rocket/fire_info",//自动点火信息
  set_fire:"/h5/Rocket/set_fire",//自动点火
  order_index:"/h5/Rocket/order_index",//订单记录（首页）
  order_list:"/h5/Rocket/order_list",//订单记录（参与人数列表）
  transfer_info:"/h5/Rocket/transfer_info",//划转账户
  add_transfer:"/h5/Rocket/add_transfer",//提交划转
  transfer_log:"/h5/Rocket/transfer_log",//划转记录
  subscribe_log:"/h5/Rocket/subscribe_log",//预约记录
  subscribe:"/h5/Rocket/subscribe",//预约闯关
  get_user_info:"/h5/Wallet/get_user_info",//获取预约池信息
  add_subscribe_transfer:"/h5/Wallet/add_subscribe_transfer",//提交充值
  subscribe_transfer:"/h5/Wallet/subscribe_transfer",//提交充值
  balance_detail:"/h5/Wallet/balance_detail",//余额明细
  queue_log:"/h5/Wallet/queue_log",//排队记录
  sub_cancel:"/h5/Rocket/sub_cancel",//取消自动
  y_subscribe_info:"/h5/Rocket/subscribe_info",//预约奖励信息
  y_subscribe_help_log:"/h5/Rocket/subscribe_help_log",//预约助力燃料记录
  y_subscribe_power_log:"/h5/Rocket/subscribe_power_log",//预约动力燃料记录
  y_centre_log:"/h5/Rocket/centre_log",//预约服务津贴记录
  statistics_info:"/h5/Rocket/statistics_info",//数据统计明细
  get_category:"/h5/Shop/get_category",//其他专区接口
  
  

  // 合同签名
  order_contract:"/h5/CommonMining/order_contract",//合同详情
  submit_auto:"/h5/CommonMining/submit_autograph",//合同签名
  // 商城
  get_shop_index: "/h5/shop/index",//获取首页信息 
  get_goods_list: "/h5/shop/get_goods_list",//获取商品列表
  get_goods_details: "/h5/shop/get_goods_details",//获取商品详情
  shop_index: "/h5/shop/index",//商城首页
  get_shop_cart: "/h5/shop/get_shop_cart",//获取用户的购物车数据
  pay_type: "/h5/Shop/pay_type", //支付方式
  add_shop_cart: "/h5/shop/add_shop_cart", //添加购物车
  delete_shop_cart: '/h5/shop/delete_shop_cart', //删除一条或者多条购物车数据
  update_shop_cart: '/h5/shop/update_shop_cart', //修改购物车数量
  shop_new_list: "/h5/Shop/new_list",//公告列表
  get_default: "/h5/shop/get_default",//获取一条默认收货地址
  get_address_list: "/h5/shop/get_address_list",//获取用户的收货地址列表
  areas: '/h5/Areas/index',  // 省市区三级联动
  get_orders_list:"/h5/shop/get_orders_list",//订单管理
  get_orders_detail:"/h5/Shop/orders_detail",//订单详情
  add_address:"/h5/shop/add_address", //添加收货地址
  update_address:"/h5/shop/update_address", //修改一条收货地址
  delete_address:"/h5/shop/delete_address", //删除一条收货地址
  set_default:"/h5/shop/set_default",//修改默认地址
  submit_order:"/h5/Shop/submit_order",//提交订单
  pay_orders:"/h5/Shop/pay_orders",//支付订单
  cancel_order:"/h5/shop/cancel_order",//取消订单
  confirm_order:"h5/shop/confirm_order",//确认收货
  search_history:"/h5/shop/get_search_history",//获取用户搜索历史
  delete_history:"/h5/shop/delete_all_search_history",//删除用户搜索历史
  search_list:"/h5/shop/search_goods_list",//搜索商品列表
  upload:"/h5/Chat/upload",//客服上传图片
  sendMessage:"/h5/Chat/send_messages",//发送消息
  getMessages:"/h5/Chat/get_messages",//获取消息
  get_group_list:"/h5/shop/get_group_list",//拼团列表
  recharge:"/h5/Devote/recharge",//贡献值微信充值
  rechargeLog:"/h5/Devote/rechargeLog",//贡献值充值记录
  orders_logistics:"/h5/Shop/orders_logistics",//物流信息
  transfer:"/h5/votes/transfer", //立即互转
  information_list:"/h5/InviteActive/information_list", //资讯列表
  store_orders_detail:"/h5/shop/store_orders_detail",//门店订单详情
  banklist:"/h5/Devote/banklist",//门店订单详情
  
  
  get_orders_list: "/h5/shop/get_orders_list",//订单管理
  get_orders_detail: "/h5/Shop/orders_detail",//订单详情
  add_address: "/h5/shop/add_address", //添加收货地址
  update_address: "/h5/shop/update_address", //修改一条收货地址
  delete_address: "/h5/shop/delete_address", //删除一条收货地址
  set_default: "/h5/shop/set_default",//修改默认地址
  submit_order: "/h5/Shop/submit_order",//提交订单
  pay_orders: "/h5/Shop/pay_orders",//支付订单
  cancel_order: "/h5/shop/cancel_order",//取消订单
  confirm_order: "/h5/shop/confirm_order",//确认收货
  search_history: "/h5/shop/get_search_history",//获取用户搜索历史
  delete_history: "/h5/shop/delete_all_search_history",//删除用户搜索历史
  search_list: "/h5/shop/search_goods_list",//搜索商品列表
  upload: "/h5/Chat/upload",//客服上传图片
  sendMessage: "/h5/Chat/send_messages",//发送消息
  getMessages: "/h5/Chat/get_messages",//获取消息
  get_group_list: "/h5/shop/get_group_list",//拼团列表
  recharge: "/h5/Devote/recharge",//贡献值微信充值
  rechargeLog: "/h5/Devote/rechargeLog",//贡献值充值记录
  orders_logistics: "/h5/Shop/orders_logistics",//物流信息
  transfer: "/h5/votes/transfer", //立即互转
  information_list: "/h5/InviteActive/information_list", //资讯列表
  WithdrawPage: "/h5/Devote/WithdrawPage",//贡献值 - 提现页面
  memberinfo: "/h5/account/memberinfo", // 个人信息
  sendSms: "/h5/Devote/sendSms",//绑定验证码
  BankBind: "/h5/Devote/BankBind",//绑定银行卡
  WithdrawSubmit: "/h5/Devote/WithdrawSubmit",//提现

  get_integral: "/h5/shop/get_integral", //火米额度
  huomi_apply: "/h5/shop/huomi_apply", //提交申请火米额度
  huomi_log: "/h5/shop/huomi_log", //火米额度记录
  WithdrawLog: "/h5/Devote/WithdrawLog", // 提现记录
  WithdrawCancel: "/h5/Devote/WithdrawCancel", // 提现撤销
  

  // 客服
  upload:"/h5/Chat/upload",//客服上传图片
  sendMessage:"/h5/Chat/send_messages",//发送消息
  getMessages:"/h5/Chat/get_messages",//获取消息

  // 方舟
  ark_buy_list:"/h5/Ark/buy_list",//推进记录
  ark_my_advance:"/h5/Ark/buy_detail",//我的推进
  ark_buy_info:"/h5/Ark/community_info",//推进社区
  ark_my_info:"/h5/Ark/my_info",//我的辅导
  ark_help_log:"/h5/Ark/help_log",//助力燃料
  ark_power_log:"/h5/Ark/power_log",//动力燃料
  ark_buy_detail:"/h5/Ark/buy_detail",//动力燃料
  ark_Index:"/h5/Ark/index",//火箭首页
  ark_getList:"/h5/Ark/get_list",//闯关列表
  ark_get_pay_info:"/h5/Ark/get_pay_info",//支付信息
  ark_Pay:"/h5/Ark/pay",//支付
  ark_user_info:"/h5/Ark/user_info",//获取币种信息
  ark_transfer:"/h5/Ark/transfer",//互转
  ark_kmt_log:"/h5/Ark/kmt_log",//燃料记录
  ark_rocket_index:"/h5/Ark/rocket_index",//应用仓列表
  ark_rocket_log:"/h5/Ark/rocket_log",//应用仓记录
  ark_fire_info:"/h5/Ark/fire_info",//自动点火信息
  ark_set_fire:"/h5/Ark/set_fire",//自动点火
  ark_order_index:"/h5/Ark/order_index",//订单记录（首页）
  ark_order_list:"/h5/Ark/order_list",//订单记录（参与人数列表）
  ark_transfer_info:"/h5/Ark/transfer_info",//划转账户
  ark_add_transfer:"/h5/Ark/add_transfer",//提交划转
  ark_transfer_log:"/h5/Ark/transfer_log",//划转记录
  ark_subscribe_log:"/h5/Ark/subscribe_log",//预约记录
  ark_subscribe:"/h5/Ark/subscribe",//预约闯关
  ark_get_user_info:"/h5/Ark/get_user_info",//获取预约池信息
  ark_add_subscribe_transfer:"/h5/Ark/add_subscribe_transfer",//提交充值
  ark_balance_detail:"/h5/Ark/balance_detail",//余额明细
  ark_queue_log:"/h5/Ark/queue_log",//排队记录
  ark_sub_cancel:"/h5/Ark/sub_cancel",//取消自动
  ark_subscribe_transfer:"/h5/Ark/subscribe_transfer",//提交互转
  ark_subscribe_info:"/h5/Ark/subscribe_info",//预约奖励信息
  ark_subscribe_help_log:"/h5/Ark/subscribe_help_log",//预约助力燃料记录
  ark_subscribe_power_log:"/h5/Ark/subscribe_power_log",//预约动力燃料记录
  ark_centre_log:"/h5/Ark/centre_log",//预约服务津贴记录

  
  game_type:"/h5/Rocket/game_type",//方舟隐藏
}
module.exports = api
