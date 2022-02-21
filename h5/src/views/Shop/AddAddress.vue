<template>
  <div class="addaddress">
    <TopHeader :info="info"></TopHeader>
    <div class="content">
      <div class="addBox">
        <div class="name box">
          <p class="title">收货人:</p>
          <input
            type="text"
            placeholder="请输入收件人名称"
            v-model="parameter.name"
          />
        </div>
        <div class="phone box">
          <p class="title">手机号码:</p>
          <input
            type="text"
            placeholder="请输入手机号码"
            v-model="parameter.mobile"
          />
        </div>
        <div class="area box">
          <p class="title">选择地区:</p>
          <!-- <van-cell is-link @click="showPopup">展示弹出层</van-cell> -->
          <div class="choose" @click="showPopup">{{ address }}</div>
          <img src="../../assets/shop/details/xiangqing_xiayj_h_icon.png" />
        </div>
        <div class="detailAera">
          <textarea
            cols="30"
            rows="10"
            placeholder="详细地址:如街道、楼牌号等"
            v-model="parameter.address"
          ></textarea>
        </div>
      </div>
      <div class="button" @click="submit">保存</div>
      <van-popup
        v-model="show"
        position="bottom"
        round
        :style="{ height: '220px' }"
      >
        <van-area
          title="配送至"
          value="110101"
          :area-list="areaList"
          @cancel="cancel"
          @confirm="confirm"
        />
      </van-popup>
    </div>
  </div>
</template>
<script>
import areaList from '@/methods/area.js'//本地地区数据
import {
  get_address_list, add_address, get_default
} from "@/http/api.js";
export default {
  data () {
    return {
      info: {
        isBack: true,
        title: '新建收货地址',
      },
      show: false,
      parameter: {
        name: "",
        mobile: "",
        address: "",
        province: '',
        city: '',
        area: '',
        default: '2' //默认收货地址 默认不勾选
      },
      detailedAddress: '',
      areaList,
      address: '请选择省市区',//选中的地区名称
      noAddressData: false,
      noDefaultData:false,
    };
  },
  created () {
    this.getAddressList() //获取收货地址列表
    this.getAddressDefult()
  },
  methods: {
    //获取收货地址
    getAddressDefult(){
      this.$http
          .post(get_default)
          .then(({ data }) => {
            const { result } = data
            if (data.code == 10000) {
              // this.address.sa_address = result.pca + data.result.sa_address
            } else {
              //没有默认收货地址
              this.noDefaultData = true;
            }
          })
    },
    //获取收货地址列表
    getAddressList () {
      this.$http
        .post(get_address_list)
        .then(({ data }) => {
          if (data.result == undefined && data.result.list.length == 0) {
            //获取收货地址为空 新增收货地址为默认地址
            this.noAddressData = true;
          }
        })
    },
    //确定 赋值
    confirm (val) {
      // this.city_show = false;
      this.address = val[0].name + "-" + val[1].name + "-" + val[2].name;
      this.parameter.province = val[0].code
      this.parameter.city = val[1].code
      this.parameter.area = val[2].code
      this.show = false;
    },
    //显示弹出层 地址选择
    showPopup () {
      this.show = true;
    },
    //关闭弹出层
    cancel () {
      this.show = false
    },
    //提交
    submit () { 
      if (this.parameter.name.length <= 0) {
        this.$toast.fail("请输入收件人名称")
        return;
      }
      var myreg = /^(13[0-9]|14[01456879]|15[0-3,5-9]|16[2567]|17[0-8]|18[0-9]|19[0-3,5-9])\d{8}$/;
      if (this.parameter.mobile.length <= 0) {
        this.$toast.fail('请输入手机号码');
        return false
      } else if (!myreg.test(this.parameter.mobile)) {
        this.$toast.fail('请输入正确的手机格式');
        return false
      }

      if (this.address.length <= 0) {
        this.$toast.fail("请选择省市区")
        return;
      }
      if (this.parameter.address.length <= 0) {
        this.$toast.fail("请输入详细地址")
        return;
      }
      if (this.noDefaultData || this.noAddressData) {
        this.parameter.default = 1  
      }
      this.$http
        .post(add_address, this.parameter)
        .then(({ data }) => {
          if (data.code == 10000) {
           this.$toast.success(data.message)
           //1秒后跳转上一页
           setTimeout(()=>{
             this.$router.back()
           },1000)
          }else{
            this.$toast.fail(data.message)
          }
        })
    }
  }
}
</script>
<style lang="scss" scoped>
.content {
  z-index: 2;
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  overflow: auto;
  background: #f5f5f5;
  font-size: 16px;
  font-family: "PingFang SC";
  -webkit-overflow-scrolling: touch;
  color: #393939;
  .addBox {
    width: 345px;
    height: 290px;
    background-color: #fff;
    margin: 0 auto;
    margin-top: 10px;
    padding: 0 15px;
    box-sizing: border-box;
    border-radius: 12px;
    .box {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 52px;
      line-height: 52px;
      border-bottom: 1px solid #e8e8e8;
      .title {
        color: #cecece;
      }
      input {
        background: none;
        outline: none;
        border: none;
      }
    }
    .area {
      .title {
        // width: 100px;
        flex: 1;
      }
      .choose {
        flex: 3;
        text-align: center;
        font-size: 12px;
        span {
          margin-left: 30px;
        }
      }
      img {
        width: 6px;
        height: 12px;
      }
    }
    .detailAera {
      height: 136px;
      padding-top: 15px;
      textarea {
        width: 100%;
        height: 80%;
        outline: none;
        cursor: pointer;
        border: none;
      }
      textarea::-webkit-input-placeholder {
        /* WebKit browsers */
        color: #cecece;
      }
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
    border-radius: 22px;
    background: #FF461E;
  }
  /deep/ .van-picker  .van-picker__toolbar{
    touch-action: none;
    pointer-events: auto;
  }
}
</style>  