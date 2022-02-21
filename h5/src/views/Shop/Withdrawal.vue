<template>
  <div class="integral">
    <TopHeader :info="info"></TopHeader>
    <div class="content">
      <div class="top">
        <div class="box br">
          <div class="boxtitle">
            <p>可出仓赠与收益</p>
          </div>
          <div class="boxmin">
            <div class="boxmin-number">{{ balance - 0 }}</div>
          </div>
          <div class="number-show">
            <input
              class="number-input"
              :placeholder="`请输入数量,${minNum}起`"
              maxLength="12"
              v-model="takeAmount"
            />
            <p class="icon">
              赠与收益 | <span @click="takeAmount = balance - 0">全部</span>
            </p>
          </div>
          <img src="../../assets/shop/my/down.png" />
          <div class="number-show">
            {{ (CNum * hm_price).toFixed(2) }}
            <p class="icon">积分</p>
          </div>
          <p class="txt">当前汇率1赠与收益≈{{ hm_price }}积分</p>
        </div>
      </div>
      <div class="calculate br" v-show="CNum > 0">
        <p>出仓费：{{ CNum * withdraw_fee / 100 }}赠与收益</p>
        <p>实际扣款：{{ CNum }}赠与收益</p>
        <p>
          对方到账：{{ (CNum - (CNum * withdraw_fee / 100)).toFixed(2) }}赠与收益≈{{
            ((CNum - (CNum * withdraw_fee / 100)) * hm_price).toFixed(2)
          }}
          积分
        </p>
      </div>
      <!-- <div class="tableBox">
        <p :class="{ tableActive: active == 0 }" @click="active = 0">
          银行卡提现
        </p>
        <p :class="{ tableActive: active == 1 }" @click="active = 1">
          微信提现
        </p>
      </div> -->
      <!-- 微信提现区域 -->
      <div class="wechatBox br" v-if="!ifWechat && active == 1">
        <div class="flexBox">
          <img src="../../assets/shop/my/gxianz_icon4.png" />
          <p>提现到微信钱包</p>
          <div class="button" @click="$router.push('binding')">绑定</div>
        </div>
      </div>
      <div class="wechatBox br already" v-if="ifWechat && active == 1">
        <div class="flexBox">
          <img src="../../assets/shop/my/gxianz_icon4.png" />
          <p>提现到微信钱包</p>
          <div class="button disable">已绑定</div>
        </div>
        <p class="wechatData">微信账户：{{ wechatInfo.wechat_account }}</p>
        <p class="wechatData">真实姓名：{{ wechatInfo.actual_name }}</p>
      </div>
      <!-- 银行卡区域 -->
      <div class="wechatBox br" v-if="!ifbank && active == 0">
        <div class="flexBox">
          <p>出仓到银行卡</p>
          <div class="button" @click="$router.push('bindbank')">绑定</div>
        </div>
      </div>
      <div class="wechatBox br already" v-if="ifbank && active == 0">
        <div class="flexBox">
          <p>提现到银行卡</p>
          <div class="button" @click="$router.push('bindbank')">修改绑定</div>
        </div>
        <p class="wechatData">银行卡号：{{ bank_info.bank_card }}</p>
        <p class="wechatData">真实姓名：{{ bank_info.actual_name }}</p>
        <p class="wechatData">开户行：{{ bank_info.open_bank }}</p>
      </div>
      <div class="promptText">
        提示：现在申请出仓，系统审核后，预计T+1个工作日到账。提现时间为上午9：30-下午17：30，周六周日休息。
      </div>
      <div class="submitbutton" @click="submitWchat">确定出仓</div>
      <!-- 遮罩层-->
      <van-overlay :show="showCode">
        <div class="wrapper" @click.stop @click="show = false">
          <div class="code">
            <img
              src="../../assets/shop/my/cwu-img.png"
              @click="showCode = false"
              class="close"
            />
            <p class="popTitle">请输入安全验证</p>
            <div class="codebox">
              <input type="password" placeholder="输入交易密码" v-model="pwd" maxlength="6" />
            </div>
            <div class="codebox">
              <input type="text" placeholder="输入验证码" v-model="code" />
              <div @click="getCode" v-show="codeShow">获取验证码</div>
              <div v-show="!codeShow">{{ count }}s后重试</div>
            </div>
            <div class="button" @click="handeleSubmit">确定</div>
          </div>
        </div>
      </van-overlay>
    </div>
  </div>
</template>
<script>
import {
  WithdrawPage, WithdrawSubmit, sendSms
} from "@/http/api.js";
export default {
  data () {
    return {
      info: {
        isBack: true,
        exit: true,
        title: '出仓',
        isRight: true,
        url: '/withdrawalDet',
        content: '记录',
      },
      takeAmount: '',
      wechatInfo: {},
      ifWechat: false,
      withdraw_fee: 0,
      wxid: 0,
      balance: 0,
      showCode: false,
      hm_price: 0,
      active: 0,
      ifbank: false,
      bank_info: {},
      codeShow: true,
      code: '',
      timer: null,
      count: '',
      pwd: '',
      minNum:'',
    };
  },
  watch: {
    'takeAmount': {
      handler (nval, oval) {
        var re = /^[0-9]+.?[0-9]*/
        if (!re.test(nval)) {
          this.$toast.fail("请输入合适的金额");
          this.CNum = 0;
        } else {
          this.CNum = Number(nval);
        }
      },
      deep: true,
      immediate: true
    }
  },
  created () {
    this.getWechatInfo();
  },
  methods: {
    // calculate(){
    //   if (
    //     this.takeAmount == "00" ||
    //     this.takeAmount.substring(0, 1) == ""
    //   ) {
    //     this.takeAmount = '';
    //   } else {
    //     this.takeAmount = this.takeAmount.replace(/[^0-9]/g, "");
    //   }
    //   this.cny = this.takeAmount * 
    // },
    getWechatInfo () {
      this.$http
        .post(WithdrawPage)
        .then(({ data }) => {
          const { result } = data
          if (result.wechat_info) {
            this.ifWechat = true;
            this.wechatInfo = result.wechat_info;
          }
          if (result.bank_info) {
            this.bank_info = result.bank_info;
            this.ifbank = true;
          }
          this.withdraw_fee = result.withdraw_fee; //手续费
          this.balance = result.balance;//余额
          this.hm_price = result.hm_price;//比例
          this.minNum = Number(result.currency_min_tibi) - 0;
          // this.num = this.withdraw_fee == 0 ? 0 : this.withdraw_fee.split('%')[0] * 0.01;
        })
    },
    submitWchat () {
      if (this.CNum.length <= 0) {
        this.$toast.fail("请输入提现金额")
        return;
      }
      if (this.CNum <= 0) {
        this.$toast.fail("请输入合适的金额")
        return;
      }
      if (Number(this.CNum > this.balance)) {
        this.$toast.fail("余额不足")
        return;
      }
      if (Number(this.CNum < this.minNum)) {
        this.$toast.fail(`最低数量${this.minNum}赠与收益起`)
        return;
      }
      if (!this.ifWechat && this.active == 1) {
        this.$toast.fail("请绑定微信")
        return;
      }
      if (!this.ifbank && this.active == 0) {
        this.$toast.fail("请绑定银行卡")
        return;
      }
      this.showCode = true;
    },
    handeleSubmit () {
      if (this.pwd.length <= 0) {
        this.$toast.fail('请输入交易密码');
        return;
      }
      if (this.code.length <= 0) {
        this.$toast.fail('请输入验证码');
        return;
      }
      let obj = {};
      if (this.active == 0) {
        obj.type = 1;
        obj.wxid = this.bank_info.id;
      } else {
        obj.type = 2;
        obj.wxid = this.wechatInfo.wxid;
      }
      obj.number = this.CNum;
      obj.phone_code = this.code;
      obj.paypwd = this.pwd;
      this.$http
        .post(WithdrawSubmit, obj)
        .then(({ data }) => {
          window.toast_txt(data.message);
          if (data.code == "10000") {
            this.takeAmount = 0;
            setTimeout(() => {
              this.getWechatInfo();
              this.showCode = false;
            }, 1000)
          }
        })
    },
    getCode () {
      const TIME_COUNT = 60;
      if (!this.timer) {
        this.count = TIME_COUNT;
        this.codeShow = false;
        this.timer = setInterval(() => {
          if (this.count > 0 && this.count <= TIME_COUNT) {
            this.count--;
          } else {
            this.codeShow = true;
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
  }
}
</script>
<style lang="scss" scoped>
/deep/ header {
  background: #ff461e;
  color: #fff;
  i {
    color: #fff;
  }
  .right_btn {
    button {
      color: #fff;
      font-size: 14px;
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
  background: #f7f5f6;
  font-size: 16px;
  font-family: "PingFang SC";
  -webkit-overflow-scrolling: touch;
  // 遮罩层样式
  .wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    .block {
      width: 326px;
      height: 350px;
      padding: 0 20px;
      box-sizing: border-box;
      border-radius: 12px;
      background-color: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      box-sizing: border-box;
      p {
        font-size: 16px;
        color: #1c1c1c;
        margin-top: 10px;
      }
      .popTitle {
        font-size: 20px;
        font-weight: bold;
      }
    }
    .code {
      position: relative;
      width: 345px;
      height: 350px;
      border-radius: 16px;
      background-color: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      .close {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 25px;
        height: 25px;
      }
      .popTitle {
        margin-top: 45px;
        font-size: 21px;
        color: #353333;
        font-weight: bold;
      }
      .codebox {
        display: flex;
        align-items: center;
        margin-top: 40px;
        border: 1px solid rgba(255, 70, 30, 0.5529411764705883);
        border-radius: 4px;
        background: rgba(255, 70, 30, 0.09);
        width: 280px;
        height: 42px;
        line-height: 42px;
        input {
          width: 50%;
          border: none;
          background: none;
          margin-top: 0;
          padding-left: 10px;
          box-sizing: border-box;
        }
        div {
          width: 50%;
          font-size: 14px;
          text-align: right;
          padding-right: 5px;
          box-sizing: border-box;
          color: #8f2c27;
        }
      }
      .button {
        margin-top: auto;
        min-width: 280px;
        height: 48px;
        line-height: 48px;
        padding: 0 5px;
        text-align: center;
        background: #ff461e;
        border-radius: 24px;
        font-size: 16px;
        color: #ffffff;
        margin-bottom: 15px;
      }
    }
  }
  .top {
    display: flex;
    justify-content: center;
    position: relative;
    top: -2px;
    height: 144px;
    background-color: #ff461e;
    .box {
      display: flex;
      flex-direction: column;
      height: 270px;
      width: 330px;
      padding: 0 25px;
      box-sizing: border-box;
      color: #353333;
      position: absolute;
      top: 33px;
      .boxtitle {
        display: flex;
        align-items: center;
        margin-top: 15px;

        img {
          width: 14px;
          height: 14px;
        }
        p {
          margin-left: 5px;
          font-size: 14px;
          color: #c3c3c3;
        }
      }

      .boxmin {
        margin-top: 7px;
        font-size: 20px;
        font-weight: bold;
      }
      .number-show {
        display: flex;
        justify-content: space-between;
        margin-top: 10px;
        padding-left: 10px;
        box-sizing: border-box;
        width: 100%;
        height: 38px;
        line-height: 38px;
        border: 1px solid #fd8aa3;
        border-radius: 4px;
        font-size: 14px;
        .number-input {
          // 清除input样式
          -webkit-appearance: none;
          -moz-appearance: none;
          outline: 0;
          border: none;
          // width: 170px;
          height: 34px;
          line-height: 34px;
          width: 40%;
        }
        // 更改样式placeholder
        input::-webkit-input-placeholder {
          color: #d2e1e1;
        }
        .icon {
          margin-right: 10px;
          font-size: 14px;
          text-align: right;
          line-height: 38px;
          color: #353333;
          span {
            color: #ff461e;
          }
        }
      }
      img {
        margin: 17px auto;
        width: 20px;
        height: 20px;
      }
      .txt {
        margin-top: 10px;
        width: 100%;
        text-align: center;
        font-size: 13px;
        color: #818181;
      }
      .prompt {
        margin-top: 3px;
        padding-left: 10px;
        box-sizing: border-box;
        width: 100%;
        height: 38px;
        line-height: 38px;
        background: url("../../assets/shop/my/gxianz_bg_sxf.png") no-repeat
          center;
        background-size: 100% 100%;
        color: #94d1d4;
        font-size: 12px;
      }
      .actual {
        color: #353333;
        margin-top: 18px;
        font-size: 14px;
        display: inline-block;
        span {
          margin-left: 5px;
          color: #fb3f69;
        }
      }
    }
  }
  .br {
    border-radius: 10px;
    background-color: #fff;
  }
  .calculate {
    width: 330px;
    height: 104px;
    margin: 0 auto;
    margin-top: 180px;
    display: flex;
    justify-content: center;
    flex-direction: column;
    padding: 10px 25px;
    padding-right: 0px;
    box-sizing: border-box;
  }
  .titleBox {
    font-size: 16px;
    color: #707070;
    width: 300px;
    margin: 0 auto;
    margin-top: 20px;
    text-align: center;
    span {
      margin: 0 10px;
      color: #1a1a1a;
    }
  }
  .tableBox {
    width: 218px;
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
      color: #e7cbb2;
      border: 1px solid #e7cbb2;
    }
  }
  .wechatBox {
    width: 330px;
    height: 70px;
    margin: 0 auto;
    margin-top: 10px;
    padding: 0 25px;
    box-sizing: border-box;
    border: 1px solid #f86d4f;

    .flexBox {
      height: 100%;
      width: 100%;
      display: flex;
      align-items: center;
      img {
        width: 22px;
        height: 22px;
      }
      p {
        margin-left: 10px;
        color: #353333;
        font-weight: bold;
        font-size: 14px;
      }
      .button {
        margin-left: auto;
        width: 72px;
        min-width: 60px;
        height: 27px;
        line-height: 27px;
        padding: 0 5px;
        text-align: center;
        background: linear-gradient(180deg, #fff7f6 0%, #f86d4f 100%);
        border-radius: 4px;
        color: #fff;
        font-size: 14px;
      }
    }
  }
  .already {
    height: 117px;
    .flexBox {
      height: 55px;
      .disable {
        background: none;
        background-color: #b6b6b6;
        color: #ffffff;
      }
    }
    .wechatData {
      margin-left: 30px;
      color: #666666;
      font-size: 14px;
    }
  }
  .promptText {
    margin: 0 auto;
    margin-top: 30px;
    color: #a2a2a2;
    font-size: 12px;
    width: 330px;
  }
  .submitbutton {
    margin: 0 auto;
    margin-top: 20px;
    margin-bottom: 30px;
    width: 330px;
    height: 44px;
    line-height: 44px;
    border-radius: 23px;
    background: #ff461e;
    text-align: center;
    color: #fff;
    font-size: 16px;
  }
}
</style>  