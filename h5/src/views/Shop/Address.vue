<template>
  <div class="address">
    <!-- <header>
      <button class="iconfont iconfanhui back" @click="goBack">
        <i class="iconfont icon-return"></i>
      </button>
      <h3>管理收货地址</h3>
    </header> -->
    <TopHeader :info="info"></TopHeader>
    <div class="content">
      <div class="noAddress" v-if="noAddress">
        <img src="../../assets/shop/wushouhdiz_img.png">
        <p>暂无收货地址</p>
      </div>
      <div
        class="addressListbox"
        v-for="(item, index) in addressArr"
        :key="index"
        v-else
      >
        <!-- 选中前 -->
        <img
          src="../../assets/shop/cart/wei-img.png"
          class="active"
          @click="handleActive(index)"
          v-if="item.active"
        />
        <!-- 选中后 -->
        <img
          src="../../assets/shop/cart/dhi-img.png"
          class="active"
          @click="handleActive(index)"
          v-else
        />
        <div class="address-min" @click="handleClickAddress(index)">
          <div class="information">
            <p class="name">{{ item.sa_name }}</p>
            <p class="phone">{{ item.sa_mobile }}</p>
          </div>
          <div class="addressText">
            {{ item.address }}
          </div>
        </div>
        <div class="address-bottom"></div>
        <img
          src="../../assets/shop/xiezi-img.png"
          class="icon"
          @click="gochangeAddress(index)"
        />
      </div>
    </div>
    <div class="bottomBox">
      <div class="button" @click="$router.push('addAddress')">+ 新建地址</div>
    </div>
  </div>
</template> 
<script>
import {
  get_address_list, update_address, set_default
} from "@/http/api.js";
export default {
  data () {
    return {
      addressArr: [],
      noAddress:false,
        info: {
        isBack: true,
        title: '管理收货地址',
      },
    };
  },  
  beforeRouteEnter (to, from, next) {
    if (from.path == "/pay") {
      to.query.isPay = true
    }
    next();
  },
  created () {
    this.getAddressList()
  },
  methods: {
    //获取收货地址列表
    getAddressList () {
      this.$http
        .post(get_address_list)
        .then(({ data }) => {
          const { result } = data
          this.addressArr = result;
          if(!this.addressArr){
            this.noAddress = true;
          }else{
            this.addressArr.forEach((item) => {
            item.active = false;
            //拼接收货地址
            item.address = item.pca + item.sa_address;
            if (item.sa_default === 1) {
              //默认地址 选中状态
              item.active = true
            }
          })
          }
          
        })
    },
    //点击
    handleClickAddress (index) {
      //选择支付跳转收货地址
      if (this.$route.query.isPay) {
        const stg = JSON.stringify(this.addressArr[index])
        //本地存储选中的收货地址
        localStorage.setItem("activeAdress", stg);
        this.$router.back()
      } else {
        return false
      }
    },
    //修改默认收货地址
    handleActive (index) {
      if (this.addressArr[index].active) { return false } //已选中 再次选中return
      this.addressArr.forEach((item) => {
        item.active = false;
      })
      this.addressArr[index].active = true;
      const obj = {}
      obj.sa_id = this.addressArr[index].sa_id;
      // obj.province = this.addressArr[index].sa_province;
      // obj.city = this.addressArr[index].sa_city;
      // obj.area = this.addressArr[index].sa_area;
      // obj.name = this.addressArr[index].sa_name;
      // obj.mobile = this.addressArr[index].sa_mobile;
      // obj.address = this.addressArr[index].sa_address;
      // obj.default = 1; //设置为默认收获地址
      this.$http
        .post(set_default, obj)
        .then(({ data }) => {
          this.$toast.success(data.message)
          // location.reload(); //刷新页面
          this.getAddressList()
        })
    },
    //添加
    goAdd () {
      this.$router.push('addAddress')
    },
    //修改
    gochangeAddress (index) {
      this.$router.push({
        path: 'changeAddress',
        query: {
          addressArr: this.addressArr[index],
          addressAll: this.addressArr
        }
      })
    },
    goBack () {
      if(this.$route.query.type == 1) {
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("exit");
        } else if (this.$platform == "android") {
          this.$router.back();
        } else {
          this.$router.back();
        }
      }else {
        if (this.$platform == "ios") {
          window.webkit.messageHandlers.iosAction.postMessage("exit");
        } else if (this.$platform == "android") {
          this.$router.back();
        } else {
          this.$router.back();
        }
      }
    },
  }
}
</script>
<style lang="scss" scoped>
.address {
  background: #f5f5f5;
}
header {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 44px;
    background: #f7f5f6;
    color: #000;
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
    color: #000;
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
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 55px;
  overflow: auto;
  background: #f5f5f5;
  font-size: 16px;
  font-family: "PingFang SC";
  -webkit-overflow-scrolling: touch;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-bottom: 10px;
    .noAddress{
    margin-top: 160px;
    img{
      width: 185px;
      height: 100px;
      margin: 0 auto;
    } 
    p{
      text-align: center;
      margin-top: 15px;
      font-size: 14px;
      color: black;
    }
  }
  .addressListbox {
    margin-top: 10px;
    width: 345px;
    height: 120px;
    border-radius: 12px;
    background-color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #1c1c1c;
    padding: 0 15px;
    box-sizing: border-box;
    .active {
      width: 21px;
      height: 21px;
    }
    .address-min {
      flex: 1;
      display: flex;
      flex-direction: column;
      padding: 10px 0;
      box-sizing: border-box;
      margin-left: 10px;
      
      .information {
        width: 250px;
        display: flex;
        align-items: center;
        // justify-content: space-between;
        font-size: 16px;
        .phone {
          margin-left: 10px;
        }
      }
      .addressText {
        margin-top: 5px;
        font-size: 12px;
        text-overflow: -o-ellipsis-lastline;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2; //行数
        -webkit-box-orient: vertical;
        width: 250px; //宽度
      }
      .icon {
        width: 12px;
        height: 12px;
      }
    }
    .address-bottom{
      height: 80px;
    }
  }
}
.bottomBox {
  width: 100%;
  height: 55px;
  background: #f5f5f5;
  position: absolute;
  bottom: 0px;
  z-index: 2;
  .button {
    width: 345px;
    height: 40px;
    text-align: center;
    line-height: 40px;
    color: #fff;
    margin: 0 auto;
    border-radius: 22px;
    background: #FF461E;
  }
}
</style>