<template>
  <div class="customer">
    <header>
      <button class="iconfont iconfanhui back" @click="goBack">
        <i class="iconfont icon-return"></i>
      </button>
      <h3>{{ info.title }}</h3>
    </header>
    <div class="content">
      <!--<p class="promt">长时间客服没回复?试试下拉刷新</p>-->
      <div id="scrollWarp" ref="scrollWarp">
        <!-- <van-list
            v-model="isUpLoading"
            @load="onLoad"
            :finished = "finished"
            :offset="offset"
          > -->
          <ul ref="wrapper" class="wrapper" id="wrapper">
            <li v-for="(item, index) in items" :key="index">
              <div class="left_box" v-if="item._position == 'l'">
                <div class="left_userinfo">
                  <!-- <img :src="item.head" /> -->
                  <!-- <p>{{ item.nick }}</p> -->
                </div>
                <p>{{ item.msg_time }}</p>
                <div class="leftuser">
                  <p v-if="item.type == 'txt'">{{ item.msg_content }}</p>
                  <img
                    :src="item.msg_content"
                    class="small"
                    v-if="item.type == 'image'"
                    @click="glass(item.msg_content)"
                  />
                </div>
              </div>
              <div class="right_box" v-if="item._position == 'r'">
                <p>{{ item.msg_time }}</p>
                <div class="rightuser">
                  <p v-if="item.type == 'txt'">{{ item.msg_content }}</p>
                  <img
                    :src="item.msg_content"
                    class="small"
                    v-if="item.type == 'image'"
                    @click="glass(item.msg_content)"
                  />
                </div>
              </div>
            </li>
          </ul>
        <!-- </van-list> -->
      </div>
      <div class="bigimg" v-if="flag1" @click="reduce">
        <img :src="smallImgSrc" alt="" />
      </div>
      <div class="send_message">
        <textarea
          name=""
          id="message_val"
          cols="30"
          rows="10"
          placeholder="请输入..."
          v-model="msg_val"
        ></textarea>
        <div class="send_btn">
          <span @click="sendMsg">发送</span>
          <img :src="upimg_scr" @click="upDataImg" />
        </div>
        <input
          type="file"
          class="upload"
          hidden
          accept="image/*"
          ref="upload"
          @change="uploadFn"
        />
      </div>
    </div>
  </div>  
</template>

<script>
import { upload, sendMessage, getMessages } from '@/http/api.js'
export default {
  name: 'customer',
  components: {},
  data() {
    return {
      info: {
        title: '专属客服',
      },
      upimg_scr: require('../../assets/home/kefu_pic.png'),
      smallImgSrc: "", // 点击图片的地址
      msg_val: "",  // 发送消息内容
      items: [], 	  // 聊天内容列表
      flag1: false, // 点击图片放大标识
      listId: '',
      timer: null,
      typeShow: 1,
    }
  },
  beforeDestroy() {
    if(this.timer != null) {
      clearInterval(this.timer);
      this.timer = null;
    }
  },
  methods: {
    changTimer(_id, type) {
      if(type == 1) {
        this.onList(_id);
      }
      if (this.timer == null) {
        this.timer = setInterval(() => {
          this.onList(this.listId);
        }, 4000)
      }
    },
    goBack() {
      if (this.$platform == "ios") {
        window.webkit.messageHandlers.iosAction.postMessage("exit");
      } else if (this.$platform == "android") {
        if(this.$route.query.id == 1) {
          this.$router.back();
        }else {
          apps.exit();
        }
      } else {
        this.$router.back();
      }
    },
    onList(_id) {
      let data = {
        msg_id: _id
      };
      this.$http
        .post(getMessages, data)
        .then(({ data }) => {
         if(data.message == "暂无数据") {
          return;
         } 
        if(data.code == 10000) {
          if(!this.listId) {
            if(this.typeShow == 1) {
              this.items = data.result;
              setTimeout(() => {
                this.$nextTick(() => {
                  this.items = data.result;
                  var container = this.$el.querySelector("#scrollWarp");
                  container.scrollTop = container.scrollHeight;
                  this.typeShow = 2;
                })
              }, 100);
            }else {
              this.items = data.result;
              return;
            }
          }
          if(this.items[this.items.length-1].msg_id == data.result[data.result.length-1].msg_id) {
            this.$nextTick(() => {
              this.items = this.items;
            })
          }else {
            this.listId = data.result[data.result.length-1].msg_id;
            this.items = this.items.concat(data.result);
          }
            setTimeout(() => {
              this.$nextTick(() => {
                var container = this.$el.querySelector("#scrollWarp");
                container.scrollTop = container.scrollHeight;
              })
            }, 100);
          }
        })
        .catch((err) => {
          this.items = [];
        });
    },
    //放大图片
    glass (src) {
      this.flag1 = true;
      this.smallImgSrc = src;
    },
    //点击放大的图片
    reduce () {
      this.flag1 = false;
    },
    upDataImg () {
			this.$refs.upload.click();
    },
    uploadFn () {
			let that = this;
      var file = this.$refs.upload.files[0];

			if(!file){
				return;
			}
      if (file.type.indexOf("image") != -1) {
        var reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = function (e) {
          if (this.result) {
            let datas = {
              img: this.result
            };
            that.$refs.upload.setAttribute('type','text'); //同一张图片可以重复传
            that.$refs.upload.setAttribute('type','file');
            if (that.timer != null) {
              clearInterval(that.timer)
              that.timer = null;
            };
            that.$http.post(upload, datas)
              .then(({
                data
              }) => {
                if (data.code == 10000) {
                  that.listId = that.items[that.items.length-1].msg_id;
                  that.onList(that.listId);
                  setTimeout(() => {
                      var container = that.$el.querySelector("#scrollWarp");
                      container.scrollTop = container.scrollHeight;
                      that.changTimer(that.listId, 2);
                  }, 100);
                } else {
                  that.$toast("网络请求失败");
                }
              })
          }
        };
      }
    },
    sendMsg () {
      if (this.msg_val != "") {
        let data = { msg_body: this.msg_val };
        if (this.timer != null) {
          clearInterval(this.timer)
          this.timer = null;
        };
        this.$http.post(sendMessage, data)
          .then(({data}) => {
            if (data.code == 10000) {
                setTimeout(() => {
                  this.$nextTick(() => {
                    var container = this.$el.querySelector("#scrollWarp");
                    container.scrollTop = container.scrollHeight;
                    this.changTimer(this.listId, 2);
                  })
                }, 100);
              this.msg_val = "";
              this.listId = this.items[this.items.length-1].msg_id;
              // 先获取再延迟轮询信息
              this.onList(this.listId);
            }
          })
          .catch(() => {
            // this.$toast("网络请求失败");
          })
      }
    },
  },
  created() {
    this.changTimer('', 1)
  }
}
</script>

<style lang="scss" scoped>
  header {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 44px;
    background: #fff;
    color: #000;
    text-align: center;
    line-height: 44px;
    display: flex;
    align-items: center;
    box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.16);
  }
  header button.back {
    position: absolute;
    top: 0;
    height: 44px;
    border: none;
    background: transparent;
    padding: 0;
    margin: 0;
    display: flex;
    align-items: center;
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
    bottom: 0;
    width: 100%;
    overflow: auto;
    background: #F7F5F6;
    -webkit-overflow-scrolling: touch;
    font-size: "PingFang TC";
  }
  #scrollWarp {
  background: #F7F5F6;
  width: 100%;
  top: 50px;
  // bottom: 165px;
  bottom: 50px;
  overflow: scroll;
  position: fixed;
}

.wrapper {
  min-height: 100% !important;
  padding: 0 12px;
  /*height: 500px;*/
}

.left_box > p,
.right_box > p {
  text-align: center;
  color: #768CA8;
  font-size: 12px;
  margin-bottom: 12px;
}

.left_userinfo {
  display: flex;
  justify-content: flex-start;
}

.leftuser {
  position: relative;
  display: inline-block;
  background: #fff;
  padding: 12px;
  margin-bottom: 18px;
  border-radius: 4px;
  color: #464646;
  font-size: 12px;
  box-sizing: border-box;
}

.leftuser::before {
  position: absolute;
  display: inline-block;
  left: 0;
  width: 0;
  height: 0px;
  content: "";
  border-style: solid;
  border-width: 0 12px 12px 0;
  border-color: #fff transparent;
  box-shadow: 0px 0px 0px 0px rgba(0, 0, 0, 0.36);
}

.leftuser > p {
  font-size: 14px;
  word-break: break-word;
}

.left_userinfo > img {
  align-content: center;
  height: 42px;
  width: 42px;
  border-radius: 42px;
}

.left_userinfo > p {
  align-content: center;
  height: 42px;
  line-height: 42px;
  padding-left: 12px;
  color: #fff;
  font-size: 14px;
}

.bigimg {
  background: #222;
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  right: 0;
  z-index: 100000;
}

.bigimg > img {
  position: absolute;
  margin: auto;
  left: 0;
  top: 0;
  bottom: 0;
  right: 0;
  z-index: 99999;
  width: 100%;
}

.small {
  height: 60px;
}

.promt {
  position: fixed;
  top: 44px;
  height: 34px;
  left: 0;
  right: 0;
  line-height: 34px;
  text-align: center;
  color: #666;
  font-size: 12px;
  z-index: 99999;
  background: #ededed;
}

.send_message {
  position: fixed;
  background: #fff;
  box-shadow: 0px 0px 8px rgba(0, 0, 0, 0.16);
  padding: 0 12px;
  height: 60px;
  // bottom: 122px;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 9;
  display: flex;
  align-items: center;
}

.send_message textarea {
  height: 60px;
  padding: 6px;
  width: 100%;
  box-sizing: border-box;
  text-align: left;
  border: 1px solid #999999;
  resize: none;
  box-shadow: -2px 3px 20px 0px rgba(124, 70, 3, 0.18);
  border-radius: 6px;
  border: none;
  width: 64%;
  height: 40px;
  font-size: 14px;
}

.send_message > p {
  color: #666;
  margin-bottom: 6px;
  font-size: 12px;
}

// .send_btn {
//   margin-top: 12px;
// }

.send_btn > img {
  width: 27px;
  float: left;
  margin-left: 15px;
}

.send_btn > span {
  display: inline-block;
  padding: 0 14px;
  height: 26px;
  line-height: 26px;
  color: #fff;
  background: #FF461E;
  border-radius: 15px;
  font-size: 14px;
  margin-left: 15px;
}

.right_box {
  text-align: right;
  margin-bottom: 4px;
}

.rightuser p {
  word-break: break-word;
  text-align: left;
  font-size: 14px;
}

.rightuser {
  background: #FF461E;
  position: relative;
  right: 0;
  display: inline-block;
  padding: 12px;
  margin-bottom: 12px;
  border-radius: 4px;
  color: #fff;
  font-size: 12px;
}

// .rightuser::before {
//   position: absolute;
//   display: inline-block;
//   bottom: -10px;
//   right: 0;
//   width: 0;
//   height: 0px;
//   content: "";
//   border-style: solid;
//   border-width: 0 12px 12px 0;
//   border-color: transparent #22cc9e;
//   box-shadow: 0px 0px 0px 0px rgba(0, 0, 0, 0.36);
// }
</style>