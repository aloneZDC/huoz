<template>
  <div class="address">
    <div class="content">
      <div class="close" @click="backOut">
        <img src="../../assets/shop/xwu-img.png" alt="">
      </div>
      <div class="main">
        <img src="../../assets/shop/wechat.png" />
        <p>请确认微信支付是否已完成</p>
      </div>
      <div class="button complete" @click="goOrder(1)">已完成支付</div>
      <div class="button problem" @click="goOrder(2)">支付遇到问题,重新支付</div>
    </div>
  </div>
</template>
<script>
import { Toast } from 'vant';
import {
  pay_orders
} from "@/http/api.js";
export default {
  data () {
    return {
    };
  },
  created () {
    Toast.loading({
      duration: 3000, // 持续展示 toast
      forbidClick: true,
      loadingType: 'spinner',
      overlay: true,
      message: "Loading..."
    });
  },
  mounted(){
    if (window.history && window.history.pushState) {
      history.pushState(null, null, document.URL);
      window.addEventListener('popstate', this.backOut, false);//false阻止默认事件
    }
  },
  destroyed() {
    window.removeEventListener('popstate', this.backOut, false);//false阻止默认事件
  },
  methods: {
    backOut() {
      let that = this;
      if(that.$route.query.type == 1) {
        if (that.$route.query.ids) {
          let url = '/ordersDet/' + that.$route.query.ids + "/" + 1;
          that.$router.push({ path: url });
          that.$router.push("#"+url);
        } else {
          that.$router.push({
            path: 'orders',
            query: {
              type: 1
            }
          })
        }
      }else if(that.$route.query.type == 2) {
        let url = '/ordersDet/' + that.$route.query.ids + "/" + 3;
        that.$router.push({ path: url });
      }else {
        let url = '/orders';
        that.$router.push({ path: url });
      }
    },
    goOrder (_dats) {
      //有ids 说明是单订单
      if(this.$route.query.type == 1) {
        if (this.$route.query.ids) {
          let url = '/ordersDet/' + this.$route.query.ids + "/" + 1;
          this.$router.push({ path: url });
        } else {
          this.$router.push({
            path: 'orders',
            query: {
              type: 1
            }
          })
        }
        // type=2是订单详情微信支付
      }else if(this.$route.query.type == 2) {
        if(_dats != 2) {
          let url = '/ordersDet/' + this.$route.query.ids + "/" + 3;
          this.$router.push({ path: url });
        }else {
          let obj = {};
          obj.gmo_id = this.$route.query.ids;
          obj.pay_type = 1;
          this.$http.post(pay_orders, obj).then(({ data }) => {
            if (data.code == 10000) {
              const local = window.location.host; //授权域名
              let urlenCode = '';
              urlenCode = encodeURIComponent(`http://${local}/#/payConfirm?ids=${this.$route.query.ids}&type=2`) //编码
              window.location.href = `${data.result.wx_pay.mweb_url}&redirect_url=${urlenCode}`;
            } else {
              this.$toast(data.message);
            }
         });
        }
        // let url = '/ordersDet/' + this.$route.query.ids + "/" + 1;
        // this.$router.push({ path: url });
      }else {
        // let url = '/orders';
        // this.$router.push({ path: url });
        if(_dats != 2) {
          let url = '/orders';
          this.$router.push({ path: url });
        }else {
          let obj = {};
          obj.gmo_id = this.$route.query.ids;
          obj.pay_type = 1;
          this.$http.post(pay_orders, obj).then(({ data }) => {
            if (data.code == 10000) {
                const local = window.location.host; //授权域名
                let urlenCode = ''
                urlenCode = encodeURIComponent(`http://${local}/#/payConfirm?ids=${this.$route.query.ids}&type=3`) //编码
                window.location.href = `${data.result.wx_pay.mweb_url}&redirect_url=${urlenCode}`;
            } else {
              this.$toast(data.message);
            }
         });
        }
      }
    },
    complete () { },
    problem () { },
  }
}
</script>
<style lang="scss" scoped>
.content {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  overflow: auto;
  background: #f5f5f5;
  font-size: 16px;
  font-family: "PingFang SC";
  -webkit-overflow-scrolling: touch;
  display: flex;
  flex-direction: column;
  align-items: center;
  .close {
    position: relative;
    z-index: 100;
    top: 5%;
    left: 40%;
    > img {
      width: 26px;
    }
  }
  .main {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 79%;
    img {
      width: 83px;
      height: 72px;
    }
    p {
      margin-top: 50px;
      font-size: 18px;
      font-weight: bold;
      color: #1c1c1c;
    }
  }
  .button {
    width: 345px;
    height: 40px;
    text-align: center;
    line-height: 40px;
    color: #fff;
    position: absolute;
    bottom: 50px;
    left: 50%;
    margin-left: -172px;
    border-radius: 6px;
    background-image: linear-gradient(127deg, #17b7bd, #3ef3b7);
  }
  .complete {
    bottom: 100px;
  }
  .problem {
    background-image: none;
    background: #d0d0d0;
  }
}
</style>