<template>
  <div class="addaddress">
    <TopHeader :info="info"></TopHeader>
    <div class="content">
      <div class="addBox">
        <div class="name box">
          <p class="title">收货人:</p>
          <input type="text" v-model="addressArr.sa_name" />
        </div>
        <div class="phone box">
          <p class="title">手机号码:</p>
          <input type="text" v-model="addressArr.sa_mobile" />
        </div>
        <div class="area box">
          <p class="title">选择地区:</p>
          <!-- <van-cell is-link @click="showPopup">展示弹出层</van-cell> -->
          <div class="choose" @click="showPopup">
            {{ addressArr.pca }}
          </div>
          <img src="../../assets/shop/details/xiangqing_xiayj_h_icon.png" />
        </div>
        <div class="detailAera">
          <textarea
            cols="30"
            rows="10"
            placeholder="详细地址:如街道、楼牌号等"
            v-model="addressArr.sa_address"
          ></textarea>
        </div>
      </div>
      <div class="operation save" @click="submit">保存</div>
      <div class="operation delete" @click="delateShow = true">删除</div>
      <van-popup
        v-model="show"
        position="bottom"
        round
        :style="{ height: '30%' }"
      >
        <van-area
          title="配送至"
          value="110101"
          :area-list="areaList"
          @cancel="cancel"
          @confirm="confirm"
        />
      </van-popup>
      <div class="popup" v-show="delateShow">
        <div class="popup-box">
          <div class="popup-title">确定要删除该收货地址吗？</div>
          <div class="button-grounp">
            <div class="button button-cancel" @click="delateShow = false">
              取消
            </div>
            <div class="button button-determine" @click="handleDelete">
              确定
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import areaList from '@/methods/area.js'//本地地区数据
import {
  update_address, delete_address
} from "@/http/api.js";
export default {
  data () {
    return {
      info: {
        isBack: true,
        title: '修改收货地址',
      },
      show: false,
      detailedAddress: '',//详细地址
      address: '',//省市区
      addressArr: {},
      addressAll: [],
      areaList,
      address: '请选择省市区',//选中的地区名称
      delateShow: false,
    };
  },
  created () {
    this.addressArr = this.$route.query.addressArr; //选中地址
    this.addressAll = this.$route.query.addressAll; //全部地址
  },
  methods: {
    //确定 赋值
    confirm (val) {
      this.addressArr.pca = val[0].name + "-" + val[1].name + "-" + val[2].name;
      this.addressArr.sa_province = val[0].code
      this.addressArr.sa_city = val[1].code
      this.addressArr.sa_area = val[2].code
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
    submit () {
      if (this.addressArr.sa_name.length <= 0) {
        this.$toast.fail("请输入收件人名称")
        return;
      }
      var myreg = /^(13[0-9]|14[01456879]|15[0-3,5-9]|16[2567]|17[0-8]|18[0-9]|19[0-3,5-9])\d{8}$/;
      if (this.addressArr.sa_mobile.length <= 0) {
        this.$toast.fail('请输入手机号码');
        return false
      } else if (!myreg.test(this.addressArr.sa_mobile)) {
        this.$toast.fail('请输入正确的手机格式');
        return false
      }

      if (this.addressArr.pca.length <= 0) {
        this.$toast.fail("请选择省市区")
        return;
      }
      if (this.addressArr.sa_address.length <= 0) {
        this.$toast.fail("请输入详细地址")
        return;
      }
      const obj = {}
      obj.sa_id = this.addressArr.sa_id;
      obj.province = this.addressArr.sa_province;
      obj.city = this.addressArr.sa_city;
      obj.area = this.addressArr.sa_area;
      obj.name = this.addressArr.sa_name;
      obj.mobile = this.addressArr.sa_mobile;
      obj.address = this.addressArr.sa_address;
      obj.default = this.addressArr.sa_default; //设置为默认收获地址
      this.$http
        .post(update_address, obj)
        .then(({ data }) => {
          this.$toast.success(data.message)
        })
      // this.$router.back()

    },
    //删除
    handleDelete () {
      //如果删除的是默认地址 设置第一条为默认 或第二条
      if (this.addressArr.sa_default == 1) {
        let objOne = {};
        let index = 0;
        // if (this.addressAll[0].sa_default !== 1) { index = 0 } else { index = 1 }
        objOne.sa_id = this.addressAll[index].sa_id;
        objOne.province = this.addressAll[index].sa_province;
        objOne.city = this.addressAll[index].sa_city;
        objOne.area = this.addressAll[index].sa_area;
        objOne.name = this.addressAll[index].sa_name;
        objOne.mobile = this.addressAll[index].sa_mobile;
        objOne.address = this.addressAll[index].sa_address;
        objOne.default = 1; //设置为默认收获地址
        this.$http
          .post(update_address, objOne)
          .then(({ data }) => {
            this.$toast.success(data.message)
          })
      }
      let objTwo = {}
      objTwo.sa_id = this.addressArr.sa_id
      this.$http
        .post(delete_address, objTwo)
        .then(({ data }) => {
          this.$toast.success(data.message)
          this.$router.back()
        })
    }
  }
}
</script>
<style lang="scss" scoped>
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
        color: #FC4D74;
      }
    }
  }
}
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
        font-size: 12px;
        text-align: center;
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
        height: 70%;
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
}
.operation {
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
  background: #FF461E;
}
.save {
  bottom: 100px;
}
.delete {
  background-image: none;
  background: #d0d0d0;
}
</style>  