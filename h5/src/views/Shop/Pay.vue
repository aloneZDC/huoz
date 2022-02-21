<template>
  <div class="pay">
    <!-- <div class="header">
      <i class="iconfont icon-return" @click="goBack"></i>
      <p>确认订单</p>
    </div> -->
    <TopHeader :info="info"></TopHeader>
    <div class="content">
      <div
        class="address box"
        @click="$router.push('addAddress')"
        v-if="$route.query.categoryId != 2022 && noAddressData"
      >
        <img src="../../assets/shop/jiant-imhg.png" />
        <div class="address-min">
          <div class="information">
            <p class="name">收货地址</p>
            <!-- <p class="phone"></p> -->
          </div>
          <div class="addressText noAddressTxt">请选择收货地址</div>
        </div>
        <img
          src="../../assets/shop/details//xiangqing_xiayj_h_icon.png"
          class="goAddress"
        />
      </div>
      <div class="address box" v-else-if="$route.query.categoryId == 2022">
        <img src="../../assets/shop/jiant-imhg.png" />
        <div class="address-min">
          <div class="information">
            <p class="name">无需发货！</p>
            <!-- <p class="phone"></p> -->
          </div>
          <div class="addressText noAddressTxt">
            确认收货即可赠与！
          </div>
        </div>
        <img src="" class="goAddress" />
      </div>
      <!-- 有默认收货地址 -->
      <div
        class="address box"
        @click="$router.push('address')"
        v-else-if="!noAddressData && $route.query.categoryId != 2022"
      >
        <img src="../../assets/shop/jiant-imhg.png" />
        <div class="address-min">
          <div class="information">
            <p class="name">{{ address.sa_name }}</p>
            <p class="phone">{{ address.sa_mobile }}</p>
          </div>
          <div class="addressText">{{ address.sa_address }}</div>
        </div>
        <img
          src="../../assets/shop/details//xiangqing_xiayj_h_icon.png"
          class="goAddress"
        />
      </div>
      <!-- 商品区域 -->
      <div class="goodsList box">
        <div class="list-top" v-for="(item, index) in goodsList" :key="index">
          <img :src="item.goods.goods_img" class="list-top-left" />
          <div class="list-top-right">
            <p class="name">
              {{ item.goods.goods_title }}
            </p>
            <p
              class="specifications"
              v-if="noFormat == false && item.goods.format"
            >
              {{ item.goods.format.name }}
            </p>
            <div class="goodsdeatil">
              <p class="price" v-if="$route.query.categoryId == 3">{{ (item.goods.goods_price / hmPrice).toFixed(4) }}<span>金米</span></p>
              <p class="price" v-else><span>￥</span>{{ item.goods.goods_price }}</p>
              <p class="number">x{{ item.sc_num }}</p>
            </div>
          </div>
        </div>
        <div class="list-bottom">
          <div class="additionalList">
            <p>商品总价</p>
            <p>{{ $route.query.categoryId == 3 ? `${(total / hmPrice).toFixed(4)}金米` : `￥${total}` }}</p>
          </div>
          <div class="additionalList">
            <p>运费</p>
            <p>{{ $route.query.categoryId == 3 ? `${(postage_total / hmPrice).toFixed(4)}金米` : `￥${postage_total}` }}</p>
          </div>
          <div class="additionalList">
            <p>订单备注</p>
            <input
              type="text"
              v-model="ordernote"
              placeholder="特殊需求请联系卖家(选填)"
            />
          </div>
          <p class="bottomList">
            <span>共{{ num }}件，</span>合计:<span
              v-if="$route.query.categoryId == 3">{{
                (goods_total / hmPrice).toFixed(4)
              }}{{ $route.query.categoryId != 3 ? "赠与收益" : "金米" }}</span
            >
            <span v-else-if="$route.query.categoryId == 17">￥{{ goods_total }}</span>
            <span
              v-else>￥{{ goods_total }}≈{{
                (goods_total / hmPrice).toFixed(4)
              }}{{ $route.query.categoryId != 3 ? "赠与收益" : "金米" }}</span
            >
          </p>
        </div>
      </div>

      <!-- 支付方式区域 -->
      <div class="payMethod box" v-if="isIntegral == false && isSend == false">
        <!-- 支付方式标题 -->
        <!-- <div class="payTitle">——— &nbsp;选择付款方式&nbsp; ———</div> -->
        <div class="paytitleImg">选择付款方式</div>
        <!-- 置换区 只有金米支付 -->
        <div v-if="$route.query.categoryId == 3">
          <div
            class="payList"
            v-for="(item, index) in payMethodsArr"
            :key="index"
          >
            <img src="../../assets/shop/fire_img.png" class="payicon" />
            <span>{{ item.name }}(可用{{ item.money }})</span>
            <img src="../../assets/shop/cart/wei-img.png" class="active" />
          </div>
        </div>
        <!-- 非置换区支付方式 -->
        <div v-else>
          <div
            class="payList"
            v-for="(item, index) in payMethodsArr"
            :key="index"
          >
            <img :src="item.img" class="payicon" />
            <span>{{ item.name }}</span>
            <img
              src="../../assets/shop/cart/wei-img.png"
              class="active"
              @click="payMethodsActive(index)"
              v-if="item.active"
            />
            <img
              src="../../assets/shop/cart/dhi-img.png"
              class="active"
              @click="payMethodsActive(index)"
              v-else
            />
          </div>
        </div>

        <!-- <div class="combination" v-show="payMethodsArr[2].active">已选606.00购物￥+200.00积分支付</div> -->
      </div>
      <!-- 提示语区域 -->
      <!-- <div class="prompt">
        <p>购买成功后预计可获“{{estimate_num}}分享积分”</p>
      </div> -->
    </div>
    <!-- 实际付款区域 -->
    <div class="payBox">
      <!-- 旧版本底部支付按钮 -->
      <!-- 提示语 -->
      <!-- <div class="prompt">
        <p>购买成功后预计可获“{{ estimate_num }}分享积分”</p>
      </div> -->
      <div class="payMain">
        <p class="paytitle">
          <span>共{{ num }}件，</span>合计:&nbsp;
        </p>
        <p class="price">{{ $route.query.categoryId == 3 ? `${(goods_total / hmPrice).toFixed(4)}金米` : `￥${goods_total}` }}</p>
        <div class="button" @click="goPay">立即支付</div>
      </div>
      <!-- 新版本提交订单 -->
      <!-- <div class="payBtn" @click="goPay">提交订单</div> -->
    </div>
    <!-- 输入密码弹窗 -->
    <KeyBord
      ref="keyBord"
      @data-password="buttonNum"
      @handleShow="handleShow"
    ></KeyBord>
    <!-- 放弃付款弹窗 -->
    <div class="popup" v-show="toastShow">
      <div class="popup-box">
        <div class="popup-title">
          <p>确认放弃付款吗?</p>
          <p class="title">超过支付时效后,订单会被取消哦!</p>
        </div>

        <div class="button-grounp">
          <div class="button button-cancel" @click="goOrder">确定离开</div>
          <div class="button button-determine" @click="showKeyArea">
            继续支付
          </div>
        </div>
      </div>
    </div>
    <!-- 遮罩层  填写收货人信息-->
    <van-overlay :show="show">
      <div class="wrapper">
        <div class="block">
          <img
            src="../../assets/shop/my/cwu-img.png"
            @click="show = false"
            class="close"
          />
          <div class="title">填写提货人信息</div>
          <input type="text" v-model="username" placeholder="输入提货人姓名" />
          <input type="text" v-model="phone" placeholder="输入提货人电话" />
          <input
            type="text"
            v-model="mentionNote"
            placeholder="输入备注(选填)"
            maxlength="50"
          />
          <div class="button" @click="show = false">确定</div>
        </div>
      </div>
    </van-overlay>
  </div>
</template>
<script>
import {
  get_shop_cart,
  pay_type,
  get_default,
  submit_order,
  pay_orders,
} from "@/http/api.js";
export default {
  data() {
    return {
      info: {
        isBack: true,
        title: "确认订单",
        exit: true,
      },
      goodsList: [],
      address: {},
      postage_total: 0,
      num: 0,
      detailData: "", //商品详情
      discount: 0,
      goods_total: "",
      hmPrice: "", // 火米比例
      //代金￥数组
      vouchersArr: [
        {
          active: true,
          text: "可用2555.25代金￥抵用50.26元",
        },
        {
          active: false,
          text: "可用2555.25代金￥抵用50.26元",
        },
      ],
      //支付方式
      payMethodsArr: [
        {
          id: 1,
          active: true,
          name: "余额支付",
          // img: require("../../assets/shop/gwq.png"),
          img: require("../../assets/shop/fire_img.png"),
          is_recommend: 1,
        },
        {
          id: 3,
          active: false,
          name: "微信支付",
          img: require("../../assets/shop/cart/vxzf_icon.png"),
          is_recommend: 0,
        },
        {
          id: 4,
          active: false,
          name: "支付宝",
          img: require("../../assets/shop/cart/zfbzf_icon.png"),
          is_recommend: 0,
        },
      ],
      toastShow: false,
      goods_id: "",
      active_sc_id: "", //购物车表id
      // active: true,
      equal_total: 0, //代金￥可用额度
      djq_money: 0, //代金￥剩余额度
      jf_paytype: {}, //积分
      money: 0, //余额
      total: 0, //总价格 优惠￥后
      original: 0, //总价格 优惠￥前
      jf_total: 0,
      noAddressData: false, //没有默认收货地址
      isStorage: false,
      ids: "",
      paytypeid: "",
      isVip: false,
      isSend: false,
      isIntegral: false, //是否积分购买
      moreOrders: false, //是否多订单支付
      isHide: false,
      immediately: false,
      group_id: null,
      goGroup: false,
      format_id: "",
      noFormat: false,
      estimate_num: 0,
      active: 0,
      show: false,
      username: "",
      phone: "",
      note: "",
      activeExtract: "",
      ordernote: "",
      mentionNote: "",
      oneself: true,
    };
  },
  created() {
    //判断是否为立即购买
    if (this.$route.query.immediately && this.$route.query.immediately == 1) {
      this.immediately = true;
      //是否是参团
      if (this.$route.query.group_id) {
        this.group_id = this.$route.query.group_id;
        this.goGroup = true;
      }
      this.goods_id = this.$route.query.goods_id;
      this.format_id = this.$route.query.format_id;
      //商品没有规格数组 传递参数做判断
      if (this.format_id == "000") {
        this.noFormat = true;
      }
      this.isSend = JSON.parse(this.$route.query.is_send); //是否超值礼包
    } else {
      this.active_sc_id = this.$route.query.sc_id;
    }
    //是否积分购买 获取不同的支付方式
    if (this.$route.query.isIntegral) {
      this.isIntegral = JSON.parse(this.$route.query.isIntegral);
    }
    if (this.isIntegral == true) {
      this.getPayType(2);
    } else {
      if (this.$route.query.categoryId == 3) {
        this.getPayType(2);
      } else if(this.$route.query.categoryId == 16){
        this.getPayType(16);
      }else if(this.$route.query.categoryId == 17){
        this.getPayType(17);
      }else {
        this.getPayType(1);
      }
    }
    //新人礼包
    // this.isVip = JSON.parse(this.$route.query.isVip);
    if (this.goods_id == 1) {
      this.isVip = true;
    }
    //新人礼包、积分商品 部分隐藏判断
    if (this.isVip || this.isIntegral) {
      this.isHide = true;
    }
    //自提点选择
    const storage = JSON.parse(localStorage.getItem("activeExtract"));
    if (storage) {
      this.activeExtract = storage;
    }
    this.getAddress();
    this.getShopCart();
  },
  mounted() {
    if (window.history && window.history.pushState) {
      history.pushState(null, null, document.URL);
      window.addEventListener("popstate", this.backChange, false); //false阻止默认事件
    }
  },
  destroyed: function () {
    window.removeEventListener("popstate", this.backChange, false); //false阻止默认事件
  },
  methods: {
    XHR() {
		// 参考自 <a target=_blank href="http://www.cnblogs.com/gaojun/archive/2012/08/11/2633891.html">http://www.cnblogs.com/gaojun/archive/2012/08/11/2633891.html</a>
		var xhr;
		try {xhr = new XMLHttpRequest();}
		catch(e) {
			var IEXHRVers =["Msxml3.XMLHTTP","Msxml2.XMLHTTP","Microsoft.XMLHTTP"];
			for (var i=0,len=IEXHRVers.length;i< len;i++) {
				try {xhr = new ActiveXObject(IEXHRVers[i]);}
				catch(e) {continue;}
			}
		}
		return xhr;
	  },
    backChange() {
      this.goBack();
    },
    goBack() {
      if (this.$platform == "ios") {
        window.webkit.messageHandlers.iosAction.postMessage("exit");
      } else if (this.$platform == "android") {
        this.$router.back();
      } else {
        this.$router.back();
      }
    },
    //获取收货地址
    getAddress() {
      //尝试获取本地缓存收货地址 （选中的地址）
      const storage = JSON.parse(localStorage.getItem("activeAdress"));
      if (storage) {
        this.address = storage;
        this.address.sa_address = this.address.address;
        this.isStorage = true;
        localStorage.clear("activeAdress");
      } else {
        //获取默认收货地址
        this.$http.post(get_default).then(({ data }) => {
          const { result } = data;
          if (data.code == 10000) {
            this.address = result;
            this.address.sa_address = result.pca + data.result.sa_address;
          } else {
            //没有默认收货地址
            this.noAddressData = true;
          }
        });
      }
    },
    //获取购物车信息 获取抵扣总价
    async getShopCart() {
      let obj = {};
      //立即购买参数不同
      if (this.immediately) {
        obj.goods_id = this.goods_id;
        obj.num = this.$route.query.num;
        obj.format_id = this.format_id;
      } else {
        obj.sc_id = this.active_sc_id;
      }
      await this.$http.post(get_shop_cart, obj).then(({ data }) => {
        const { result } = data;
        this.goodsList = result.list;
        this.equal_total = result.equal_total; //代金￥可用额度
        this.total = result.total; //总价格
        this.goods_total = result.goods_total;
        this.num = result.num; //总数量
        this.postage_total = result.postage_total; //总运费
        this.discount = result.discount;
        this.hmPrice = result.hm_price;
        this.jf_total =
          result.list[0].goods.goods_price + result.list[0].goods.goods_postage;
        this.original = Number(result.total) + Number(result.equal_total); //总价格 优惠前
        this.estimate_num = result.estimate_num;
        if (this.immediately && result.list[0].goods.goods_type == 2) {
          this.oneself = false;
        }
      });
    },
    //获取支付方式数据
    getPayType(type) {
      console.log(type);
      let obj = {};
      obj.type = type;
      this.$http.post(pay_type, obj).then(({ data }) => {
        const { result } = data;
        //代金￥余额
        this.djq_money = result.djq_money;
        if (type == 2) {
          //积分
          this.jf_paytype = result.pay_type[0];
          // 置换专区 type=3
          if (this.$route.query.categoryId == 3) {
            let obj = {};
            this.payMethodsArr = [];
            result.pay_type.forEach((item,index) =>{
              obj = {
                id: item.id,
                name: item.name,
                active: true,
                img: require("../../assets/shop/gwq.png"),
                is_recommend: item.is_recommend,
                money: item.money
              };
              this.payMethodsArr.push(obj);
            });
          }
        } else {
          // 置换专区 type=3
          if (this.$route.query.categoryId == 3) {
            let obj = {};
            this.payMethodsArr = [];
            result.pay_type.forEach((item,index) =>{
              obj = {
                id: item.id,
                name: item.name,
                active: true,
                img: require("../../assets/shop/gwq.png"),
                is_recommend: item.is_recommend,
                money: item.money
              };
              this.payMethodsArr.push(obj);
            })
          }else if(this.$route.query.categoryId == 17) {
            
            let obj = {};
            this.payMethodsArr = [];
            result.pay_type.forEach((item,index) =>{
              let obj = {
                id: item.id,
                name: item.name,
                is_recommend: item.is_recommend,
              };
              if(item.id == 3){
                obj.img = require("../../assets/shop/cart/vxzf_icon.png");
                obj.active = true;
              }else if(item.id == 4){
                obj.img = require("../../assets/shop/cart/zfbzf_icon.png");
                obj.active = false;
              }
              this.payMethodsArr.push(obj);
            })
          } else {
            //购物￥
            const moneyOne = result.pay_type[0].money;
            this.payMethodsArr[0].name = `赠与收益(可用${moneyOne})`;
            //微信
            const moneyTwo = result.pay_type[1].money;
            this.payMethodsArr[1].name = `微信支付`;
            this.payMethodsArr[2].name = `支付宝`;
            // this.payMethodsArr[2].name = `支付宝`;
          }
        }
      });
    },
    vouchersActive() {
      this.active = !this.active;
    },
    payMethodsActive(index) {
      this.payMethodsArr.forEach((item) => {
        item.active = false;
      });
      this.payMethodsArr[index].active = true;
    },
    //判断支付方式
    goPay() {
      if (this.noAddressData && this.$route.query.categoryId != 2022) {
        this.$toast.fail("请添加收货地址");
        return false;
      }
      this.toastShow = false; //关闭询问框
      let obj = {};
      //判断是否立即购买
      if (this.immediately) {
        //立即购买时传入商品id和数量
        obj.goods_id = this.goods_id;
        obj.num = this.$route.query.num;
        obj.format_id = this.format_id;
      } else {
        //购物车购买传入购物车表id
        obj.sc_id = this.active_sc_id;
      }
      //判断是否是参团
      if (this.goGroup) {
        obj.group_id = this.group_id;
      }
      //使用代金￥ 新人礼包不能使用优惠￥
      if (
        this.active == false ||
        this.isVip == true ||
        this.isIntegral == true
      ) {
        obj.is_equal = 0;
      } else {
        obj.is_equal = 1;
      }
      //超值礼包 勾选贡献值支付
      if (this.isSend) {
        this.payMethodsArr[1].active = true;
      }
      //判断支付方式
      this.payMethodsArr.map((item) => {
        if (item.active) {
          if (item.id == 3) {
            //微信支付调起sdk
            obj.pay_type = item.id;
            this.paytypeid = item.id;
          }else if(item.id == 4) {
            //支付宝调起sdk
            obj.pay_type = item.id;
            this.paytypeid = item.id;
          } else {
            obj.pay_type = item.id;
            this.paytypeid = item.id;
          }
        }
        // if (this.$route.query.categoryId == 3) {
        //   obj.pay_type = item.id;
        //   this.paytypeid = item.id;
        // }
      });
      //积分支付
      if (this.isIntegral) {
        obj.pay_type = this.jf_paytype.id;
        this.paytypeid = this.jf_paytype.id;
      }
      //是否网点自提
      // if (this.oneself) {
      //   if (this.active == 0) {
      //     //做网点选择 提货人信息判断
      //     if (this.activeExtract == "") {
      //       this.$toast.fail("请添加自提网点");
      //       return false;
      //     }
      //     if (this.username == "" && this.phone == "") {
      //       this.$toast.fail("请填写提货人信息！");
      //       return false;
      //     }
      //     obj.sa_id = this.activeExtract.sa_id;
      //     obj.type = 2;
      //     obj.receive_name = this.username;
      //     obj.mobile = this.phone;
      //   } else {
      //     obj.type = 1;
      //     obj.sa_id = this.address.sa_id;
      //   }
      // } else {
      //   //虚拟物品
      //   obj.type = 3;
      // }
      // type=1是实物物流 type=2 是自提  type=3是虚拟
      // v-if="[0,1].includes(item.rocket_status)"
      if (this.$route.query.categoryId == 1 || this.$route.query.categoryId == 16 || this.$route.query.categoryId == 17) {
        obj.type = 1;
        obj.sa_id = this.address.sa_id;
        // 自提区 type = 3
      } else if (this.$route.query.categoryId == 2) {
        obj.sa_id = this.address.sa_id;
        obj.type = 3;
      } else if (this.$route.query.categoryId == 3) {
        obj.type = 1;
        obj.sa_id = this.address.sa_id;
      }
      obj.order_remark = this.ordernote;
      obj.remark = this.mentionNote;
      this.$http.post(submit_order, obj).then(({ data }) => {
        if (data.code == 10001) {
          window.toast_txt(data.message);
          return false;
        }
        const { result } = data;
        if(this.paytypeid != 4) {
          this.ids = result.ids;
        
        //是否多订单
        if (typeof result.ids == "string" && result.ids.indexOf(",") != -1) {
          this.moreOrders = true;
        }
        }
        //是否超值礼包 是的话直接跳转订单页
        if (data.code == 10000 && this.isSend) {
          this.$toast.success(data.message);
          //两秒后跳转
          setTimeout(() => {
            //跳转订单详情
            let url = '/ordersDet/' + this.ids + "/" + 1;
            this.$router.push({ path: url });
          }, 2000)
          return false;
        }
        //判断支付方式
        // console.log(this.paytypeid);
        // 1为火米 2为金米 3为微信 4为支付宝
        if (this.paytypeid == 1 || this.paytypeid == 2) {
        this.$refs.keyBord.showKey();
        } else if(this.paytypeid == 3){
          let url = result.pay_url;
          if (this.$platform == "android") {
            // apps.goweb(url);
            apps.openBrowser(url);
            // setTimeout(() => {
            //   window.location.href = url;
            // },100)
          } else {
            window.location.href = url + '?v=' + (new Date().getTime());
          }
          // const local = window.location.host; //授权域名
          // let urlenCode = '';                            
          // //是否多订单支付
          // if (this.moreOrders) {
          //   urlenCode = encodeURIComponent(`http://${local}/#/payConfirm?type=1`) //编码
          // } else {
          //   urlenCode = encodeURIComponent(`http://${local}/#/payConfirm?ids=${this.ids}&type=1`) //编码
          // }
          // window.location.href = `${data.result.wx_pay.mweb_url}&redirect_url=${urlenCode}`;
        }else if(this.paytypeid == 4) {
          let url = result.pay_url;
          if (this.$platform == "android") {
            // apps.goweb(url);
            apps.openBrowser(url);
          } else {
            window.location.href = url + '?v=' + (new Date().getTime());
          }
          // sessionStorage.setItem('html',data.result.wx_pay);
          // this.$router.push({path: '/alipay'});
        }
      });
    },
    //接收子组件传递 询问框显示
    handleShow(show) {
      this.toastShow = show;
    },
    //放弃付款跳转订单
    goOrder() {
      //是否多订单支付 多订单跳转全部订单
      if (this.moreOrders) {
        this.$router.push({
          path: "orders",
          query: {
            type: 1,
          },
        });
      } else {
        let url;
        //判断虚拟
        // if (this.$route.query.categoryId == 2) {
        //   url = "/ordDet/" + this.ids + "/" + 1;
        // } else {
        //   //跳转订单详情
        //   url = "/ordersDet/" + this.ids + "/" + 1;
        // }
        url = "/ordersDet/" + this.ids + "/" + 1;
        this.$router.push({ path: url });
      }
    },
    //显示密码框
    showKeyArea() {
      this.toastShow = false; //关闭询问框
      this.$refs.keyBord.showKey();
    },
    // 付款
    buttonNum(val) {
      let obj = {};
      obj.gmo_id = this.ids;
      obj.pay_pwd = val;
      obj.pay_type = this.paytypeid;
      this.$http.post(pay_orders, obj).then(({ data }) => {
        if (data.code == 10000) {
          this.$toast.success(data.message);
        } else {
          this.$toast.fail(data.message);
        }
        //两秒后跳转
        setTimeout(() => {
          //是否多订单支付 多订单跳转全部订单
          if (this.moreOrders) {
            this.$router.push({
              path: "orders",
              query: {
                type: 1,
              },
            });
          } else {
            //判断虚拟
            let url;
            // if (this.$route.query.categoryId == 2) {
            //   url = "/ordDet/" + this.ids + "/" + 1;
            // } else {
            //   //跳转订单详情
            //   url = "/ordersDet/" + this.ids + "/" + 1;
            // }
            url = "/ordersDet/" + this.ids + "/" + 1;
            this.$router.push({ path: url });
          }
        }, 2000);
      });
      //关闭付款弹窗
      this.$refs.keyBord.closeKey();
    },
  },
};
</script>
<style lang="scss" scoped>
/deep/ .van-overlay {
  z-index: 999;
}
.pay {
  font-family: "SourceHanSansCN-Normal";
}
.header {
  position: relative;
  width: 100%;
  display: flex;
  align-items: center;
  height: 44px;
  color: #1c1c1c;
  background-color: #f5f5f5;
  padding-left: 15px;
  box-sizing: border-box;
  font-family: "PingFang SC";
  i {
    position: absolute;
    left: 15px;
    top: 12px;
    color: #000000;
    text-align: center;
    font-size: 20px;
    font-weight: bold;
  }
  p {
    height: 44px;
    font-size: 18px;
    line-height: 44px;
    font-weight: bold;
    margin: 0 auto;
  }
}
.payBox {
  z-index: 2;
  position: absolute;
  bottom: 0;
  // height: 80px;
  width: 100%;
  background-color: #ffffff;
  font-size: 16px;
  .prompt {
    font-size: 12px;
    text-align: center;
    height: 25px;
    line-height: 25px;
    background-color: #ff461e;
    p {
      color: #fde7ea;
    }
  }
  .payMain {
    height: 60px;
    padding: 0 15px;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    p {
      color: #1c1c1c;
      font-weight: bold;
    }
    .paytitle {
      margin-left: 20px;
      color: #333333;
      font-size: 14px;
      span {
        color: #999999;
        font-size: 13px;
      }
    }
    .price {
      color: #ff461e;
      margin-left: 10px;
    }
    .button {
      // position: absolute;
      // right: 15px;
      margin-left: 15px;
      width: 88px;
      height: 32px;
      color: #fff;
      font-size: 13px;
      text-align: center;
      line-height: 32px;
      border-radius: 16px;

      background: #ff461e;
    }
    .payBtn {
      width: 100%;
      height: 44px;
      line-height: 44px;
      text-align: center;

      background: #ff461e;
      color: #fee4e8;
      border-radius: 22px;
    }
  }
}
.bottom {
  position: absolute;
  bottom: 0;
  border-top: 10px solid #f5f5f5;
  height: 63px;
  background-color: #ffffff;
  width: 100%;
}
.popup {
  z-index: 3;
  position: absolute;
  left: 0;
  top: 0;
  width: 100%;
  min-height: 100%;
  background: rgba(0, 0, 0, 0.45);
  .popup-box {
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 99999;
    width: 292px;
    height: 186px;
    border-radius: 12px;
    background-color: #ffffff;
    color: #ffffff;
    .popup-title {
      font-size: 18px;
      color: #1c1c1c;
      text-align: center;
      height: 127px;
      border-bottom: 1px solid #e8e8e8;

      p {
        margin-top: 39px;
      }
      .title {
        margin-top: 16px;
        font-size: 16px;
      }
    }

    .button-grounp {
      display: flex;
      align-items: center;
      justify-content: space-around;
      .button {
        width: 50%;
        height: 60px;
        line-height: 60px;
        text-align: center;
        font-size: 16px;
        border-radius: 4px;
      }
      .button-cancel {
        color: #1c1c1c;
        border-right: 1px solid #e8e8e8;
      }
      .button-determine {
        color: #ff461e;
      }
    }
  }
}
.content {
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 63px;
  overflow: auto;
  background-color: #f5f5f5;
  font-family: "PingFang SC";
  -webkit-overflow-scrolling: touch;
  padding: 0 15px;
  box-sizing: border-box;
  padding-bottom: 10px;
  .box {
    margin-top: 10px;
    width: 100%;
    background-color: #fefefe;
    border-radius: 12px;
  }
  .tableBox {
    width: 217px;
    margin: 20px auto;
    display: flex;
    align-items: center;
    p {
      flex: 1;
      height: 30px;
      line-height: 30px;
      text-align: center;
      color: #a8a8a8;
      border: 1px solid #a8a8a8;
    }
    .tableActive {
      color: #ff461e;
      border: 1px solid #ff461e;
    }
  }
  .extract {
    min-height: 100px;
    display: flex;
    flex-direction: column;
    justify-content: space-around;
    color: #333333;
    padding: 0 15px;
    padding-top: 10px;
    box-sizing: border-box;
    .extractList {
      display: flex;
      align-items: center;
      max-width: 702px;
      // justify-content: space-between;
      .icon {
        width: 16px;
        height: 20px;
      }
      .userIcon {
      }
      p {
        margin-left: 15px;
      }
      .go {
        width: 8px;
        height: 14px;
        margin-left: auto;
      }
      .inputIcon {
        width: 17px;
        height: 17px;
        margin-left: auto;
      }
      .extractInfo {
        display: flex;
        flex-direction: column;
        // align-items: center;
        p {
          margin-top: 7px;
          font-size: 12px;
          color: #565656;
        }
        p:first-child {
          font-size: 15px;
          color: #333333;
        }
        p:last-child {
          width: 230px;
          overflow: hidden;
          white-space: nowrap;
          text-overflow: ellipsis;
        }
      }
      .info {
        padding-bottom: 20px;
      }
    }
    .last-list {
      min-height: 50px;
      margin-top: 15px;
    }
  }
  .address {
    height: 73px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #1c1c1c;
    padding: 0 15px;
    box-sizing: border-box;
    font-family: PingFangTC-Regular;
    img {
      width: 16px;
      height: 20px;
    }
    .goAddress {
      width: 6px;
      height: 12px;
    }
    .address-min {
      display: flex;
      flex-direction: column;
      padding: 10px 0;
      box-sizing: border-box;
      margin-left: 10px;
      .information {
        display: flex;
        align-items: center;
        font-size: 16px;
        .phone {
          margin-left: 7px;
        }
      }
      .addressText {
        margin-top: 9px;
        font-size: 12px;
        width: 250px;
        text-overflow: -o-ellipsis-lastline;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
      }
      .noAddressTxt {
        color: #999999;
      }
    }
  }
  .noAddress {
    height: 50px;
  }
  .goodsList {
    // height: 250px;
    // height: 320px;
    display: flex;
    flex-direction: column;
    color: #333333;
    padding-top: 7px;
    box-sizing: border-box;
    font-size: 16px;
    padding: 25px 0;
    .list-top {
      flex: 1;
      display: flex;
      align-items: center;
      padding: 0 15px;
      box-sizing: border-box;
      font-family: SourceHanSansCN-Normal;
      height: 82px;
      margin-top: 20px;
      .list-top-left {
        width: 82px;
        height: 82px;
      }
      .list-top-right {
        width: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        margin-left: 30px;
        height: 80%;
        .specifications {
          font-size: 12px;
          width: 200px;
          margin-top: 5px;
          text-overflow: -o-ellipsis-lastline;
          overflow: hidden;
          text-overflow: ellipsis;
          display: -webkit-box;
          -webkit-line-clamp: 1; //行数
          -webkit-box-orient: vertical;
        }
        .goodsdeatil {
          width: 100%;
          display: flex;
          align-items: center;
          justify-content: space-between;
          .number {
            font-size: 12px;
            color: #aeaeae;
          }
          .price {
            font-size: 18px;
            font-weight: bold;
            color: #ff461e;
            span {
              font-size: 12px;
            }
          }
        }
        .name {
          font-size: 16px;
          overflow: hidden;
          text-overflow: ellipsis;
          display: -webkit-box;
          -webkit-line-clamp: 1;
          -webkit-box-orient: vertical;
        }
        .specifications {
          font-size: 12px;
        }
      }
    }
    .list-bottom {
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-around;
      padding: 0 15px;
      box-sizing: border-box;
      text-align: right;
      .additionalList {
        display: flex;
        align-items: center;
        // align-items: flex-end;
        justify-content: flex-end;
        margin-top: 15px;
        font-size: 13px;
        p {
          text-align: right;
        }
        p {
          flex: 1;
        }
        p:last-child {
          flex: 3;
        }
        .preferential {
          color: #ff461e;
        }
        input {
          padding-left: 40px;
          box-sizing: border-box;
          flex: 3;
          // 清除input样式
          -webkit-appearance: none;
          -moz-appearance: none;
          background: none;
          outline: 0;
          border: none;
          height: 35px;
          line-height: 35px;
          // border-bottom: 1px solid #c9c9c9;
        }
        // 更改样式placeholder
        input::-webkit-input-placeholder {
          color: #c5c5c5;
        }
      }
      .bottomList {
        margin-top: 50px;
        font-size: 13px;
        color: #333333;
        span {
          color: #999999;
        }
        span:last-child {
          margin-left: 15px;
          color: #ff461e;
        }
      }
    }
  }
  .available {
    display: flex;
    flex-direction: column;
    justify-content: center;
    height: 50px;
    padding: 0 22px;
    box-sizing: border-box;
    margin-top: 40px;
    .vouchersList {
      display: flex;
      align-items: center;
      .active {
        width: 21px;
        height: 21px;
      }
      p {
        margin-left: 20px;
        font-size: 12px;
        color: #b5b5b5;
      }
      .activeText {
        color: #1c1c1c;
      }
    }
  }

  .payMethod {
    display: flex;
    flex-direction: column;
    align-items: center;
    // height: 350px;
    padding: 25px 0;
    background-color: #fff;
    .payTitle {
      margin: 15px 0;
      font-size: 14px;
      color: #df9411;
    }
    .paytitleImg {
      width: 100%;
      height: 54px;
      line-height: 54px;
      padding-left: 15px;
      box-sizing: border-box;
      font-size: 14px;
      font-weight: bolder;
      color: #333333;
      background: url("../../assets/shop/cart/shouyitai_bg.png") no-repeat
        center;
      background-size: 100% 100%;
    }
    .combination {
      margin-top: 10px;
      color: #333333;
      font-size: 12px;
    }
    .payList {
      margin-top: 15px;
      padding: 15px;
      box-sizing: border-box;
      display: flex;
      align-items: center;
      width: 328px;
      height: 70px;
      border-radius: 10px;
      background-color: #f6f6f6;
      // img {
      //   width: 18px;
      //   height: 18px;
      // }
      p {
        margin-left: 20px;
        font-size: 12px;
        color: #1c1c1c;
      }
      .active {
        position: absolute;
        right: 30px;
        width: 21px;
        height: 21px;
      }
      .payicon {
        margin-right: 27px;
        width: 20px;
      }
    }
  }
  .prompt {
    margin-top: 25px;
    color: #ff461e;
    font-size: 15px;
    text-align: center;
  }
}
.wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  .block {
    position: relative;
    width: 345px;
    height: 411px;
    border-radius: 16px;
    background-color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 43px 33px;
    box-sizing: border-box;
    .close {
      position: absolute;
      top: 15px;
      right: 15px;
      width: 25px;
      height: 25px;
    }
    .title {
      font-size: 21px;
      color: #353333;
      font-weight: bold;
      margin-bottom: 35px;
    }
    input {
      margin-top: 20px;
      width: 280px;
      // 清除input样式
      -webkit-appearance: none;
      -moz-appearance: none;
      background: none;
      outline: 0;
      border: none;
      height: 42px;
      line-height: 42px;
      background-color: #fffbfc;
      border: 1px solid #ff461e;
      border-radius: 4px;
      padding: 0 10px;
      box-sizing: border-box;
    }
    // 更改样式placeholder
    input::-webkit-input-placeholder {
      color: #6f6f6f;
    }
    .button {
      margin-top: 48px;
      width: 280px;
      height: 48px;
      line-height: 48px;
      text-align: center;

      background: linear-gradient(90deg, #e9cdb3 0%, #cdb4a3 100%);
      border-radius: 24px;
      color: white;
      font-size: 16px;
    }
  }
}
</style>