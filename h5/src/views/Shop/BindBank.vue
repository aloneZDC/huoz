<template>
  <div class="integral">
    <TopHeader :info="info"></TopHeader>
    <div class="content">
      <div class="top">
        <div class="box">
          <p>真实姓名</p>
          <input type="text" placeholder="持卡人姓名" v-model="name" />
          <p>绑定银行卡</p>
          <input
            type="text"
            placeholder="持卡人本人银行卡号"
            v-model="bankcard"
          />
          <p>银行开户行</p>
          <div class="bankList">
            <div class="bankArrow" @click="showPicker = true">
              <i class="iconfont icon-return"></i>
            </div>
            <van-field
              readonly
              clickable
              name="picker"
              :value="value"
              placeholder="点击选择银行"
              @click="showPicker = true"
            />
            <van-popup v-model="showPicker" position="bottom">
              <van-picker
                show-toolbar
                :columns="columns"
                @confirm="onConfirm"
                @cancel="showPicker = false"
              />
            </van-popup>
          </div>
          <p>银行开户支行</p>
          <input type="text" placeholder="输入开户行" v-model="accountName" />
          <p>手机号/邮箱</p>
          <div class="phone">{{ infoArr.phone ? infoArr.phone : infoArr.email  }}</div>
          <p>验证码</p>
          <div class="codebox">
            <input type="text" placeholder="输入验证码" v-model="code" />
            <div @click="getCode" v-show="show">获取验证码</div>
            <div v-show="!show">{{ count }}s后重试</div>
          </div>
        </div>
      </div>

      <div class="submitbutton" @click="submit">立即绑定</div>
    </div>
  </div>
</template>
<script>
import {
  memberinfo, WeChatBind, sendSms, BankBind, banklist
} from "@/http/api.js";
export default {
  data () {
    return {
      info: {
        isBack: true,
        title: '银行卡账号',
        background: 'headerBgcolor'
      },
      infoArr: [],
      name: '',
      bankcard: '',
      phone: '',
      code: '',
      show: true,
      count: '',
      timer: null,
      accountName: '',
      columns: [], // 银行卡信息
      value: '',
      showPicker: false,
      bankId: "",
      
    };
  },
  created () {
    this.getInfo();
    this.infos();
  },
  methods: {
    // onChange(picker, value, index) {
    //   console.log(`当前值：${value.text}, 当前id：${value.classId}`);
    // },
    onConfirm(value, index) {
      this.value = value.text;
      // 银行ID
      this.bankId = value.classId;
      this.showPicker = false;
    },
    infos() {
      this.$http.post(banklist).then(({ data })=> {
        this.columns = data.result.map(item => {
          const classId = item.id;
          const text = item.name;
          return {
            //必须用text变量表示选择器中的选项，其他的没有要求
            text,        
            classId
          };
        })
      })
    },
    //用户基本信息
    getInfo () {
      this.$http
        .post(memberinfo)
        .then(({ data }) => {
          const { member } = data.result
          this.infoArr = member;
        })
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
      if (this.name.length <= 0) {
        this.$toast.fail("请输入真实姓名")
        return;
      }
      if(this.bankId.length <= 0) {
        this.$toast.fail("请选择开户行")
        return;
      }
      if (this.accountName.length <= 0) {
        this.$toast.fail("请输入开户支行")
        return;
      }
      const pattern = /^([1-9]{1})(\d{15}|\d{16}|\d{18})$/
      if (!pattern.test(this.bankcard)) {
        this.$toast.fail("请输入正确的银行卡号")
        return;
      }
      if (this.code.length <= 0) {
        this.$toast.fail("请输入短信验证码")
        return;
      }
      let obj = {}
      obj.actual_name = this.name;
      obj.open_bank = this.accountName;
      obj.bank_card = this.bankcard;
      obj.phone_code = this.code;
      obj.bank_name = this.bankId;
      this.$http
        .post(BankBind, obj)
        .then(({ data }) => {
          if (data.code == 10000) {
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
  background: #ff461e;
  color: #fff;
  i {
    color: #fff;
  }
}
/deep/ .van-cell {
  padding: 5px 10px;
  border: 1px solid #ff461e;
  border-radius: 4px;
}
.content {
  z-index: 2;
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  overflow: auto;
  background: #ffffff;
  font-size: 16px;
  font-family: "PingFang SC";
  -webkit-overflow-scrolling: touch;
  // display: flex;
  // flex-direction: column;
  // align-items: center;
  .top {
    display: flex;
    justify-content: center;
    position: relative;
    // top: -2px;
    // height: 144px;
    // background: url("../../assets/shop/my/bj-img.png") no-repeat center;
    // background-size: 100% 100%;
    .box {
      display: flex;
      flex-direction: column;
      // position: absolute;
      // top: 33px;
      // height: 450px;
      width: 330px;
      padding: 0 25px;
      padding-bottom: 24px;
      box-sizing: border-box;
      color: #353333;
      border-radius: 10px;
      background-color: #fff;
      .bankList {
        position: relative;
        margin-top: 10px;
        .bankArrow {
          position: absolute;
          transform: rotateY(205deg);
          right: 5px;
          z-index: 999;
          top: 9px;
          > i {
            font-size: 18px;
            color: #000;
          }
        }
      }
      p {
        margin-top: 20px;
        color: #353333;
        font-size: 14px;
      }
      input {
        // 清除input样式
        -webkit-appearance: none;
        -moz-appearance: none;
        outline: 0;
        border: none;
        margin-top: 10px;
        height: 36px;
        line-height: 36px;
        border: 0.5px solid #ff461e;
        border-radius: 4px;
        padding-left: 10px;
        box-sizing: border-box;
      }
      // 更改样式placeholder
      input::-webkit-input-placeholder {
        color: #d2e1e1;
        font-size: 14px;
      }
      .phone {
        margin-top: 10px;
        padding-left: 10px;
        box-sizing: border-box;
        height: 36px;
        line-height: 36px;
        border: 0.5px solid #ff461e;

        border-radius: 4px;
        color: #052c2c;
      }
      .codebox {
        display: flex;
        align-items: center;
        margin-top: 10px;
        border: 0.5px solid #ff461e;

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
          color: #FF461E;
        }
      }
    }
  }
  .submitbutton {
    margin: 0 auto;
    margin-top: 20px;
    margin-bottom: 40px;
    width: 325px;
    height: 39px;
    line-height: 39px;
    border-radius: 23px;
    background: #FF461E;
    text-align: center;
    color: #fff;
    font-size: 18px;
  }
}
</style>  