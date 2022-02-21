<template>
  <div class="integral">
    <TopHeader :info="info"></TopHeader>
    <div class="content">
      <div class="top">
        <div class="box">
          <p>好友账户ID</p>
          <input type="text" placeholder="输入好友ID号" v-model="id" />
          <p>好友手机/邮箱</p>
          <input type="text" placeholder="输入好友手机/邮箱" v-model="phone" />
          <p>互转数量</p>
          <input type="text" placeholder="输入互转数量" v-model="num" />
          <p class="num">预约池余额:{{ usdt }}Y令牌</p>
          <span>Y令牌</span>
        </div>
      </div>
      <div class="submitbutton" @click="showToast">确定</div>
      <!-- 遮罩层  推进-->
      <van-overlay :show="showPop">
        <div class="wrapper">
          <div class="block">
            <img
              src="../../assets/rocket/cwu-img.png"
              @click="showPop = false"
              class="close"
            />
            <p class="title">请输入交易密码</p>
            <input type="password" placeholder="输入交易密码" v-model="pwd" maxlength="6" />
            <div class="codebox">
              <input type="text" placeholder="输入验证码" v-model="code" />
              <div @click="getCode" v-show="show">获取验证码</div>
              <div v-show="!show">{{ count }}s后重试</div>
            </div>
            <div class="button" @click="submit">确定</div>
          </div>
        </div>
      </van-overlay>
    </div>
  </div>
</template>
<script>
import {
  ark_subscribe_transfer, sendSms
} from "@/http/api.js";
export default {
  data () {
    return {
      info: {
        isBack: true,
        title: '划转',
      },
      infoArr: [],
      id: '',
      phone: '',
      num: '',
      pwd: '',
      code: '',
      show: true,
      showPop: false,
      count: '',
      timer: null,
      usdt: this.$route.query.num,
    };
  },
  created () {

  },
  methods: {
    showToast () {
      if (this.id == '') {
        this.$toast.fail('请输入好友ID号');
        return;
      } if (this.phone == '') {
        this.$toast.fail('请输入好友手机/邮箱');
        return;
      }
      if (this.num == '') {
        this.$toast.fail('请输入互转数量');
        return;
      }
      this.showPop = true;
    },
    //获取验证码
    getCode () {
      const TIME_COUNT = 60;
      if (!this.timer) {
        this.count = TIME_COUNT;
        this.show = false;
        this.timer = setInterval(() => {
          if (this.count > 0 && this.count <= TIME_COUNT) {
            this.count--;
          } else {
            this.show = true;
            clearInterval(this.timer);
            this.timer = null;
          }
        }, 1000)
      }
      this.$http
        .post(sendSms)
        .then(({ data }) => {
          if (data.code == 10000) {
            this.$toast.success(data.message)
          } else {
            this.$toast.fail(data.message)
          }
        })
    },
    submit () {
      if (this.pwd.length <= 0) {
        this.$toast.fail("请输入真实姓名")
        return;
      }
      if (this.code.length <= 0) {
        this.$toast.fail("请输入短信验证码")
        return;
      }
      let obj = {}
      obj.currency_id = 105,
        obj.target_user_id = this.id,
        obj.target_account = this.phone,
        obj.num = this.num;
      obj.paypwd = this.pwd;
      obj.phone_code = this.code
      this.$http
        .post(ark_subscribe_transfer, obj)
        .then(({ data }) => {
          if (data.code = 10000) {
            this.$toast.success(data.message)
            //1.5秒后跳转上一页
            setTimeout(() => {
              this.$router.back()
            }, 1500)
          } else {
            this.$toast.fail(data.message)
          }
        })
    }

  },


}
</script>
<style lang="scss" scoped>
/deep/ header {
  background: #FDFDFD;
  color: #0f0f0f;
  i {
    color: #0f0f0f;
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
  background: #FDFDFD;
  font-size: 16px;
  font-family: "PingFang SC";
  -webkit-overflow-scrolling: touch;
  // display: flex;
  // flex-direction: column;
  // align-items: center;
  // 遮罩层样式
  .wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    .block {
      position: relative;
      width: 326px;
      height: 350px;
      border-radius: 16px;
      background-color: #2c281c;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 0 20px;
      padding-top: 40px;
      box-sizing: border-box;
      .close {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 25px;
        height: 25px;
      }
      .title {
        color: white;
        font-size: 21px;
        font-weight: bolder;
      }
      input {
        // 清除input样式
        -webkit-appearance: none;
        -moz-appearance: none;
        outline: 0;
        border: none;
        margin-top: 30px;
        height: 42px;
        width: 100%;
        line-height: 42px;
        background: #ffffff;
        border-radius: 4px;
        padding-left: 10px;
        padding-right: 50px;

        box-sizing: border-box;
      }
      // 更改样式placeholder
      input::-webkit-input-placeholder {
        color: #b5b5b5;
        font-size: 14px;
      }
      .codebox {
        display: flex;
        align-items: center;
        margin-top: 30px;
        background: #fff;
        border-radius: 4px;
        input {
          width: 50%;
          border: none;
          background: none;
          margin-top: 0;
        }
        div {
          width: 50%;
          font-size: 14px;
          text-align: center;
          color: #151515;
        }
      }
      .button {
        margin-top: 50px;
        width: 280px;
        height: 48px;
        line-height: 48px;
        text-align: center;
        color: white;
        font-size: 16px;
        background: linear-gradient(90deg, #e9d8a8 0%, #b89a67 100%);
        border-radius: 24px;
      }
    }
  }
  .top {
    display: flex;
    justify-content: center;
    position: relative;
    top: -2px;
    height: 144px;
    // background: url("../../assets/shop/my/bj-img.png") no-repeat center;
    // background-size: 100% 100%;
    .box {
      position: relative;
      display: flex;
      flex-direction: column;
      height: 385px;
      width: 100%;
      padding: 0 25px;
      padding-bottom: 24px;
      box-sizing: border-box;
      color: #2C2C2C;
      border-radius: 10px;
      p {
        margin-top: 20px;
        color: #2C2C2C;
        font-size: 14px;
      }
      .num {
        color: #a8a8a8;
        font-size: 12px;
      }
      input {
        // 清除input样式
        -webkit-appearance: none;
        -moz-appearance: none;
        outline: 0;
        border: none;
        margin-top: 10px;
        height: 46px;
        line-height: 46px;
        background: #EDEDED;
        border-radius: 4px;
        padding-left: 10px;
        padding-right: 50px;
        color: #151515;
        box-sizing: border-box;
      }
      // 更改样式placeholder
      input::-webkit-input-placeholder {
        color: #b5b5b5;
        font-size: 14px;
      }
      .phone {
        margin-top: 10px;
        padding-left: 10px;
        box-sizing: border-box;
        height: 36px;
        line-height: 36px;
        border: 1px solid #e7cbb2;
        background: rgba(226, 199, 174, 0.35);
        border-radius: 4px;
        color: #ffffff;
      }
      .codebox {
        display: flex;
        align-items: center;
        margin-top: 10px;
        border: 1px solid #e7cbb2;
        background: rgba(226, 199, 174, 0.35);
        border-radius: 4px;
        input {
          width: 50%;
          border: none;
          background: none;
          margin-top: 0;
        }
        div {
          width: 50%;
          font-size: 14px;
          text-align: center;
          color: #8f2c27;
        }
      }
      span {
        position: absolute;
        bottom: 110px;
        right: 35px;
        z-index: 3;
        color: #272727;
      }
    }
  }
  .submitbutton {
    margin: 0 auto;
    margin-top: 280px;
    width: 345px;
    height: 50px;
    background: #FF461E;
    line-height: 50px;
    border-radius: 8px;
    text-align: center;
    color: #fff;
    font-size: 13px;
  }
}
</style>  