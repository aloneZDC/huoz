<template>
  <div class="ordersdet">
    <header>
      <button class="iconfont iconfanhui back" @click="goBack">
        <i class="iconfont icon-return"></i>
      </button>
      <h3>{{ info.title }}</h3>
    </header>
    <div class="content">
      <div class="cont-header">
        <!-- 已付款 -->
        <div v-if="dataConfig.gmo_status == 1">
          <div class="header-top">
            <p>买家已付款</p>
            <p>等待卖家发货</p>
          </div>
          <div class="header-bottom">
            <!-- <button>退款</button> -->
            <!-- <button @click="goCustomer">联系客服</button>
            <button @click="tipSend">提醒发货</button> -->
          </div>
        </div>
        <!-- 未付款 -->
        <div v-else-if="dataConfig.gmo_status == 2">
          <div class="header-top">
            <p>等待买家付款</p>
            <p>剩{{ orderTime }}自动关闭</p>
          </div>
          <div class="header-bottom">
            <div>
              <img src="../../assets/shop/shop/eug-img-x.png" alt="" />
              <button @click="cancelOrder">取消订单</button>
            </div>
            <div>
              <img src="../../assets/shop/shop/eug-img.png" alt="" />
              <button
                @click="
                  go_pay(
                    dataConfig.total_pay_huo_price,
                    dataConfig.gmo_id,
                    dataConfig.category_type,
                    dataConfig.category_pid
                  )
                "
              >
                去付款
              </button>
            </div>
          </div>
        </div>

        <!-- 已发货 -->
        <div v-else-if="dataConfig.gmo_status == 3">
          <div class="header-top">
            <p>卖家已发货</p>
            <p>剩{{ orderTime }}自动确认</p>
          </div>
          <div class="header-bottom">
            <div>
              <img src="../../assets/shop/shop/eug-img-s.png" alt="" />
              <!-- <button @click="goCustomer">联系客服</button> -->
              <button @click="confirm">确认收货</button>
            </div>
          </div>
        </div>

        <!-- 交易成功 -->
        <div v-else-if="dataConfig.gmo_status == 4">
          <!-- 是否是新人礼包 -->
          <div v-if="dataConfig.goods[0].go_goods_id == 1">
            <div class="header-top">
              <p>交易成功</p>
            </div>
          </div>
          <div v-else>
            <div class="header-top">
              <p>交易成功</p>
              <p v-if="Number(dataConfig.gmo_close_time) > 0">
                剩{{ orderTime }}自动关闭退款
              </p>
            </div>
            <div class="header-bottom">
              <!-- <button>退款</button> -->
              <!-- <button @click="goCustomer">联系客服</button> -->
              <!-- <button>评价</button> -->
            </div>
          </div>
        </div>
        <!-- 订单已取消 -->
        <div v-else-if="dataConfig.gmo_status == 5">
          <div class="header-top">
            <p>交易关闭</p>
            <p
              v-if="dataConfig.category_type == 5 && dataConfig.refund_num > 0"
            >
              订单已取消(退款:券{{ dataConfig.refund_num }})
            </p>
            <p v-else>订单已取消</p>
          </div>
          <div class="header-bottom">
            <!-- <button @click="goCustomer">联系客服</button> -->
          </div>
        </div>
      </div>
      <div class="content-middle">
        <div
          class="cont-bottom"
          v-if="
            dataConfig.gmo_status == 1 ||
            dataConfig.gmo_status == 2 ||
            dataConfig.gmo_status == 5
          "
        >
          <!-- 等待支付 -->
          <div class="bottom-first-w">
            <div>
              <img src="../../assets/shop/jiant-imhg.png" alt="" />
            </div>
            <div>
              <p>
                <span>{{ dataConfig.gmo_receive_name }}</span>
                <span>{{ dataConfig.gmo_mobile }}</span>
              </p>
              <p v-if="dataConfig.gmo_pickedup_title">
                {{ dataConfig.gmo_pickedup_title }}
              </p>
              <p v-if="dataConfig.gmo_pickedup_tel">
                {{ dataConfig.gmo_pickedup_tel }}
              </p>
              <p>{{ dataConfig.gmo_address }}</p>
            </div>
          </div>
        </div>
        <div class="bottom-first" v-else>
          <div @click="goLogistics(dataConfig.gmo_id)">
            <div>
              <img src="../../assets/shop/dingd_wuliu_icon.png" alt="" />
            </div>
            <div>
              <p>{{ dataConfig.logistics.context }}</p>
              <!-- <p>
                <span
                  >{{ dataConfig.gmo_express_name }}
                  {{ dataConfig.gmo_express_code }}</span
                >
                <button
                  class="copy"
                  @click="copy"
                  data-clipboard-action="copy"
                  :data-clipboard-text="dataConfig.gmo_express_code"
                  src="../../assets/shop/fuz_icon.png"
                >
                  复制
                </button>
                <span>复制快递单号查询物流详情</span>
              </p> -->
              <p>{{ dataConfig.logistics.time }}</p>
            </div>
            <div>
              <i class="iconfont icon-return"></i>
            </div>
          </div>
          <div>
            <div>
              <img src="../../assets/shop/jiant-imhg.png" alt="" />
            </div>
            <div>
              <p>
                <span>{{ dataConfig.gmo_receive_name }}</span>
                <span>{{ dataConfig.gmo_mobile }}</span>
              </p>
              <p>{{ dataConfig.gmo_address }}</p>
            </div>
          </div>
        </div>
        <div class="list">
          <div>
            <span></span>
            <span>{{ dataConfig.category_type }}</span>
          </div>
          <div
            class="list-top"
            v-for="(item, index) in dataConfig.goods"
            :key="index"
          >
            <div>
              <img :src="item.go_img" alt="" />
            </div>
            <div>
              <p>{{ item.go_title }}</p>
              <p v-if="item.format">{{ item.format.name }}</p>
              <div class="list-top-li">
                <span>{{ dataConfig.category_pid == 3 ? `${(item.go_price / dataConfig.hm_price).toFixed(4)}金米` :  `￥${item.go_price - 0}` }}</span>
                <span><span class="size">X</span>{{ item.go_num }}</span>
              </div>
              <div v-if="dataConfig.category_pid != 3 && dataConfig.category_pid != 17" class="list-top-tw">赠与≈{{ Math.round(dataConfig.gmo_give_num * dataConfig.hm_price) }} 积分</div>
            </div>
          </div>
          <div class="list-prc">
            <div>
              <span>商品总额</span>
              <span>{{ dataConfig.category_pid == 3 ? `${(dataConfig.gmo_total_price / dataConfig.hm_price).toFixed(4)}金米` : `￥${dataConfig.gmo_total_price}` }}</span>
            </div>
            <div>
              <span>运费</span>
              <span>{{ dataConfig.category_pid == 3 ? `${(dataConfig.gmo_pay_postage / dataConfig.hm_price).toFixed(4)}金米` : `￥${dataConfig.gmo_pay_postage}` }}</span>
            </div>
            <!-- <div>
              <span>代金券抵扣</span>
              <span>-￥{{ dataConfig.gmo_equal_num }}</span>
            </div> -->
            <!-- <div>
              <span>会员等级优惠</span>
              <span class="membership">-￥{{ dataConfig.gmo_discount }}</span>
            </div> -->
          </div>
          <div class="list-pay">
            <span>实付款:</span>
            <span v-if="dataConfig.category_type == 4"
              >{{ dataConfig.gmo_pay_num }} 积分</span
            >
            <span v-else>
              <span v-if="dataConfig.category_pid == 3">
                {{ dataConfig.total_pay_huo_price }}{{ dataConfig.category_pid == 3 ? '金米' : dataConfig.give_currency.currency_name }}
              </span>
              <span v-else-if="dataConfig.category_pid == 17">
                ￥{{ dataConfig.total_pay_cny_price }}
              </span>
              <span v-else>
                ￥{{ dataConfig.total_pay_cny_price }}≈{{
                dataConfig.total_pay_huo_price
              }}{{ dataConfig.category_pid == 3 ? '金米' : dataConfig.give_currency.currency_name }}
              </span>
            </span>  
          </div>
        </div>
        <div class="list-order" v-if="dataConfig.gmo_status == 5">
          <div>
            <span>订单编号：</span>
            <span>{{ dataConfig.gmo_code }}</span>
            <img
              src="../../assets/shop/clfdg-img.png"
              class="copy"
              @click="copy"
              data-clipboard-action="copy"
              :data-clipboard-text="dataConfig.gmo_code"
            />
          </div>
          <!-- <div v-if="dataConfig.gmo_pay_code != ''">
            <span>微信交易号：</span>
            <span>{{ dataConfig.gmo_pay_code }}</span>
          </div> -->
          <div>
            <span>创建时间：</span>
            <span>{{ dataConfig.gmo_add_time }}</span>
          </div>
          <div v-if="dataConfig.gmo_pay_time != ''">
            <span>付款时间：</span>
            <span>{{ dataConfig.gmo_pay_time }}</span>
          </div>
          <div v-if="dataConfig.gmo_ship_time != ''">
            <span>发货时间：</span>
            <span>{{ dataConfig.gmo_ship_time }}</span>
          </div>
          <div v-if="dataConfig.gmo_sure_time != ''">
            <span>成交时间：</span>
            <span>{{ dataConfig.gmo_sure_time }}</span>
          </div>
        </div>
        <div class="list-order" v-else>
          <div>
            <span>订单编号：</span>
            <span>{{ dataConfig.gmo_code }}</span>
            <img
              src="../../assets/shop/clfdg-img.png"
              class="copy"
              @click="copy"
              data-clipboard-action="copy"
              :data-clipboard-text="dataConfig.gmo_code"
            />
          </div>
          <!-- <div v-if="dataConfig.gmo_pay_code != ''">
            <span>微信交易号：</span>
            <span>{{ dataConfig.gmo_pay_code }}</span>
            <img
              src="../../assets/shop/clfdg-img.png"
              class="copy"
              @click="copy"
              data-clipboard-action="copy"
              :data-clipboard-text="dataConfig.gmo_pay_code"
            />
          </div> -->
          <div>
            <span>创建时间：</span>
            <span>{{ dataConfig.gmo_add_time }}</span>
          </div>
          <div v-if="dataConfig.gmo_pay_time != ''">
            <span>付款时间：</span>
            <span>{{ dataConfig.gmo_pay_time }}</span>
          </div>
          <div v-if="dataConfig.gmo_ship_time != ''">
            <span>发货时间：</span>
            <span>{{ dataConfig.gmo_ship_time }}</span>
          </div>
          <div v-if="dataConfig.gmo_sure_time != ''">
            <span>成交时间：</span>
            <span>{{ dataConfig.gmo_sure_time }}</span>
          </div>
        </div>
      </div>
    </div>
    <div class="maskak" v-if="fullBoll"></div>
    <div class="changeShow" v-if="fullBoll">
      <div>
        <img src="../../assets/shop/xwu-img.png" alt="" @click="cenelAll" />
      </div>
      <!-- 1是多支付方式 2是金米支付方式 -->
      <p v-if="payType == 2 || 1">该商品需支付{{ dataConfig.category_pid == 3 ? `${payNum}金米` : dataConfig.category_pid == 17 ? `￥${dataConfig.total_pay_cny_price}` : `￥${dataConfig.total_pay_cny_price}≈${payNum - 0}赠与收益` }}</p>
      <p v-else>该商品需支付￥{{ payNum }},请选择支付方式</p>
      <!-- <div v-if="payType == 1">
        <div
          class="mask-tak"
          v-for="(item, index) in payData"
          :key="index"
          @click="changePay(index)"
        >
          <div>
            <img src="../../assets/shop/gwq.png" v-if="item.id == 2" />
            <img
              src="../../assets/shop/cart/mlfxjk_icon.png"
              v-else-if="item.id == 3"
            />
            <img src="../../assets/shop/zh.png" v-else />
            <span v-if="item.id == 4">{{ item.name }}</span>
            <span v-else>{{ item.name }}(金米{{ item.money }})</span>
          </div>
        </div>
      </div> -->
      <!-- 消费积分支付 -->
      <div v-if="payType == 2">
        <div class="mask-tak" v-for="(item, index) in payData" :key="index">
          <div>
            <img src="../../assets/shop/fire_img.png" class="payicon" />
            <span>{{ item.name }}(金米{{ item.money }})</span>
          </div>
          <div>
            <img src="../../assets/shop/cart/wei-img.png" class="active" />
          </div>
        </div>
      </div>
      <div v-else>
        <div
          class="mask-tak"
          v-for="(item, index) in payData"
          :key="index"
          @click="changePay(item.id)"
        >
          <div>
            <img :src="item.img" alt="" />
            {{ item.name }}{{item.id == 1 ? `(余额${item.money})` : ''}}
          </div>
          <div>
            <img :src="item.id == isA ? isCheck : isCheckNo" alt="" />
          </div>
        </div>
      </div>
      <div class="line"></div>
      <div @click="shopPay">
        <button>确认支付</button>
      </div>
    </div>
    <!-- 输入密码弹窗 -->
    <KeyBord
      ref="keyBord"
      @data-password="buttonNum"
      @handleShow="handleShow"
    ></KeyBord>
  </div>
</template>

<script>
import {
  get_orders_detail,
  cancel_order,
  pay_type,
  pay_orders,
  confirm_order,
} from "@/http/api.js";
export default {
  name: "ordersDet",
  components: {},
  inject: ["reload"],
  data() {
    return {
      info: {
        title: "订单详情",
      },
      shopOrdName: "",
      dataOption: {
        // gmo_id: '3',
        gmo_id: this.$route.params.id,
        pay_type: "",
      },
      fullBoll: false,
      dataConfig: {
        logistics: "",
        give_currency: '',
      },
      timer: null,
      orderTime: "",
      isShow: true,
      payData: [], // 支付方式
      isCheck: require("../../assets/shop/cart/wei-img.png"),
      isCheckNo: require("../../assets/shop/cart/dhi-img.png"),
      isA: 1, // 支付方式id选择
      goodsId: "",
      payType: "",
      payTypeId: "",
    };
  },
  methods: {
    goLogistics(_id) {
      let url = "/logistics" + "?id=" + _id;
      this.$router.push({ path: url });
    },
    // 联系客服
    goCustomer() {
      this.$router.push({ path: "/wxcustomer" });
      return;
      this.$router.push({ path: "/customer" });
    },
    // 确认收货
    confirm() {
      this.$http.post(confirm_order, this.dataOption).then(({ data }) => {
        if (data.code == 10000) {
          window.toast_txt(data.message);
          setTimeout(() => {
            this.reload();
          }, 1000);
        } else {
          window.toast_txt(data.message);
        }
      });
    },
    tipsMsg() {
      window.toast_txt("敬请期待！");
    },
    tipSend() {
      window.toast_txt("已通知卖家发货");
    },
    goBack() {
      if (this.$platform == "android") {
        if (this.$route.params.type == 1) {
          apps.exit();
        } else if (this.$route.params.type == 3) {
          apps.exit();
        } else {
          this.$router.back();
        }
      } else {
        if (this.$route.params.type == 1) {
          this.$router.push({ path: "/" });
        } else if (this.$route.params.type == 3) {
          this.$router.push({ path: "/orders" });
        } else {
          this.$router.back();
        }
      }
    },
    cenelAll() {
      this.fullBoll = false;
      this.isA = this.payData[0].id;
      this.payType = "";
      this.payTypeId = "";
    },
    copy() {
      let _this = this;
      let clipboard = new this.clipboard(".copy");
      clipboard.on("success", function () {
        _this.$toast("复制成功");
      });
      clipboard.on("error", function () {
        _this.$toast("复制失败");
      });
    },
    _pay_type() {
      let option = {
        type: "",
      };
      if (this.payType == 2) {
        option.type = this.payType;
      } else if(this.payType == 99 || this.payType == 16 || this.payType == 17) {
        option.type = this.payType;
      } else {
        option.type = 1;
      }
      this.$http.post(pay_type, option).then(({ data }) => {
        if (data.code == 10000) {
          this.payData = [];
          if(this.payType == 2) {
            this.payData = data.result.pay_type;
          }else if(this.payType == 17){
            let obj = {};
            data.result.pay_type.forEach((item,index) =>{
              let obj = {
                id: item.id,
                name: item.name,
                is_recommend: item.is_recommend,
                img: '',
              };
              if(item.id == 3){
                obj.img = require("../../assets/shop/cart/vxzf_icon.png");
              }else if(item.id == 4){
                obj.img = require("../../assets/shop/cart/zfbzf_icon.png");
              }
              this.payData.push(obj);
              this.isA = this.payData[0].id;
            });
          }else {
            let obj = {};
            data.result.pay_type.forEach((item,index) =>{
              let obj = {
                id: item.id,
                name: item.name,
                is_recommend: item.is_recommend,
                img: '',
              };
              if(item.id == 1) {
                // obj.img = require("../../assets/shop/gwq.png");
                obj.img = require("../../assets/shop/fire_img.png");
                obj.money = item.money;
              }else if(item.id == 3){
                obj.img = require("../../assets/shop/cart/vxzf_icon.png");
              }else if(item.id == 4){
                obj.img = require("../../assets/shop/cart/zfbzf_icon.png");
              }
              this.payData.push(obj);
              this.isA = this.payData[0].id;
            });
          }
          // 弹出支付选择
          this.fullBoll = true;
        } else {
          window.toast_txt(data.message);
        }
      });
    },
    _orders() {
      this.$http.post(get_orders_detail, this.dataOption).then(({ data }) => {
        this.dataConfig = {
          ...data.result,
        };
        // 判断是否是新人礼包
        this.goodsId = this.dataConfig.goods[0].go_goods_id;
        if (Number(data.result.gmo_close_time) > 0) {
          let day = parseInt(data.result.gmo_close_time / 60 / 60 / 24);
          let hr = parseInt((data.result.gmo_close_time / 60 / 60) % 24);
          let min = parseInt((data.result.gmo_close_time / 60) % 60);
          min = min + 1;
          // let sec = parseInt(data.result.gmo_close_time % 60);
          if (day == 0) {
            day = day;
          } else {
            day = day > 9 ? day : "0" + day;
          }
          if (hr == 0) {
            hr = hr;
          } else {
            hr = hr > 9 ? hr : "0" + hr;
          }
          min = min > 9 ? min : "0" + min;
          // sec = sec > 9 ? sec : '0' + sec;
          this.orderTime = `${day}天${hr}时${min}分`;
        } else {
          clearInterval(this.timer);
          this.timer = null;
        }
      });
    },
    go_pay(_num, _id, type, num_id) {
      // 所需支付的数量
      this.payNum = _num;
      // 订单id
      this.dataOption.gmo_id = _id;
      // 等于4是积分支付，传数字2去支付
      // if (type == 4) {
      //   this.payType = 2;
      // }
      // 1和2 分别是乐购区和自提区 3是置换区 16爆品 17新人
      if (num_id == 2 || num_id == 1) {
        this.payType = 1;
      }else if (num_id == 16) {
        this.payType = 16;
      }else if (num_id == 17) {
        this.payType = 17;
      }else {
        this.payType = 2;
      }
      // 点击去支付的时候再请求支付方式
      this._pay_type();
    },
    shopPay() {
      this.toastShow = false;
      this.dataOption.pay_type = this.isA; //支付方式id
      if (this.payType == 2) {
        this.isA = this.payType; 
      };
      //判断支付方式 1为赠与收益支付，3为微信支付 为积分支付
      if (this.isA == 1 || this.isA == 2) {
        this.$refs.keyBord.showKey();
        this.dataOption.pay_type = this.isA;
      }  else if(this.isA == 3) {
        // 微信支付
        let obj = {};
        obj.gmo_id = this.dataOption.gmo_id;
        obj.pay_type = this.isA;
        this.$http.post(pay_orders, obj).then(({ data }) => {
          if (data.code == 10000) {
            let url = data.result.pay_url;
            if (this.$platform == "android") {
              apps.openBrowser(url);
            } else {
              window.location.href = url + '?v=' + (new Date().getTime());
            }
          } else {
            this.$toast(data.message);
          }
        });
      }else if(this.isA == 4) {
        // 支付宝支付
        let obj = {};
        obj.gmo_id = this.dataOption.gmo_id;
        obj.pay_type = this.isA;
        this.$http.post(pay_orders, obj).then(({ data }) => {
          if (data.code == 10000) {
            let url = data.result.pay_url;
            if (this.$platform == "android") {
              apps.openBrowser(url);
            } else {
              window.location.href = url + '?v=' + (new Date().getTime());
            }
          } else {
            this.$toast(data.message);
          }
        });
      }
    },
    cancelOrder() {
      this.$http.post(cancel_order, this.dataOption).then(({ data }) => {
        if (data.code == 10000) {
          window.toast_txt(data.message);
          this.reload();
        }
      });
    },
    changePay(_index) {
      if (Number(this.isA) != Number(_index)) {
        this.isA = _index;
      }
    },
    buttonNum(data) {
      this.dataOption.pay_pwd = data;
      this.$http.post(pay_orders, this.dataOption).then(({ data }) => {
        if (data.code == 10000) {
          this.$toast(data.message);
          setTimeout(() => {
            this.reload();
          }, 1000);
        } else {
          this.$toast(data.message);
        }
      });
      //关闭付款弹窗
      this.$refs.keyBord.closeKey();
    },
    //接收子组件传递 询问框显示
    handleShow(show) {
      this.toastShow = show;
    },
    backChange() {
      this.goBack();
    },
  },
  mounted() {
    this._orders();
    this.timer = setInterval(() => {
      setTimeout(this._orders(), 0);
    }, 1000 * 60);
    if (window.history && window.history.pushState) {
      history.pushState(null, null, document.URL);
      window.addEventListener("popstate", this.backChange, false); //false阻止默认事件
    }
  },
  //当离开页面时，清除倒计时
  beforeDestroy() {
    clearInterval(this.timer);
    this.timer = null;
  },
  destroyed: function () {
    window.removeEventListener("popstate", this.backChange, false); //false阻止默认事件
  },
  created() {
    // this._orders();
    // this._pay_type();
  },
};
</script>

<style lang="scss" scoped>
header {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 44px;
  background: #f5f5f5;
  // color: #fff;
  text-align: center;
  line-height: 44px;
  display: flex;
  align-items: center;
}
header button.back,
header div.right_btn {
  position: absolute;
  top: 0;
  height: 44px;
  border: none;
  background: transparent;
  padding: 0;
  margin: 0;
  display: flex;
  align-items: center;
}
header button.back {
  left: 0;
  padding-left: 12px;
  // color: #fff;
  > i {
    font-size: 20px;
    font-weight: bold;
  }
}

header h3 {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: bold;
}

.content {
  z-index: 2;

  position: absolute;
  top: 42px;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  overflow: auto;
  background: #f5f5f5;
  -webkit-overflow-scrolling: touch;
  font-size: "PingFang TC";
  > .cont-header {
    // height: 128px;
    width: 100%;
    // background: url("../../assets/shop/dingdanxiangq_bg.png") no-repeat center;
    // background-size: 100% 100%;
    font-size: 14px;
    font-weight: normal;
    color: #262626;
    text-align: center;
    padding: 0 14px;
    box-sizing: border-box;
    margin-top: 10px;
    > div {
      display: flex;
      padding: 0 15px;
      align-items: center;
      justify-content: space-between;
    }
    .header-top {
      padding-top: 20px;
      padding-bottom: 17px;
      > p:nth-child(1) {
        color: #ff461e;
        font-size: 16px;
      }
      > p:nth-child(2) {
        padding-top: 8px;
      }
    }
    .header-bottom {
      display: flex;
      > div {
        text-align: center;
        > img {
          width: 22px;
          display: block;
          margin: 0 auto;
        }
        button {
          width: 68px;
          color: #434343;
          margin-top: 10px;
          font-size: 14px;
          background: none;
          border: none;
        }
      }
    }

    div {
      background-color: #fff;
      border-radius: 10px;
    }
  }
  .content-middle {
    padding: 0 15px;
    box-sizing: border-box;
  }
  .cont-bottom {
    > .bottom-first-w {
      margin-top: 14px;
      width: 100%;
      height: auto;
      background: #ffffff;
      box-shadow: -2px 4px 20px 0px rgba(124, 70, 3, 0.05);
      border-radius: 15px;
      color: #000;
      display: flex;
      align-items: center;
      padding: 22px 14px;
      box-sizing: border-box;
      > div:nth-child(1) {
        margin-right: 15px;
        > img {
          width: 15px;
          height: 19px;
        }
      }
      > div:nth-child(2) {
        > p:nth-child(1) {
          font-size: 13px;
          margin-bottom: 8px;
          > span:nth-child(2) {
            padding-left: 10px;
            color: #8a8a8a;
          }
        }
        > p:nth-child(2) {
          font-size: 14px;
        }
      }
    }
  }
  .bottom-first {
    margin-top: 14px;
    width: 100%;
    height: auto;
    background: #ffffff;
    box-shadow: -2px 4px 20px 0px rgba(124, 70, 3, 0.05);
    border-radius: 15px;
    color: #000;
    padding: 22px 14px;
    box-sizing: border-box;
    > div:nth-child(1) {
      border-bottom: 1px solid #d3d3d3;
      display: flex;
      align-items: center;
      padding-bottom: 12px;
      > div:nth-child(1) {
        margin-right: 10px;
        > img {
          width: 18px;
        }
      }
      > div:nth-child(2) {
        color: #8a8a8a;
        font-size: 10px;
        > p:nth-child(1) {
          font-size: 14px;
          color: #ff461e;
          overflow: hidden;
          white-space: nowrap;
          text-overflow: ellipsis;
          width: 250px;
          > button {
            margin-left: 10px;
            width: 43px;
            height: 17px;
            border: 1px solid #ff461e;
            border-radius: 3px;
            font-size: 10px;
            color: #ff461e;
            background: transparent;
          }
          > span:nth-child(3) {
            display: flex;
            margin-top: 5px;
          }
        }
        > p:nth-child(2) {
          padding-top: 8px;
        }
      }
      > div:nth-child(3) {
        flex: 1;
        transform: rotateY(180deg);
      }
    }
    > div:nth-child(2) {
      padding-top: 10px;
      display: flex;
      align-items: center;
      > div:nth-child(1) {
        margin-right: 10px;
        > img {
          width: 16px;
        }
      }
      > div:nth-child(2) {
        > p:nth-child(1) {
          font-size: 13px;
          margin-bottom: 8px;
          > span:nth-child(2) {
            padding-left: 10px;
            color: #8a8a8a;
          }
        }
        > p:nth-child(2) {
          font-size: 14px;
        }
      }
    }
  }

  .list {
    margin-top: 14px;
    width: 100%;
    height: auto;
    background: #ffffff;
    box-shadow: -2px 4px 20px 0px rgba(124, 70, 3, 0.05);
    border-radius: 15px;
    color: #333;
    padding: 0 14px 22px;
    box-sizing: border-box;
    > div:nth-child(1) {
      color: #353333;
      display: flex;
      align-items: center;
      height: 20px;
      font-size: 16px;
      padding-top: 20px;
      > span:nth-child(1) {
        width: 9px;
        height: 9px;
        background: #ff461e;
        border-radius: 50%;
        opacity: 1;
        margin-right: 4px;
        display: inline-block;
      }
    }
    > .list-top {
      font-size: 16px;
      font-weight: normal;
      display: flex;
      align-items: center;
      padding: 20px 0;
      border-bottom: 1px solid #d3d3d3;
      > div:nth-child(1) {
        margin-right: 9px;
        > img {
          width: 80px;
          height: 80px;
          border-radius: 6px;
        }
      }
      > div:nth-child(2) {
        width: 100%;
        > p {
          text-overflow: -o-ellipsis-lastline;
          overflow: hidden;
          text-overflow: ellipsis;
          display: -webkit-box;
          -webkit-line-clamp: 1;
          -webkit-box-orient: vertical;
          width: 220px;
        }
        > p:nth-child(2) {
          font-size: 12px;
          color: #333;
          margin-top: 10px;
        }
        > .list-top-li {
          display: flex;
          padding-top: 11px;
          align-items: flex-end;
          > span:nth-child(1) {
            color: #333;
          }
          > span:nth-child(2) {
            font-size: 12px;
            flex: 1;
            text-align: right;
          }
          .size {
            font-size: 8px;
          }
        }
        > .list-top-tw {
          margin-top: 10px;
          height: 27px;
          background: #ffe1db;
          opacity: 1;
          border-radius: 6px;
          color: #ff461e;
          font-size: 14px;
          padding: 0 10px;
          // width: 180px;
          width: fit-content;
          display: flex;
          justify-content: center;
          align-items: center;
        }
      }
    }
    > .list-prc {
      padding-top: 20px;
      color: #262626;
      > div {
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 11px;
      }
    }
    > .list-pay {
      padding-top: 5px;
      font-size: 14px;
      color: #4e4e4e;
      text-align: right;
      span {
        flex: 5;
        text-align: right;
      }
      > span:nth-child(2) {
        flex: 1;
        color: #FF461E;
        margin-left: 5px;
      }
    }
  }
  .list-order {
    margin-top: 14px;
    margin-bottom: 20px;
    width: 100%;
    height: auto;
    background: #ffffff;
    box-shadow: -2px 4px 20px 0px rgba(124, 70, 3, 0.05);
    border-radius: 15px;
    padding: 22px 14px;
    box-sizing: border-box;
    font-size: 12px;
    font-weight: normal;
    color: #000000;
    > div {
      padding-bottom: 10px;
      img {
        margin-left: 30px;
        width: 14.48px;
        height: 14.48px;
        // border-radius: 3px;
        // font-size: 10px;
        // color: #FF461E;
        // background: transparent;
      }
    }
  }
}
.changeShow {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 90%;
  background: #ffffff;
  border-radius: 6px;
  padding: 0 12px;
  box-sizing: border-box;
  padding-top: 12px;
  box-shadow: 0px -1px 0px 0px rgba(230, 230, 230, 1);
  font-family: "PingFang SC";
  z-index: 5;
  > div:nth-child(1) {
    width: 100%;
    text-align: right;
    > img {
      width: 21px;
    }
  }
  > p {
    text-align: center;
    font-size: 18px;
    font-weight: bold;
    color: #1c1c1c;
    padding: 24px 0;
  }
  .mask-tak {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 24px;
    padding-bottom: 24px;
    font-weight: 500;
    font-size: 12px;
    color: #1c1c1c;
    > div:nth-child(1) {
      > img {
        width: 20px;
        vertical-align: middle;
      }
      > span {
        padding-left: 8px;
      }
    }
    > div:nth-child(2) {
      > img {
        width: 20px;
        height: 20px;
        vertical-align: middle;
      }
    }
  }
  > .line {
    border-top: 1px solid #e8e8e8;
    padding-bottom: 30px;
  }
  > div:last-child {
    font-weight: 500;
    text-align: center;
    padding-bottom: 30px;
    color: #fff;
    > button {
      font-size: 16px;
      width: 191px;
      height: 40px;
      outline: none;
      border: none;

      background: #FF461E;
      border-radius: 6px;
    }
  }
}
.maskak {
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  background: #1d1b1b;
  opacity: 0.53;
  z-index: 4;
}
.membership {
  color: #FF461E;
}

</style>