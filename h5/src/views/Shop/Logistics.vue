<template>
  <div class="logistics">
    <top-header :info="info"></top-header>
    <div class="content">
      <div class="cont-top">
        <p>卖家已发货</p>
        <div>
          <span>{{ dataConfig.gmo_express_name }} {{ dataConfig.gmo_express_code }}</span>
          <img class="copy" @click="copy" data-clipboard-action="copy" :data-clipboard-text="dataConfig.gmo_express_code" src="../../assets/shop/fuz_icon.png" alt="">
        </div>
      </div>
      <div class="cont-list">
        <div>
          <van-steps direction="vertical" :active="0">
            <van-step v-for="(item, index) in dataConfig.logistics" :key="index" class="list-line">
              <!-- <p class="line-f">【{{ item.status }}】</p> -->
              <p v-html="item.context">{{item.context}}</p>
              <p>{{item.time}}</p>
            </van-step>
        </van-steps>
        </div>
      </div>
    </div>
  </div>  
</template>

<script>
import TopHeader from "@/components/TopHeader";
import { orders_logistics } from "@/http/api.js";
export default {
  name: "logistics",
  components: {
    TopHeader,
  },
  data() {
    return {
      info: {
        title: '物流详情',
        isBack: true,
        exit: false,
      },
      dataOption: {
        gmo_id: this.$route.query.id
      },
      items:[],
      dataConfig: {},
    }
  },
  mounted() {
    window.phoneCall = this.phoneCall;
  },
  methods: {
    phoneCall(phone) {
      if (this.$platform == 'ios') {
        window.webkit.messageHandlers.iosAction.postMessage(phone);
      } else if (this.$platform == "android") {
        apps.callPhone(phone);
      }
    },
    copy() {
      let _this = this;
      let clipboard = new this.clipboard(".copy");
      clipboard.on('success', function() {
        _this.$toast("复制成功");
      });
      clipboard.on('error', function() {
        _this.$toast("复制失败");
      });
    },
    _list() {
      this.$http.post(orders_logistics, this.dataOption).then(({ data }) => {
        if(data.code == 10000) {
          this.dataConfig = {
            ...data.result
          };
          const telReg = /(1[3|4|5|7|8][\d]{9}|0[\d]{2,3}-[\d]{7,8}|400[-]?[\d]{3}[-]?[\d]{4})/g;
          let current = "";
          this.dataConfig.logistics.map((item, index) => {
            current = item.context.match(telReg);
            if (current) {
              current.map((list, indexs) => {
                let temp = list;
                let tempList = `<span class="copy phone-num" style="text-decoration:none;color: #0A7DE6;" onclick="phoneCall('${temp}')">${temp}</span>`;
                // let tempList = '<span class="copy phone-num" style="text-decoration:none;color: #0A7DE6;" onclick="phoneCall('+ temp +')">' + temp + '</span>';
                // item.context =  item.context.replace(list, '<a href="tel:' + temp + '"class="copy phone-num" style="text-decoration:none;color: #0A7DE6;" onclick="phoneCall('+ temp +')">' + temp + '</a>');
                item.context = item.context.replace(list, tempList);
               
              })
            }
          })
        }
      });
      // window.phoneCall = function(_phones){ 
      //   console.log(_phones);
      // }

    }
  },
  created() {
    this._list();
  }
}
</script>

<style lang="scss" scoped>
  /deep/ header {
    background: #FF461E;
    background-size: 100% 100%;
    color: #fff;
    i {
      color: #fff;
    }
  }
  /deep/ .van-step__title--active {
    color: #FF461E;
  }
  /deep/ .van-step__icon--active {
    color: #FF461E;
  }  
  .content {
    position: absolute;
    top: 42px;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    overflow: auto;
    background: #F5F5F5;
    -webkit-overflow-scrolling: touch;
    font-size: "PingFang TC";
    z-index: 2;
    > .cont-top {
      height: 84px;
      width: 100%;
      // background: url("../../assets/shop/dingdanxiangq_bg.png") no-repeat center;
      // background-size: 100% 100%;
      background: #FF461E;
      padding-top: 16px;
      padding-left: 26px;
      box-sizing: border-box;
      font-size: 16px;
      color: #fff;
      font-weight: normal;
      > div:nth-child(2) {
        font-size: 14px;
        margin-top: 10px;
        > img {
          width: 17px;
          vertical-align: middle;
          margin-left: 8px;
        }
      }
    }
    > .cont-list {
      padding: 0 15px;
      margin: 9px 0;
      box-sizing: border-box;
      > div {
        border-radius: 15px;
        background: #FFFFFF;
        width: 100%;
        min-height: 431px;
        .list-line {
          .line-f {
            font-size: 16px;
            padding-bottom: 4px;
          }
        }
      }
    }
  }
</style>