<template>
  <div class="details">
    <div class="content">
      <div class="swipe-box">
        <van-swipe touchable ref="imgChange">
          <van-swipe-item v-for="(image, index) in images" :key="index">
            <img v-lazy="image" @click="showImagePreview(image, index)" />
          </van-swipe-item>
        </van-swipe>
        <div class="back-box">
          <i class="iconfont icon-return" @click="goBack"></i>
        </div>
      </div>
      <!-- <div class="bannar vip-bannar" v-show="isVip"></div> -->
      <!-- <div class="bannar vip-bannar"></div> -->
      <div class="details-pic">
        <div class="details-pic-o">
          <div class="details-pic-o-pri">
            <div v-if="detailData.category_pid == 3">
              <span class="details-pic-o-pri-o">{{ (detailData.goods_price / detailData.hm_price).toFixed(4) }}</span>
              <span class="details-pic-o-pri-t">金米</span>
            </div>
            <div v-else>
              <span>￥</span>
              <span>{{ detailData.goods_price }}</span>
            </div>
            <div>原价{{ detailData.goods_market }}</div>
          </div>
          <!-- <div>
            已提{{ detailData.goods_sale_number }} / 仅剩{{
              detailData.goods_stock - detailData.goods_sale_number
            }}
          </div> -->
        </div>
        <div class="details-pic-w" v-if="detailData.category_pid == 3">
          金米当钱花<!--（1金米≈{{ detailData.hm_price }}元）-->
        </div>
        <div class="details-pic-w" v-else-if="detailData.category_pid == 17">
          0元免费送
        </div>
        <div class="details-pic-w" v-else>
          提货成功将赠与≈{{ Math.round(detailData.goods_currency_give_num * detailData.hm_price)
          }} 积分
        </div>
      </div>
      <!-- 有规格数组 -->
      <div class="information box" v-if="!noFormat">
        <!-- <div class="information-one">
          <div class="left">
            <p class="now"><span>￥</span>{{ format_obj.goods_price }}</p>
            <p class="before"><span>￥</span>{{ format_obj.goods_market }}</p>
          </div>
          <div class="rigth">爆卖{{ detailData.goods_sale_number }}件</div>
        </div> -->
        <div class="title">
          <p>{{ detailData.goods_title }}</p>
          <!-- <p>{{ detailData.goods_desc }}</p> -->
        </div>
      </div>
      <!-- 无规格商品 -->
      <div class="information box" v-else>
        <div class="title">
          <p>{{ detailData.goods_title }}</p>
          <!-- <p>{{ detailData.goods_desc }}</p> -->
        </div>
      </div>
      <div class="operation box" v-if="isSend && !isHide">
        <!-- 规格 -->
        <!-- <div
          class="operation-list"
           @click="showPopup('five')"
        > -->
        <div class="operation-list">
          <div class="list-left">
            <p class="left-one">规格</p>
            <p class="left-three">
              {{ format_obj.name }}
            </p>
          </div>
          <!-- <div class="list-right" v-if="!isHide">
            <img
              src="../../assets/shop/details/xiangqing_xiayj_h_icon.png"
              @click="showPopup('five')"
            />
          </div> -->
        </div>
        <!-- 发货 -->
        <div class="operation-list">
          <div class="list-left">
            <p class="left-one">运费</p>
            <p class="left-three">{{ detailData.goods_postage }}元</p>
          </div>
          <!-- <div class="list-right">
            <img
              src="../../assets/shop/details/xiangqing_xiayj_h_icon.png"
            />
          </div> -->
        </div>
      </div>

      <div class="detailsTitle">
        <div class="line"></div>
        <p>宝贝详情</p>
        <div class="line"></div>
      </div>
      <div class="richText" ref="market" v-html="detailData.goods_content">
        <!-- <img src="../../assets/shop/details/xq_two_img.png" /> -->
      </div>
      <!-- 弹出层区域 -->
      <!-- 立即购买弹窗 -->
      <van-popup
        v-model="showfour"
        position="bottom"
        @close="handleClose"
        round
      >
        <div class="goodsPopbox">
          <div class="goodsBox">
            <img class="goodsImg" :src="detailData.goods_img" />
            <div class="goodsPrice">
              <p>{{ detailData.goods_title }}</p>
              <!-- 有规格 -->
              <div class="price" v-if="!noFormat">
                <p class="now" v-if="detailData.category_pid == 3">{{ (format_obj.goods_price / detailData.hm_price).toFixed(4) }}<span>金米</span></p>
                <p class="now" v-else><span>￥</span>{{ format_obj.goods_price }}</p>
                <!-- <p class="before">￥{{ format_obj.goods_market }}</p> -->
              </div>
              <!-- 无规格 -->
              <div class="price" v-else>
                <p class="now" v-if="detailData.category_pid == 3">{{ (detailData.goods_price / detailData.hm_price).toFixed(4) }}<span>金米</span></p>
                <p class="now" v-else><span>￥</span>{{ detailData.goods_price }}</p>
                <!-- <p class="before">￥{{ detailData.goods_market }}</p> -->
              </div>
            </div>
          </div>
          <!-- 规格 -->
          <p class="title" v-if="!noFormat">选择规格:</p>
          <div class="selectBox" v-if="!noFormat">
            <div
              class="list"
              v-for="(item, index) in format_list"
              :key="index"
              @click="handleSelect(index)"
              :class="{ listActive: format_index == index }"
            >
              {{ item.name }}
            </div>
          </div>
          <div class="changeNum">
            <p>数量</p>
            <div class="right">
              <div class="Reduction" @click="reduction">-</div>
              <input
                type="text"
                v-model="num"
                class="number"
                readonly="readonly"
                v-if="this.detailData.category_pid == 17"
              />
              <input
                type="text"
                v-model="num"
                class="number"
                @input="handleBlur"
                @blur= "handleBlurT"
                v-else
              />
              <div class="add" @click="add">+</div>
            </div>
          </div>
          <div class="changeFee">
            <div>运费</div>
            <div v-if="detailData.category_pid == 3">{{ (detailData.goods_postage / detailData.hm_price).toFixed(4)}}金米</div>
            <div v-else>￥{{ detailData.goods_postage - 0 }}</div>
          </div>
          <!-- <div v-else class="changeNum"></div> -->
          <div @click="goPay()" class="payButton">{{ detailData.category_pid == 3 ? "立即兑换" : "立即购买" }}</div>
          <img
            src="../../assets/shop/details/xwu-img.png"
            class="close"
            @click="cancel('four')"
          />
        </div>
      </van-popup>
      <!-- 加入购物车弹窗 -->
      <van-popup
        v-model="showfive"
        position="bottom"
        @close="handleClose"
        round
      >
        <div class="goodsPopbox">
          <div class="goodsBox">
            <img class="goodsImg" :src="detailData.goods_img" />
            <div class="goodsPrice">
              <p>{{ detailData.goods_title }}</p>
              <!-- 有规格 -->
              <div class="price" v-if="!noFormat">
                <p class="now" v-if="detailData.category_pid == 3">{{ (format_obj.goods_price / detailData.hm_price).toFixed(4) }}<span>金米</span></p>
                <p class="now" v-else><span>￥</span>{{ format_obj.goods_price }}</p>
                <!-- <p class="before">￥{{ format_obj.goods_market }}</p> -->
              </div>
              <!-- 无规格 -->
              <div class="price" v-else>
                <p class="now" v-if="detailData.category_pid == 3">{{ (detailData.goods_price / detailData.hm_price).toFixed(4) }}<span>金米</span></p>
                <p class="now" v-else><span>￥</span>{{ detailData.goods_price }}</p>
                <!-- <p class="before">￥{{ detailData.goods_market }}</p> -->
              </div>
            </div>
          </div>
          <!-- 规格 -->
          <p class="title" v-if="isVip == false && noFormat == false">
            选择规格:
          </p>
          <div class="selectBox" v-if="isVip == false && noFormat == false">
            <div
              class="list"
              v-for="(item, index) in format_list"
              :key="index"
              @click="handleSelect(index)"
              :class="{ listActive: format_index == index }"
            >
              {{ item.name }}
            </div>
          </div>
          <div class="changeNum">
            <p>数量</p>
            <div class="right">
              <div class="Reduction" @click="reduction">-</div>
              <input
                type="text"
                v-model="num"
                class="number"
                @input="handleBlur"
              />
              <div class="add" @click="add">+</div>
            </div>
          </div>
          <div @click="addCart" class="payButton">加入购物车</div>
          <img
            src="../../assets/shop/details/xwu-img.png"
            class="close"
            @click="cancel('five')"
          />
        </div>
      </van-popup>
    </div>

    <div class="flexBox" v-show="bottomBoxShow">
      <div class="bottom-left" :class="{ vipLeft: isHide }">
        <div
          class="iconBox"
          @click="goCart"
          v-if="detailData.category_pid == 1"
        >
          <img src="../../assets/shop/details/xq_kf_icon.png" />
          <p>购物车</p>
        </div>
        <div class="iconBox" @click="goCustomer">
          <img src="../../assets/shop/details/xq_gwc_icon.png" />
          <p>联系客服</p>
        </div>
      </div>
      <div class="bottom-right">
        <div class="addButton button" @click="showPopup('five')" v-if="!isHide">
          加入购物车
        </div>
        <div
          class="buyButton button"
          @click="showPopup('four')"
          :class="{ vipRight: isHide }"
        >
          {{ detailData.category_pid == 3 ? "立即兑换" : "立即购买" }}
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import { ImagePreview } from "vant";
import {
  get_goods_details,
  add_shop_cart,
  get_group_list,
} from "@/http/api.js";
export default {
  name: "detail",
  inject: ["reload"],
  data() {
    return {
      images: [],
      puzzleArr: [],
      showone: false,
      showtwo: false,
      showthree: false,
      showfour: false,
      showfive: false,
      couponsactive: false,
      detailData: "",
      goods_id: "",
      bottomBoxShow: true,
      isVip: false,
      isBuyVip: false,
      isIntegral: false,
      isPuzzle: false,
      isHide: false,
      isSend: false,
      isBuySend: false,
      num: 1,
      isLogin: true,
      platform: this.$cookie.get("platform"),
      timer: null,
      noPuzzleArr: false,
      send: false,
      goods_give_num: 0,
      format_list: [],
      format_index: 0,
      format_obj: {
        //用作传递规格id时，没有规格商品的判断
        id: "000",
      },
      noFormat: false,
      categoryId: "",
      categoryIdVip: false, // 是否是乐购区
    };
  },
  mounted() {
    //获取登录态
    const tokenId = this.$cookie.get("token_id");
    if (tokenId && tokenId == undefined) {
      this.isLogin = false;
    };
  },
  created() {
    this.goods_id = this.$route.params.id;
    this.getDetail();
    // if (this.goods_id == 1) { this.isVip = true }; //新人礼包
    if (this.$route.query.type == 2) {
      this.isIntegral = true;
    } //积分商城
    if (this.$route.query.type == 3) {
      //拼团专区
      this.isPuzzle = true;
      this.getGroupList(); //获取拼团列表
    }
  },
  mounted() {
    //拼团倒计时定时器
    if (this.isPuzzle && this.noPuzzleArr == false) {
      this.timer = setInterval(() => {
        setTimeout(this.getGroupList());
      }, 1000 * 60);
    } else {
      this.noPuzzleArr = false;
    };
    
  },
  //当离开页面时，清除倒计时
  beforeDestroy() {
    clearInterval(this.timer);
    this.timer = null;
  },
  updated() {
    if (
      this.isVip ||
      this.isIntegral ||
      this.isPuzzle ||
      this.isSend ||
      this.categoryIdVip
    ) {
      this.isHide = true;
    } //部分隐藏
    //富文本img
    let arrList =  this.$refs.market.getElementsByTagName('img');
    for(var i=0; i< arrList.length; i++) {
      arrList[i].style.width = '100%';
      arrList[i].style.display = 'block';
    }
  },
  methods: {
    handleBlur(e) {
      if (this.num == "00" || this.num.substring(0, 1) == "0" || this.num == "") {
        this.num = "";
      } else {
        this.num = this.num.replace(/[^0-9]/g, "");
      }
      if (
        Number(this.num) >
        Number(this.detailData.goods_stock - this.detailData.goods_sale_number)
      ) {
        this.num =
          this.detailData.goods_stock - this.detailData.goods_sale_number;
      };
      
    },
    handleBlurT() {
      if(this.num == "") {
        this.num = 1;
      }
    },
    // 联系客服
    goCustomer() {
      // this.$toast("敬请期待");
      // return false;
      let url = `/customer?id=1`;
      this.$router.push({ path: url });
    },
    //获取拼团列表
    getGroupList() {
      let obj = {};
      obj.goods_id = this.goods_id;
      //获取商品详情
      this.$http.post(get_group_list, obj).then(({ data }) => {
        const { result } = data;
        if (data.code == "10000") {
          this.puzzleArr = result.splice(0, 3);
          //拼团数组倒计时开始 以下标为参数
          this.puzzleArr.forEach((item, index) => {
            //倒计时
            this.getTimeOut(index);
          });
        } else {
          //code不等10000 没有数据 清除定时器
          clearInterval(this.timer);
          this.timer = null;
        }
      });
    },
    //商品详情
    getDetail() {
      let obj = {};
      obj.goods_id = this.goods_id;
      //获取商品详情
      this.$http.post(get_goods_details, obj).then(({ data }) => {
        const { result } = data;
        //轮播图数组
        this.images = result.goods_banners;
        this.imagesLists = this.images;
        this.categoryId = data.result.category_pid;
        if (this.categoryId != 1) {
          this.categoryIdVip = true;
        }
        //赋值详情数组
        this.detailData = result;
        //新人礼包是否已购买
        if (result.goods_id == 1 && result.is_buy == 1) {
          this.isBuyVip = true;
        }
        // 是否超值礼包
        if (result.is_send == 1) {
          this.isSend = true;
          //超值礼包是否已购买
          if (result.is_buy == 1) {
            this.isBuySend = true;
          }
        }
        //积分商品
        if (result.category_type == 4) {
          this.isIntegral = true;
        }
        this.category_type = result.category_type; //商品分类
        //判断是否赠送积分
        this.goods_give_num = parseInt(result.goods_give_num);
        // //规格
        if (result.format_list.length > 0) {
          //规格数组
          this.format_list = result.format_list;
          this.format_obj = this.format_list[0];
        } else {
          this.noFormat = true;
        }
        //判断是否虚拟产品
        if (result.goods_type == 2) {
          this.isVip = true;
        }
      });
    },
    //显示预览图
    showImagePreview(images, index) {
      let _this = this;
      ImagePreview({
        images: this.images,
        showIndex: true,
        loop: true, //是否循环播放
        startPosition: index,
        closeOnPopstate: true,
        onChange(_index) {
          _this.$refs.imgChange.swipeTo(_index);
        },
      });
    },
    onChange(index) {
      this.index = index;
    },
    handleClose() {
      this.bottomBoxShow = true;
      this.num = 1;
    },
    //显示弹出层
    showPopup(type) {
      if(this.detailData.category_pid == 17 && this.detailData.buy_shop_num == 0) {
        this.$toast.fail("次数用完，请去分享");
        return false;
      }
      const str = "show" + type;
      this[str] = true;
      this.bottomBoxShow = false;
    },
    //关闭弹出层
    cancel(type) {
      const str = "show" + type;
      this[str] = false;
      this.bottomBoxShow = true;
      this.num = 1;
      this.format_index = 0;
    },
    //规格选择
    handleSelect(index) {
      this.format_index = index;
      this.format_obj = this.format_list[index];
    },
    couponsActive() {
      this.couponsactive = true;
    },
    goBack() {
      if (this.$platform == "ios") {
        window.webkit.messageHandlers.iosAction.postMessage("exit");
      } else if (this.$platform == "android") {
        // type=1是从搜索过来
        //  || this.$route.query.type == 2 || this.$route.query.type == 3
        if (this.$route.query.type == 1) {
          apps.exit();
        } else if(this.$route.query.type == 88) {
          this.$router.back();
        }else {
          apps.exit();
        }
        localStorage.removeItem("highIdx");
        localStorage.removeItem("zeroIdx");
      } else {
        this.$router.back();
      }
    },
    wait() {
      window.toast_txt("敬请期待！");
    },
    //跳转支付 立即购买
    goPay(type, index) {
      //未登录禁止点击
      if (this.isLogin == false) {
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("login");
        } else if (this.$platform == "android") {
          apps.gologin();
        }
        return false;
      }
      if(this.format_obj.goods_stock == 0){
        this.$toast.fail('库存不足');
        return;
      }
      //如果是积分商品/超值礼包，return出去 显示弹出层选择数量/规格
      if (type == 1) {
        this.showPopup("four");
        return false;
      }
      //判断是否参团
      if (this.isPuzzle && type == 2) {
        this.$router.push({
          path: "/pay",
          query: {
            goods_id: this.goods_id,
            immediately: 1, //立即购买
            isIntegral: this.isIntegral, //积分购买
            num: this.num, //商品数量
            group_id: this.puzzleArr[index].id,
          },
        });
        return false;
      }
      this.$router.push({
        path: "/pay",
        query: {
          goods_id: this.goods_id,
          immediately: 1, //立即购买
          isIntegral: this.isIntegral, //积分购买
          num: this.send ? 1 : this.num, //商品数量
          format_id: this.format_obj.id, //规格id
          is_send: this.isSend, //是否超值商品
          categoryId: this.categoryId, // 是否是消费专区
        },
      });
    },
    //跳转购物车
    goCart() {
      //未登录禁止点击
      // if (this.isLogin == false) {
      //   if (this.$platform == "ios") {
      //     window.webkit.messageHandlers.iosAction.postMessage("login");
      //   } else if (this.$platform == "android") {
      //     apps.gologin();
      //   }
      //   return false;
      // } else {
        if (this.$platform == "android") {
          apps.gocat();
        } else {
          this.$router.push('/cart');
        }
      // }
      // this.$router.push('/cart');
    },
    // 添加购物车
    addCart() {
      //未登录禁止点击
      if (this.isLogin == false) {
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("login");
        } else if (this.$platform == "android") {
          apps.gologin();
        }
        return false;
      }
      let obj = {};
      obj.num = this.num;
      obj.goods_id = this.goods_id;
      obj.format_id = this.format_obj.id;
      this.$http.post(add_shop_cart, obj).then(({ data }) => {
        if (data.code == 10000) {
          this.$toast.success(data.message);
        }
      });
      this.num = 1;
      this.showfive = false;
    },
    reduction() {
      if(this.detailData.category_pid == 17) {
        this.$toast.fail("仅能购买数量为1");
        return false;
      };
      if (this.num <= 1 || this.num == 0) {
        this.num = 1;
        return false;
      };
      this.num = Number(this.num) - 1;
    },
    add() {
      if(this.detailData.category_pid == 17) {
        this.$toast.fail("仅能购买数量为1");
        return false;
      };
      this.num = Number(this.num) + 1;
      if (
        Number(this.num) >
        Number(this.detailData.goods_stock - this.detailData.goods_sale_number)
      ) {
        this.num =
          this.detailData.goods_stock - this.detailData.goods_sale_number;
      };
    },
    //计算倒计时
    getTimeOut(index) {
      if (Number(this.puzzleArr[index].end_time) > 0) {
        const time = this.puzzleArr[index].end_time;
        let day = parseInt(time / 60 / 60 / 24);
        let hr = parseInt((time / 60 / 60) % 24);
        let min = parseInt((time / 60) % 60);
        min = min + 1;
        // let sec = parseInt(time % 60)
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
        // this.orderTime = `${day}天${hr}时${min}分`;
        this.puzzleArr[index].day = day;
        this.puzzleArr[index].hr = hr;
        this.puzzleArr[index].min = min;
      } else {
        clearInterval(this.timer);
        this.timer = null;
      }
    },
  },
};
</script>
<style lang="scss" scoped>
.flexBox {
  z-index: 2;

  position: absolute;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 60px;
  bottom: 0;
  padding: 10px;
  box-sizing: border-box;
  width: 100%;
  background-color: #f7f7f7;
  .bottom-left {
    flex: 1;
    display: flex;
    align-items: center;
    // justify-content: space-between;
    box-sizing: border-box;
    .iconBox {
      display: flex;
      flex-direction: column;
      align-items: center;
      img {
        width: 17px;
        height: 17px;
      }
      p {
        font-size: 12px;
        color: #000000;
      }
    }
    .iconBox:last-child {
      margin-left: 20px;
    }
  }
  .bottom-right {
    flex: 2;
    display: flex;
    align-items: center;
    // padding-left: 20px;
    .button {
      width: 116px;
      height: 48px;
      border-radius: 5px;
      text-align: center;
      line-height: 48px;
      color: white;
      font-size: 16px;
    }
    .addButton {
      position: relative;
      left: 5px;
      background-color: #000000;
    }
    .buyButton {
      background: #ff461e;
    }
    .vipRight {
      width: 100%;
    }
  }
  .vipLeft {
    // flex: none;
    // width: 100px;
    // margin-right: 10px;
  }

  .sendBtn {
    flex: 1;
    .button {
      width: 100%;
    }
  }
  .haveBuy {
    background: none;
    background-color: #d0d0d0;
  }
}
/deep/ .van-overlay {
  z-index: 2;
}
.content {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 50px;
  overflow: auto;
  background: #f7f5f6;
  font-family: "SourceHanSansCN";
  -webkit-overflow-scrolling: touch;
  height: 100%;
  z-index: 2;
  .swipe-box {
    // height: 332px;
    img {
      width: 100%;
      height: 100%;
      // max-width: 100%;
    }
    .back-box {
      position: absolute;
      top: 25px;
      left: 0;
      width: 64px;
      height: 31px;
      border-radius: 0 31px 31px 0;
      background-color: #000000;
      opacity: 0.35;
      i {
        display: flex;
        justify-content: center;
        align-items: center;
        line-height: 31px;
        color: white;
      }
    }
  }
  .bannar {
    height: 48px;
    overflow: hidden;
    background: url("../../assets/shop/details/xq_biaoti_logobg.png") no-repeat;
    background-size: 100% 100%;
    .title {
      float: right;
      // margin-top: 15px;
      box-sizing: border-box;
      width: 170px;
      height: 20px;
      line-height: 20px;
      border-right: 1px solid #808ba5;
      font-size: 17px;
      font-weight: bold;
      text-align: center;
    }
    .countdown {
      float: right;
      width: 118px;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-content: center;
      justify-content: space-around;
      .countdown-title {
        font-size: 10px;
        color: #313131;
        text-align: center;
      }
      .countdown-show {
        display: flex;
        justify-content: center;
        align-content: center;
        padding-bottom: 4px;
        span {
          font-size: 18px;
          // font-weight: bolder;
          margin: 0 3px;
        }
        .block {
          width: 23px;
          height: 23px;
          border-radius: 6px;
          color: #ffffff;
          font-size: 12px;
          font-weight: bold;
          background-color: #313131;
          text-align: center;
          line-height: 23px;
        }
      }
    }
  }
  .vip-bannar {
    background: url("../../assets/shop/details/spxiangq_bg.png") no-repeat;
    background-size: 100% 100%;
  }
  .box {
    // margin-top: 10px;
    background-color: #ffffff;
    // border-radius: 12px;
    // margin: 0 9px;
    // margin-top: 10px;
    padding: 10px 15px;
    box-sizing: border-box;
  }
  .details-pic {
    height: 104px;
    background: linear-gradient(86deg, #ff4a21 0%, #ff4a21 49%, #f67b44 100%);
    border-radius: 15px 15px 0px 0px;
    margin-top: -20px;
    position: relative;
    padding: 0 15px;
    box-sizing: border-box;
    > .details-pic-o {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      color: #fff;
      font-weight: bold;
      padding: 8px 0 14px;
      height: 30px;
      > .details-pic-o-pri {
        display: flex;
        align-items: flex-end;
        > div:nth-child(1) {
          font-size: 24px;
          > span:nth-child(1) {
            font-size: 15px;
          }
          > span.details-pic-o-pri-o {
            font-size: 24px;
          }
          > .details-pic-o-pri-t {
            font-size: 15px;
          }
        }
        > div:nth-child(2) {
          margin-left: 10px;
          font-size: 14px;
          font-family: "Source Han Sans CN";
          font-weight: bold;
          color: #FECFCF;
          text-decoration:line-through;
        }
      }

      > div:nth-child(2) {
        font-size: 14px;
      }
    }
    > .details-pic-w {
      height: 36px;
      background: #ffffff;
      border-radius: 5px;
      font-family: "Source Han Sans CN";
      font-weight: bold;
      color: #ff4a21;
      display: flex;
      padding-left: 11px;
      box-sizing: border-box;
      align-items: center;
      font-size: 14px;
    }
  }
  .information {
    // height: 70px;
    display: flex;
    flex-direction: column;
    .information-one {
      height: 25px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      .left {
        display: flex;
        align-items: center;
        position: relative;

        .now {
          font-size: 25px;
          font-weight: bolder;
          color: #ff461e;
          span {
            font-size: 18px;
          }
        }
        .before {
          margin-left: 10px;
          padding-top: 5px;
          font-size: 15px;
          color: #999;
          text-decoration: line-through;
          span {
            font-size: 14px;
          }
        }
      }
    }
    .information-two {
      height: 23px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 5px;
      color: #902d28;
      .left {
        display: flex;
        align-items: center;
        font-size: 12px;
        .use {
          margin-top: 2px;
          width: 124px;
          text-align: center;
          line-height: 23px;
          border-radius: 12px;
          background: #fedce4;
          border: 1 solid #fbb0c2;
        }
        .receive {
          width: 91px;
          text-align: center;
          line-height: 23px;
          border-radius: 12px;
          background-color: #ffede0;
          border: 1px solid #cc9271;
          margin-left: 7px;
        }
        .price {
          font-weight: bold;
          font-size: 14px;
          span {
            font-weight: normal;
            font-size: 13px;
          }
        }
      }
      .right {
        display: flex;
        align-items: center;
        img {
          margin-left: 5px;
          height: 11px;
          width: 6px;
        }
      }
    }
    .title {
      display: flex;
      flex-direction: column;
      // height: 70px;
      // margin-top: 10px;
      font-size: 16px;
      font-family: Source Han Sans CN;
      font-weight: bold;
      color: #000000;

      p {
        width: 325px;
        text-overflow: -o-ellipsis-lastline;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
      }
      p:last-child {
        font-size: 16px;
        font-weight: bold;
      }
    }
  }
  .vipStyle {
    justify-content: space-around;
    height: 109px;
  }
  .operation {
    min-height: 90px;
    display: flex;
    flex-direction: column;
    .operation-list {
      display: flex;
      justify-content: space-between;
      height: 30px;
      line-height: 30px;
      text-align: center;
      font-size: 14px;
      color: #000000;
      .list-left {
        display: flex;
        align-items: center;
        .left-one {
          color: #8c8c8c;
        }
        .left-three {
          margin-left: 40px;
          color: #000000;
          font-size: 14px;
          // font-weight: bold;
        }
      }
      .list-right {
        width: 5%;
        img {
          width: 6px;
          height: 11px;
        }
      }
      .activity {
        margin-left: 14px;
        width: 36px;
        height: 16px;
        line-height: 16px;
        border-radius: 7px;
        background-color: #902d28;
        text-align: center;
        color: #ffffff;
        font-size: 10px;
      }
    }
    .mt {
      margin-top: 10px;
    }
  }
  .puzzle {
    min-height: 175px;
    display: flex;
    flex-direction: column;
    // align-items: center;
    .puzzleTitle {
      font-size: 15px;
      font-weight: bold;
      color: #313131;
    }
    .puzzleList {
      height: 70px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-family: "SourceHanSansCN";
      .puzzleMain {
        display: flex;
        align-items: center;
        .infoImg {
          width: 34px;
          height: 34px;
          border-radius: 50%;
          // overflow: hidden;
          img {
            width: 100%;
            height: 100%;
          }
        }
        .puzzleDetail {
          margin-left: 10px;
          display: flex;
          flex-direction: column;
          .detailTitle {
            color: #000000;
            font-size: 14px;
          }
          .detailTimeOut {
            font-size: 12px;
            color: #8c8c8c;
          }
        }
      }
      .puzzleButton {
        width: 74px;
        height: 26px;
        background: linear-gradient(84deg, #54e0b1 0%, #198a6f 100%);
        border-radius: 12px;
        font-size: 14px;
        color: #ffffff;
        line-height: 26px;
        text-align: center;
      }
      .redBtn {
        background: #f5f5f5;
        color: black;
      }
    }
    span {
      color: #902d28;
    }
  }
  .evaluation {
    height: 239px;
    .titleBox {
      display: flex;
      justify-content: space-between;
      align-items: center;
      .select {
        color: #313131;
        font-size: 15px;
        font-weight: bold;
      }
      .more {
        display: flex;
        align-items: center;
        font-size: 12px;
        color: #902d28;
        p {
          margin-right: 5px;
        }
        img {
          width: 6px;
          height: 11px;
        }
      }
    }
    .showArea {
      margin-top: 10px;
      .list {
        .list-top {
          display: flex;
          align-items: center;
          .icon {
            width: 31px;
            height: 31px;
            border-radius: 12px;
            background-color: #902d28;
          }
          .user {
            margin-left: 10px;
            display: flex;
            flex-direction: column;
            // align-items: center;
            justify-content: center;
            .name {
              font-size: 14px;
              color: #000000;
            }
            .day {
              font-size: 12px;
              color: #8c8c8c;
            }
          }
        }
        .list-bottom {
          margin-top: 15px;
          font-size: 14px;
        }
      }
    }
  }
  .detailsTitle {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 12px 0;
    .line {
      width: 42px;
      height: 1px;
      background-color: #e9e6e9;
    }
    p {
      color: #524f52;
      font-size: 12px;
      margin: 0 10px;
    }
  }
  .richText {
    width: 100%;
    min-height: 100px;
    // padding: 0 10px;
    box-sizing: border-box;
    overflow: hidden;
    margin-bottom: 60px;
  }
  // 弹窗样式
  .popbox {
    padding: 0 15px;
    box-sizing: border-box;
    font-family: "蘋方-繁";
    position: relative;
    font-size: 16px;
    .header {
      height: 70px;
      font-size: 16px;
      // font-weight: 500;
      line-height: 70px;
      color: #00d8a5;
      text-align: center;
      border-bottom: 1px solid #e8e8e8;
    }
    .title {
      height: 82px;
      display: flex;
      align-items: center;
      img {
        width: 27px;
        height: 22px;
      }
      p {
        margin-left: 10px;
        font-size: 16px;
        color: #6d6d6d;
      }
    }
    .list {
      display: flex;
      width: 345px;
      height: 95px;
      margin-top: 15px;
      background: url("../../assets/shop/chnag-img.png") no-repeat;
      background-size: 100% 100%;
      .list-left {
        width: 120px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        .price {
          font-size: 30px;
        }
        .limit {
          font-size: 16px;
        }
      }
      .list-right {
        display: flex;
        flex-direction: column;
        justify-content: space-around;
        color: #a8a8a8;
        font-size: 14px;
        padding: 10px 0;
        .name {
          color: #2f2f2f;
          font-size: 18px;
          font-weight: 500;
        }
        p:last-child {
          font-size: 12px;
        }
      }
      .button {
        position: relative;
        top: 37px;
        margin-left: 20px;
        width: 64px;
        height: 21px;
        border: 1px solid #00d6a3;
        border-radius: 11px;
        font-size: 12px;
        line-height: 21px;
        color: #00d6a3;
        text-align: center;
      }
      .disable {
        border: 1px solid #a8a8a8;
        color: #a8a8a8;
      }
    }
    .textArea {
      display: flex;
      flex-direction: column;
      margin-top: 30px;
      font-size: 16px;
      color: #393939;
      .text-list {
        display: flex;
        align-items: center;
        margin-top: 15px;
        img {
          width: 18px;
        }
        p {
          margin-left: 10px;
        }
        .list-top {
          display: flex;
          align-items: center;
        }
        .list-bottom {
          margin-top: 15px;
          margin-left: 30px;
          color: #bdbdbd;
          font-size: 14px;
        }
      }
      .list-flex {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    .close {
      position: absolute;
      top: 15px;
      right: 15px;
    }
    div:first-child {
      margin-top: 0;
    }
  }
  .goodsPopbox {
    min-height: 290px;
    position: relative;
    display: flex;
    flex-direction: column;
    padding: 0 15px;
    box-sizing: border-box;
    background-color: #ffffff;
    z-index: 4;
    .goodsBox {
      margin-top: 30px;
      display: flex;
      align-items: center;
      height: 70px;
      .goodsImg {
        width: 75px;
        height: 70px;
      }
      .goodsPrice {
        margin-left: 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        p {
          color: #3b3b3b;
          font-size: 17px;
          width: 200px;
          font-size: 0.453333rem;
          overflow: hidden;
          text-overflow: ellipsis;
          display: -webkit-box;
          -webkit-line-clamp: 1;
          -webkit-box-orient: vertical;
        }
        .price {
          margin-top: 5px;
          display: flex;
          align-items: center;
          position: relative;
          .now {
            font-size: 25px;
            font-weight: bolder;
            color: #ff461e;
            span {
              font-size: 20px;
            }
          }
          .before {
            margin-left: 5px;
            padding-top: 5px;
            font-size: 15px;
            color: #333333;
            text-decoration: line-through;
          }
          .rid {
            width: 40px;
            height: 1px;
            background-color: #902d28;
            position: absolute;
            right: 0;
            bottom: 13px;
          }
        }
      }
    }
    // 规格
    .title {
      margin-top: 30px;
      color: #393939;
      font-size: 16px;
    }
    .selectBox {
      margin-top: 10px;
      display: flex;
      align-items: center;
      // justify-content: space-between;
      flex-wrap: wrap;
      align-content: flex-start;

      .list {
        margin-top: 5px;
        margin-right: 15px;
        font-family: "SourceHanSansCN-Normal";
        width: 155px;
        height: 29px;
        line-height: 29px;
        text-align: center;
        background-color: #f3f3f3;
        font-size: 12px;
        color: #7d7b7b;
        border-radius: 4px;
        text-overflow: -o-ellipsis-lastline;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 1; //行数
        -webkit-box-orient: vertical;
      }
      .listActive {
        background: #fef2f5;
        color: #902d28;
      }
    }
    .changeNum {
      margin-top: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      p {
        color: #000;
        font-size: 14px;
        font-weight: bold;
      }
      .right {
        display: flex;
        // height: 30px;
        border-radius: 10px;
        text-align: center;
        line-height: 30px;
        font-size: 14px;
        .number {
          width: 32px;
          text-align: center;
          background: #f1f1f1;
          border: none;
          border-radius: 4px;
          // 清除input样式
          -webkit-appearance: none;
          -moz-appearance: none;
          outline: 0;
          margin: 0 2px;
        }

        .Reduction {
          width: 32px;
          height: 32px;
          background: #f7f7f7;
          border-radius: 2px 0px 0px 2px;
        }
        .add {
          width: 32px;
          height: 32px;
          background: #f7f7f7;
          border-radius: 2px 0px 0px 2px;
        }
      }
    }
    .changeFee {
      margin-top: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 14px;
      > div:nth-child(1) {
        font-weight: bold;
      }
      > div:nth-child(2) {
        margin-right: 20px;
      }
    }
    .payButton {
      margin-top: 50px;
      margin-bottom: 20px;
      width: 345px;
      height: 40px;
      background: #ff461e;
      border-radius: 6px;
      color: #ffffff;
      font-size: 16px;
      text-align: center;
      line-height: 40px;
    }
    .close {
      position: absolute;
      top: 15px;
      right: 15px;
    }
  }
}
/deep/ .van-swipe-item {
  height: 376px;
}
/deep/ .van-image-preview__swipe-item {
  height: 100%;
}
</style>