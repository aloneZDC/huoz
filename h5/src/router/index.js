import Vue from 'vue'
import VueRouter from 'vue-router'
Vue.use(VueRouter)
const routes = [
  {
    path: '/',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Shop.vue')
  },
  {
    path: '/shop',
    name: 'shops',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Shop.vue')
  },
  {
    path: '/noticeList',
    name: 'noticeList',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/NoticeList.vue')
  },
  {
    path: '/detail/:id',
    name: 'detail',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Details.vue')
  },
  {
    path: '/pay',
    name: 'pay',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Pay.vue')
  },
  {
    path: '/orders',
    name: 'orders',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Orders.vue')
  },
  {
    path: '/ordersDet/:id/:type',
    name: 'ordersDet',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/OrdersDet.vue')
  },
  {
    path: '/ordDet/:id/:type',
    name: 'ordDet',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/OrdDet.vue')
  },
  {
    path: '/logistics',
    name: 'logistics',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Logistics.vue')
  },
  // {
  //   path: '/zero',
  //   name: 'zero',
  //   meta: { auth: true },
  //   component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Zero.vue')
  // },
  {
    path: '/customer',
    name: 'customer',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Customer.vue')
  },
  {
    path: '/address',
    name: 'address',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Address.vue')
  },
  {
    path: '/addAddress',
    name: 'addAddress',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/AddAddress.vue')
  },
  {
    path: '/changeAddress',
    name: 'changeAddress',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/ChangeAddress.vue')
  },
  {
    path: '/pond',
    name: 'pond',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Pond.vue')
  },
  {
    path: '/cart',
    name: 'cart',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Cart.vue')
  },
  //微信确认支付完成页
  {
    path: '/payConfirm',
    name: 'payConfirm',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/PayConfirm.vue')
  },
  //支付宝支付
  {
    path: '/alipay',
    name: 'alipay',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Alipay.vue')
  },
  //爆品专区
  {
    path: '/highShop',
    name: 'highShop',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/HighShop.vue')
  },
  //零元购专区
  {
    path: '/zeroShop',
    name: 'zeroShop',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/ZeroShop.vue')
  },
  // 火箭首页
  {
    path: '/rocket',
    name: 'rocket',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Rocket.vue')
  },
  // 火箭列表
  {
    path: '/rocketList',
    name: 'rocketList',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/RocketList.vue')
  },
  // 推进
  {
    path: '/advance',
    name: 'advance',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Advance.vue')
  },
  // 推进记录
  {
    path: '/inDetails',
    name: 'inDetails',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/InDetails.vue')
  },
  // 燃料互转
  {
    path: '/between',
    name: 'between',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Between.vue')
  },
  // 仓位
  {
    path: '/warehouse',
    name: 'warehouse',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Warehouse.vue')
  },
  // 仓位明细
  {
    path: '/wareDets',
    name: 'wareDets',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/WareDets.vue')
  },
  // 合同--矿机
  {
    path: '/contract',
    name: 'contract',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Contract.vue')
  },
  // 自动点火
  {
    path: '/autofire',
    name: 'autofire',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Autofire.vue')
  },
  // 购买人数
  {
    path: '/notice',
    name: 'notice',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Notice.vue')
  },

  // 互转
  {
    path: '/exchange',
    name: 'exchange',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Exchange.vue')
  },
  // 余额明细
  {
    path: '/linerecord',
    name: 'linerecord',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Linerecord.vue')
  },
  // 预约排单
  {
    path: '/lineup',
    name: 'lineup',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Lineup.vue')
  },
  // 社区
  {
    path: '/community',
    name: 'community',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Community.vue')
  },
  // 预约池互转
  {
    path: '/turn',
    name: 'turn',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Turn.vue')
  },
  // 预约奖励
  {
    path: '/reward',
    name: 'reward',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Reward.vue')
  },
  //数据统计明细
  {
    path: '/statistics',
    name: 'statistics',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Statistics.vue')
  },
  //归仓
  {
    path: '/withdrawal',
    name: 'withdrawal',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Withdrawal.vue')
  },
  // 归仓记录
  {
    path: '/withdrawalDet',
    name: 'withdrawalDet',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/WithdrawalDet.vue')
  },
  //绑定银行卡
  {
    path: '/bindbank',
    name: 'bindbank',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/BindBank.vue')
  },
  // ------------方舟----------------
  // 方舟列表
  {
    path: '/rocketListArk',
    name: 'rocketListArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/RocketList.vue')
  },
  // 推进
  {
    path: '/advanceArk',
    name: 'advanceArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Advance.vue')
  },
  // 推进记录
  {
    path: '/inDetailsArk',
    name: 'inDetailsArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/InDetails.vue')
  },
  // 燃料互转
  {
    path: '/betweenArk',
    name: 'betweenArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Between.vue')
  },
  // 仓位
  {
    path: '/warehouseArk',
    name: 'warehouseArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Warehouse.vue')
  },
  // 仓位明细
  {
    path: '/wareDetsArk',
    name: 'wareDetsArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/WareDets.vue')
  },
  // 合同--矿机
  {
    path: '/contractArk',
    name: 'contractArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Contract.vue')
  },
  // 自动点火
  {
    path: '/autofireArk',
    name: 'autofireArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Autofire.vue')
  },
  // 购买人数
  {
    path: '/noticeArk',
    name: 'noticeArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Notice.vue')
  },

  // 互转
  {
    path: '/exchangeArk',
    name: 'exchangeArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Exchange.vue')
  },
  // 余额明细
  {
    path: '/linerecordArk',
    name: 'linerecordArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Linerecord.vue')
  },
  // 预约排单
  {
    path: '/lineupArk',
    name: 'lineupArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Lineup.vue')
  },
  // 社区
  {
    path: '/communityArk',
    name: 'communityArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Community.vue')
  },
  // 预约池互转
  {
    path: '/turnArk',
    name: 'turnArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Turn.vue')
  },
  // 预约奖励
  {
    path: '/rewardArk',
    name: 'rewardArk',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Ark/Reward.vue')
  },
]

const router = new VueRouter({
  // mode: 'history',
  base: process.env.BASE_URL,
  routes
})

export default router
