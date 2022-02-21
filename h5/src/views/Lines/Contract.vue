<template>
  <div class="contract">
    <top-header :info="info"></top-header>
    <div class="content">
      <div class="list">
        <ul>
          <li class="details" v-for="(item, index) in items" :key="index">
            <van-image
              :src="item"
              lazy-load
              width="100%"
              height="490"
              @click="showImagePreview(item, index)"
            />
          </li>
        </ul>
      </div>
      <div class="save" v-if="autoType == 1">
        <button @click="deposit">下载并保存合同</button>
      </div>
      <div class="cont" v-if="autoType == 0">
        <div class="cont-list">
          <div class="cont-list-title">
            <vue-esign
              ref="esign"
              :isCrop="isCrop"
              :width="360"
              :height="114"
              :lineWidth="lineWidth"
              :lineColor="lineColor"
              :bgColor.sync="bgColor"
            ></vue-esign>
          </div>
        </div>
        <div class="lines">请在框内签名</div>
        <div class="cont-button">
          <button @click="handleReset">清除</button>
          <button @click="handleGenerate">完成</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ImagePreview } from 'vant';
import { order_contract, submit_auto } from "@/http/api.js";
export default {
  name: "contract",
  inject: ['reload'],
  data() {
    return {
      info: {
        isBack: true,
        exit: true,
      },
      lineWidth: 2,
      lineColor: "#000000",
      bgColor: "",
      resultImg: "",
      isCrop: false,
      items: [],
      dataOption: {
        order_id: '',
        order_type: '',
        autograph: '',
      },
      autoType: "",
      arrImg: [],
    };
  },
  methods: {
    // 下载保存合同
    deposit() {
      let dataImg = "";
      let images = this.items;
      let _this = this;
      this.$toast.loading({
        duration: 0, // 持续展示 toast
        forbidClick: true,
        loadingType: 'spinner',
        overlay: true,
        message: "保存中..."
      });
      for (let i = 0; i < images.length; i++) {
        var index = images[i].lastIndexOf(".");
        var ext = images[i].substr(i + 1);
        this.getUrlBase64(images[i], ext, function (base64) {
          _this.$toast.clear();
          dataImg = base64;
          if(_this.$platform == "android") {
            apps.download(dataImg);
          }else {
            console.log(dataImg);
          }
            
        });
      }
    },
    getUrlBase64(url, ext, callback) {
      var canvas = document.createElement("canvas"); //创建canvas DOM元素
      var ctx = canvas.getContext("2d");
      var img = new Image;
      img.crossOrigin = 'Anonymous';
      // img.setAttribute('crossOrigin', '*')
      // img.setAttribute('crossOrigin', 'anonymous');
      img.src = url;
      img.onload = () => {
        canvas.width = img.width;//指定画板的宽度，自定义
        canvas.height = img.height; //指定画板的高度,自定义
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height); //参数可自定义
        var dataURL = canvas.toDataURL(ext);
        callback.call(this, dataURL); //回掉函数获取Base64编码
        canvas = null;
      };
    },

    sub() {
      this.$http.post(submit_auto, this.dataOption).then(({ data }) => {
        if(data.code == 10000) {
          this.$toast(data.message);
          setTimeout(() => {
            this.reload();
          },1500)
        }else {
          this.$toast(data.message);
        }
      })
    },
    onChange(index) {
      this.index = index;
    },
    // 图片放大
    showImagePreview (images, index) {
      let _this = this;
      ImagePreview({
        images: this.items,
        showIndex: true,
        startPosition: index,
        closeOnPopstate: true,
      })
    },
    //清空画板..
    handleReset() {
      this.$refs.esign.reset();
      this.dataOption.autograph = "";
    },
    //生成签名图片..
    handleGenerate() {
      this.$refs.esign.generate().then(res => {
        this.dataOption.autograph = res;
        if(this.dataOption.autograph != "") {
          this.sub();
        }
      }).catch(err => {
        // this.$toast(err);
        this.$toast("请在框内签名");
      })
    },
    _list() {
      this.$http.post(order_contract, this.dataOption).then(({ data }) => {
        if(data.code == 10000) {
          this.items = data.result.contract_list;
          this.autoType = data.result.contract_status;
        }
      });
    }
  },
  created() {
    if(this.$platform == 'android') {
      let urlId = window.location.hash;
      urlId = urlId.split("?id=");
      urlId = urlId[1].split("&type=");
      this.dataOption.order_id = urlId[0];
      this.dataOption.order_type = urlId[1];
    }else {
      this.dataOption.order_id = "1";
      this.dataOption.order_type = "1";
    };
    this._list();
  },
};
</script>

<style lang="scss" scoped>
/deep/ header {
  background: #18181a;
  i {
    color: #fff;
  }
}
.content {
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  width: auto;
  overflow: auto;
  background: #18181a;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang SC";
  font-size: 15px;
  color: #ffffff;
  z-index: 2;
  > .list {
    padding: 0 15px;
    box-sizing: border-box;
    margin-bottom: 245px;
    .details {
      width: 100%;
      height: 460px;
      box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.16);
      opacity: 1;
      > img {
        width: 100%;
        height: 100%;
        border-radius: 8px;
      }
    }
  }
  .cont {
    height: 240px;
    width: 100%;
    position: fixed;
    bottom: 0;
    background: #18181a;
    z-index: 3;
    padding: 0 15px;
    box-sizing: border-box;
    > .cont-list {
      > .cont-list-title {
        width: 100%;
        height: 110px;
        border: 1px dashed #000;
        box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.16);
        border-radius: 8px;
        background: #fff;
        margin-top: 5px;
      }
    }
    > .lines {
      font-size: 14px;
      color: #fff;
      text-align: center;
      margin-top: 10px;
    }
    > .cont-button {
      display: flex;
      justify-content: space-around;
      margin-top: 20px;
      > button {
        height: 38px;
        width: 120px;
        border-radius: 8px;
        font-size: 16px;
        font-family: "PingFang SC";
        font-weight: 500;
        color: #fff;
        background: #D67515;
        border: none;
        outline: none;
      }
      > button:nth-child(2) {
        background: #F7BA34;
      }
    }
  }
  .save {
    position: relative;
    bottom: 138px;
    left: 0;
    right: 0;
    text-align: center;
    width: 86%;
    margin: 0 auto;
    > button {
      outline: none;
      font-size: 14px;
      border-radius: 8px;
      padding: 14px 0;
      background: #F9C755;
      color: #fff;
      border: none;
      font-weight: bold;
      width: 100%;
    }
  }
}


</style>