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
  //???????????????????????????
  {
    path: '/payConfirm',
    name: 'payConfirm',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/PayConfirm.vue')
  },
  //???????????????
  {
    path: '/alipay',
    name: 'alipay',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Alipay.vue')
  },
  //????????????
  {
    path: '/highShop',
    name: 'highShop',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/HighShop.vue')
  },
  //???????????????
  {
    path: '/zeroShop',
    name: 'zeroShop',
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/ZeroShop.vue')
  },
  // ????????????
  {
    path: '/rocket',
    name: 'rocket',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Rocket.vue')
  },
  // ????????????
  {
    path: '/rocketList',
    name: 'rocketList',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/RocketList.vue')
  },
  // ??????
  {
    path: '/advance',
    name: 'advance',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Advance.vue')
  },
  // ????????????
  {
    path: '/inDetails',
    name: 'inDetails',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/InDetails.vue')
  },
  // ????????????
  {
    path: '/between',
    name: 'between',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Between.vue')
  },
  // ??????
  {
    path: '/warehouse',
    name: 'warehouse',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Warehouse.vue')
  },
  // ????????????
  {
    path: '/wareDets',
    name: 'wareDets',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/WareDets.vue')
  },
  // ??????--??????
  {
    path: '/contract',
    name: 'contract',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Contract.vue')
  },
  // ????????????
  {
    path: '/autofire',
    name: 'autofire',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Autofire.vue')
  },
  // ????????????
  {
    path: '/notice',
    name: 'notice',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Notice.vue')
  },

  // ??????
  {
    path: '/exchange',
    name: 'exchange',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Exchange.vue')
  },
  // ????????????
  {
    path: '/linerecord',
    name: 'linerecord',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Linerecord.vue')
  },
  // ????????????
  {
    path: '/lineup',
    name: 'lineup',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Lineup.vue')
  },
  // ??????
  {
    path: '/community',
    name: 'community',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Community.vue')
  },
  // ???????????????
  {
    path: '/turn',
    name: 'turn',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Turn.vue')
  },
  // ????????????
  {
    path: '/reward',
    name: 'reward',
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Reward.vue')
  },
  //??????????????????
  {
    path: '/statistics',
    name: 'statistics',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Lines" */ '../views/Lines/Statistics.vue')
  },
  //??????
  {
    path: '/withdrawal',
    name: 'withdrawal',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/Withdrawal.vue')
  },
  // ????????????
  {
    path: '/withdrawalDet',
    name: 'withdrawalDet',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/WithdrawalDet.vue')
  },
  //???????????????
  {
    path: '/bindbank',
    name: 'bindbank',
    meta: { auth: true },
    component: () => import(/* webpackChunkName: "Shop" */ '../views/Shop/BindBank.vue')
  },
  // ------------??????----------------
  // ????????????
  {
    path: '/rocketListArk',
    name: 'rocketListArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/RocketList.vue')
  },
  // ??????
  {
    path: '/advanceArk',
    name: 'advanceArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Advance.vue')
  },
  // ????????????
  {
    path: '/inDetailsArk',
    name: 'inDetailsArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/InDetails.vue')
  },
  // ????????????
  {
    path: '/betweenArk',
    name: 'betweenArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Between.vue')
  },
  // ??????
  {
    path: '/warehouseArk',
    name: 'warehouseArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Warehouse.vue')
  },
  // ????????????
  {
    path: '/wareDetsArk',
    name: 'wareDetsArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/WareDets.vue')
  },
  // ??????--??????
  {
    path: '/contractArk',
    name: 'contractArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Contract.vue')
  },
  // ????????????
  {
    path: '/autofireArk',
    name: 'autofireArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Autofire.vue')
  },
  // ????????????
  {
    path: '/noticeArk',
    name: 'noticeArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Notice.vue')
  },

  // ??????
  {
    path: '/exchangeArk',
    name: 'exchangeArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Exchange.vue')
  },
  // ????????????
  {
    path: '/linerecordArk',
    name: 'linerecordArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Linerecord.vue')
  },
  // ????????????
  {
    path: '/lineupArk',
    name: 'lineupArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Lineup.vue')
  },
  // ??????
  {
    path: '/communityArk',
    name: 'communityArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Community.vue')
  },
  // ???????????????
  {
    path: '/turnArk',
    name: 'turnArk',
    component: () => import(/* webpackChunkName: "Ark" */ '../views/Ark/Turn.vue')
  },
  // ????????????
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
