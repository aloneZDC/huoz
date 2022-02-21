<template>
  <div class="cart">
    <div class="header">
      <!-- <i class="iconfont icon-return" @click="goBack" v-if="isDetails"></i> -->
      <p>购物车</p>
      <span @click="handledelateShow" v-if="!noGoodsList">删除</span>
    </div>
    <div class="content">
      <div class="show" v-if="noGoodsList">
        <div class="nodata">
          <img src="../../assets/shop/cart/gwuche-img.png" />
          <div class="button" @click="$router.push('/')">开启购物之旅</div>
        </div>
      </div>
      <div class="show" v-else>
        <div
          class="goodslistBox"
          v-for="(item, index) in goodsList"
          :key="index"
        >
          <!-- 选中前 -->
          <img
            src="../../assets/shop/cart/wei-img.png"
            class="active"
            @click="handleActive(index)"
            v-show="item.active"
          />
          <!-- 选中后 -->
          <img
            src="../../assets/shop/cart/dhi-img.png"
            class="active"
            @click="handleActive(index)"
            v-show="!item.active"
          />
          <img class="goodsImg" :src="item.goods.goods_img" />
          <div class="operation">
            <div class="topMain">
              <p>{{ item.goods.goods_title }}</p>
              <p class="specifications" v-if="item.goods.format">
                {{ item.goods.format.name }}
              </p>
            </div>
            <div class="main">
              <div class="left">
                <p><span>￥</span>{{ item.goods.goods_price }}</p>
                <!-- <p class="historyPrice">
                  <span>￥</span>{{ item.goods.goods_market }}
                </p> -->
              </div>
              <div class="right">
                <div class="Reduction" @click="Reduction(index)">-</div>
                <!-- <div class="number">{{ item.sc_num }}</div> -->
                <form action="javascript:return true;">
                  <input
                    type="text"
                    v-model="item.sc_num"
                    class="number"
                    @click="saveNum(item.sc_num)"
                    @change="handleBlur($event, index)"
                  />
                </form>
                <div class="add" @click="add(index)">+</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="settlement" v-if="!noGoodsList">
      <div class="left">
        <img
          src="../../assets/shop/cart/wei-img.png"
          class="active"
          @click="handleAllActive"
          v-if="allChecked"
        />
        <img
          src="../../assets/shop/cart/dhi-img.png"
          class="active"
          @click="handleAllActive"
          v-else
        />
        <p>全选</p>
      </div>
      <div class="right">
        <!-- <span>不含运费</span> -->
        <p>合计:￥{{ totalPrice.toFixed(2) }}</p>
        <div class="button" @click="goPay">去结算({{ totalNum }})</div>
      </div>
    </div>
    <div class="popup" v-show="delateShow">
      <div class="popup-box">
        <div class="popup-title">确定删除商品?</div>
        <div class="button-grounp">
          <div class="button button-cancel" @click="hideDeletePopup">取消</div>
          <div class="button button-determine" @click="handleDelete">确定</div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import {
  get_shop_cart,
  delete_shop_cart,
  update_shop_cart,
} from "@/http/api.js";
export default {
  name: 'cart',
  inject: ['reload'],
  data () {
    return {
      goodsList: [],
      noGoodsList: false,
      //全选
      allChecked: false,
      //总价格
      totalPrice: 0,
      //总数量
      totalNum: 0,
      // active: true,
      delateShow: false,
      goodsId: '',
      notEnough: false,
      isDetails: false,
      noActive: false,
      activelength: 0,
      num: 0,
    };
  },
  created () {

    //判断是否来自商品详情
    if (this.$route.query.isDetails) {
      this.isDetails = true;
    }
    this.getShopCart();
    // //判断有无商品
    // this.goodsList == undefined || this.goodsList.length <= 0 ? this.noGoodsList = true : this.noGoodsList = false;
  },
  beforeRouteEnter (to, from, next) {
    if (from.name == "detail") {
      to.query.isDetails = true
    }
    next();
  },
  mounted () {
    window.callJS = this.callJS;
    //获取登录态
    const tokenId = this.$cookie.get("token_id");
    if (tokenId == "" || tokenId == undefined) {
      if (this.$platform == "android") {
        this.noGoodsList = true;
      }else {
        this.isLogin = false;
      }
    }
  },
  methods: {
    //保存修改之前的商品数量
    saveNum (num) {
      this.num = num;
    },
    handleBlur (e, index) {
      // if (e.keyCode == 13) {
      e.preventDefault(); //禁止默认事件（默认是换行）
      if (this.num == this.goodsList[index].sc_num) return false;
      if (this.goodsList[index].sc_num > 0) {
        this.goUpdateNum(index, this.goodsList[index].sc_num)
        this.getTotalNum()
      } else {
        this.$toast.fail('请输入合适数量！')
        this.goodsList[index].sc_num = this.num; //重置
        return false;
      }
      // }
    },
    // android刷新页面方法
    callJS () {
      this.getShopCart();
      this.reload();
    },
    goBack () {
      this.$router.back();
    },
    //跳转支付
    goPay () {
      let deleteArr = []
      this.goodsList.forEach((item, index, arr) => {
        //获取选中状态的对象 
        if (item.active) {
          deleteArr.push(item.sc_id) //购物车表id
        }
      })
      if (deleteArr.length <= 0) {
        this.$toast('未选中商品')
        return false;
      }
      let sc_id = deleteArr.join(',');  //逗号分隔
      let url = `/pay?sc_id=${sc_id}&isVip=false&categoryId=1`
      if (this.$platform == "ios") {
        window.webkit.messageHandlers.iOSShopWebView.postMessage(url);
      } else if (this.$platform == "android") {
        //详情跳转过来
        if (this.isDetails) {
          let urls = `/pay?sc_id=${sc_id}&categoryId=1`;
          // this.$router.push({
          //   path: 'pay',
          //   query: {
          //     sc_id: sc_id
          //   }
          // });
          apps.newfullscreenwebview(urls);
        } else {
          // this.$router.push({ path: url });
          apps.newfullscreenwebview(url);
        }
        apps.refresh();
      } else {
        this.$router.push({ path: url });
      }
    },
    //获取购物车数据
    getShopCart () {
      this.$http
        .post(get_shop_cart)
        .then(({ data }) => {
          const { result } = data
          if (result != undefined && result.list.length > 0) {
            this.noGoodsList = false
            //数组添加active选中状态
            result.list.forEach((item) => {
              item.active = false;
            })
            this.goodsList = result.list; //商品信息
          } else {
            this.noGoodsList = true
          }
        })
    },
    //删除商品弹窗
    handledelateShow () {
      this.goodsList.forEach((item) => {
        if (item.active) {
          this.delateShow = true
        }
      })

    },
    //全选
    handleAllActive () {
      this.totalPrice = 0;
      this.allChecked = !this.allChecked;
      this.goodsList.forEach((item) => {
        if (this.allChecked) {
          item.active = true
          this.totalPrice += Number(this.cal.accMul(item.sc_num, item.goods.goods_price))
          this.getTotalNum(); //更新总数量
        } else {
          item.active = false;
          this.totalNum = 0; //重置总数量
        };
      })
    },
    //更新总数量
    getTotalNum () {
      this.totalNum = 0;
      this.goodsList.forEach((item, index) => {
        if (item.active) {
          this.totalNum += Number(item.sc_num);
        }
      })
    },
    //更改总价格 
    getTotalPrice (index, type, ifadd) {
      this.getTotalNum(); //更新总数量
      const total = Number(this.cal.accMul(this.goodsList[index].sc_num, this.goodsList[index].goods.goods_price))
      if (this.goodsList[index].active) {
        if (type == 1) {
          //从修改数量调用
          if (ifadd == 'add') {
            //添加数量时
            this.totalPrice += Number(this.goodsList[index].goods.goods_price);
          } else {
            //减少数量时 
            this.totalPrice -= Number(this.goodsList[index].goods.goods_price);
          }
          return false;
        }
        //选中
        this.totalPrice += total;
      } else {
        //未选中
        this.totalPrice -= total;
      }
    },
    //复选
    handleActive (index) {
      this.goodsList[index].active = !this.goodsList[index].active;
      this.allChecked ? this.allChecked = false : this.allChecked;
      this.getTotalPrice(index); //修改总价
      this.getTotalNum(); //更新总数量

    },
    //增加商品数量
    add (index) {
      this.goodsList[index].sc_num = Number(this.goodsList[index].sc_num) + 1;
      //更改数量方法
      this.goUpdateNum(index, this.goodsList[index].sc_num)
      //改变数量后 判断选中状态计算总价
      if (this.goodsList[index].active) {
        this.getTotalPrice(index, 1, 'add'); //修改总价
      }
    },
    //减少商品数量
    Reduction (index) {
      if (this.goodsList[index].sc_num > 1) {
        this.goodsList[index].sc_num = Number(this.goodsList[index].sc_num) - 1
        //更改数量方法
        this.goUpdateNum(index, this.goodsList[index].sc_num);
        //改变数量后 判断选中状态计算总价
        if (this.goodsList[index].active) {
          this.getTotalPrice(index, 1, 'noadd'); //修改总价
        }
      } else {
        //显示删除商品弹窗
        this.delateShow = true;
        this.goodsId = index;
        this.notEnough = true;
      }
    },
    // 改变购物车商品数量
    goUpdateNum (index, num) {
      let obj = {};
      obj.sc_id = this.goodsList[index].sc_id;
      obj.num = num;
      this.$http
        .post(update_shop_cart, obj)
        .then(({ data }) => {
          if (data.code == "10000") {
            this.totalPrice = 0;
            this.goodsList.forEach((item) => {
              if (item.active) {
                this.totalPrice += Number(this.cal.accMul(item.sc_num, item.goods.goods_price))
              }
            })
          }
        })
    },
    //关闭删除弹窗
    hideDeletePopup () {
      this.delateShow = false;
    },
    //删除商品
    handleDelete () {
      let obj = {}
      if (!this.notEnough) {
        let deleteArr = []
        //删除active为true的商品 选中的商品
        this.goodsList.forEach((item, index, arr) => {
          //获取选中状态的对象 
          if (item.active) {
            deleteArr.push(item.sc_id) //购物车表id
          }
        })
        obj.sc_id = deleteArr.join(',')  //逗号分隔
      } else {
        //数量>1 删除单个商品 
        obj.sc_id = this.goodsList[this.goodsId].sc_id
      }
      //删除操作
      this.$http
        .post(delete_shop_cart, obj)
        .then(({ data }) => {
          this.$toast.success(data.message)
        })
      this.delateShow = false;
      this.notEnough = false
      this.goodsId = '' //重置id
      this.getShopCart();
      this.totalPrice = 0; //总价重置
    }
  }
}
</script>
<style lang="scss" scoped>
.cart {
  font-family: "PingFang SC";
}
.header {
  height: 44px;
  line-height: 44px;
  width: 100%;
  background-color: #fff;
  position: relative;
  i {
    position: absolute;
    left: 15px;
    color: #000000;
    text-align: center;
    font-weight: bold;
    font-size: 20px;
  }
  p {
    text-align: center;
    font-size: 18px;
    font-weight: bold;
    color: #1c1c1c;
  }
  span {
    position: absolute;
    right: 15px;
    top: 2px;
    color: #FF461E;
    font-size: 14px;
  }
}
.bottom {
  position: absolute;
  bottom: 0;
  background-color: #fff;
  height: 63px;
}
.settlement {
  // z-index: 2;
  position: absolute;
  bottom: 50px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  padding: 0 15px;
  box-sizing: border-box;
  height: 43px;
  background-color: #fff;
  p {
    font-size: 15px;
    font-weight: bold;
  }
  .left {
    display: flex;
    align-items: center;
    p {
      margin-left: 12px;
      color: #1c1c1c;
    }
    .active {
      width: 21px;
      height: 21px;
    }
  }
  .right {
    width: auto;
    margin-left: auto;
    display: flex;
    align-items: center;
    span {
      margin-right: 5px;
      font-size: 10px;
      color: #999999;
    }
    p {
      color: #333333;
      margin-right: 15px;
    }
    .button {
      padding: 0 10px;
      box-sizing: border-box;
      height: 32px;
      line-height: 32px;
      text-align: center;
      color: #ffffff;
      font-size: 14px;
      border-radius: 16px;
      background: #FF461E;
    }
  }
}
.popup {
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
    margin-left: -136.5px;
    margin-top: -73px;
    z-index: 99999;
    width: 273px;
    height: 146px;
    border-radius: 12px;
    background-color: #ffffff;
    color: #ffffff;
    .popup-title {
      font-size: 18px;
      color: #1c1c1c;
      text-align: center;
      height: 86px;
      line-height: 86px;
      border-bottom: 1px solid #e8e8e8;
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
        color: #FF461E;
      }
    }
  }
}
.content {
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  overflow: auto;
  background: #f5f5f5;
  padding-bottom: 60px;
  -webkit-overflow-scrolling: touch;
  font-family: SourceHanSansCN-Normal;
  .show {
    padding: 0 14px;
    box-sizing: border-box;
    padding-bottom: 10px;
    .nodata {
      height: 250px;
      width: 210px;
      margin: 0 auto;
      margin-top: 150px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      img {
        width: 216px;
        height: 182px;
      }
      .button {
        width: 147px;
        height: 40px;
        text-align: center;
        line-height: 40px;
        color: #ffffff;
        font-size: 14px;

        background: #FF461E;
        border-radius: 20px;
      }
    }
    .goodslistBox {
      display: flex;
      align-items: center;
      width: 100%;
      height: 126px;
      padding: 0 10px;
      box-sizing: border-box;
      border-radius: 12px;
      // box-shadow: 0 0 2px #000000;
      background-color: #fff;
      margin-top: 15px;
      .active {
        width: 18px;
        height: 18px;
      }
      img:nth-child(3) {
        height: 90px;
        width: 90px;
        min-width: 90px;
        margin: 0 15px 0 5px;
      }
      .operation {
        width: 100%;
        height: 75px;
        .topMain {
          p {
            color: #333333;
            // font-weight: bold;
            font-size: 16px;
            text-overflow: -o-ellipsis-lastline;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
          }
          .specifications {
            background-color: #f9f9f9;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 12px;
            text-overflow: -o-ellipsis-lastline;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 1; //行数
            -webkit-box-orient: vertical;
            width: 200px; //宽度
          }
        }

        .main {
          width: 200px;
          display: flex;
          margin-top: 10px;
          justify-content: space-between;
          .left {
            display: flex;
            align-items: flex-end;
            p {
              color: #FF461E;
              font-size: 16px;
              font-weight: bold;
            }
            span {
              font-size: 12px;
            }
            .historyPrice {
              margin-left: 5px;
              color: #cecece;
              font-size: 12px;
              text-decoration: line-through;
            }
          }
          .right {
            display: flex;
            // margin: 0 20px;
            margin-right: 5px;
            width: 100px;
            height: 25px;
            border-radius: 15px;
            text-align: center;
            line-height: 25px;
            font-weight: bolder;
            font-size: 14px;
            // border: 1px solid #d8d8d8;
            form {
              min-width: 85px;
              .number {
                // flex: 3;
                width: 100%;
                height: 30px;
                text-align: center;
                background-color: #f4f4f4;
                border-radius: 4px;
                // 清除input样式
                -webkit-appearance: none;
                -moz-appearance: none;
                outline: 0;
                border: none;
              }
              input::-webkit-input-placeholder {
                color: #c9c9c9;
                font-size: 16px;
              }
            }

            .Reduction {
              // flex: 1;
              width: 20%;
              font-size: 18px;
              font-weight: bolder;
              // border-right: 1px solid #d8d8d8;
            }
            .add {
              // flex: 1;
              width: 20%;
              font-size: 18px;
              font-weight: bolder;
            }
          }
        }
      }
    }
  }
}
</style>