<template>
  <header :class="info.background">
    <button class="iconfont iconfanhui back" v-if="info.isBack" @click="goBack">
      <i class="iconfont icon-return"></i>
    </button>
    <h3>{{ info.title }}</h3>
    <div v-if="info.isRight" class="right_btn">
      <button @click="jump(info.url)">
        <img v-if="info.img" :src="info.img" />
        <span v-if="info.content">{{ info.content }}</span>
      </button>
    </div>
  </header>
</template>
<script>
export default {
  name: "topheader",
  props: {
    info: Object
  },
  data () {
    return {
      setting: {},
      platform: this.$cookie.get('platform'),
    };
  },
  created () {
    this.setting = {
      ...this.info
    };
  },
  methods: {
    jump(url) {
      this.$router.push({ path: url });
    },
    goBack () {
      if(this.info.rmList == 1) {
        localStorage.removeItem("shopActiveId");
      }
      if (this.platform == "ios") {
        window.webkit.messageHandlers.iosAction.postMessage("exit");
      } else if (this.platform == "android") {
        if(this.info.exit) {
          apps.exit();
          localStorage.removeItem("highIdx");
          localStorage.removeItem("zeroIdx");
        }else {
          this.$router.back();
        }
        if(this.info.shopDet) {
          localStorage.removeItem("highIdx");
          localStorage.removeItem("zeroIdx");
        }
      } else {
        if(this.info.shopDet) {
          localStorage.removeItem("highIdx");
          localStorage.removeItem("zeroIdx");
        }
        this.$router.back()
      }

    }
  }
};
</script>

<style lang="scss" scoped>
header {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 44px;
  background: #fff;
  text-align: center;
  line-height: 44px;
  display: flex;
  align-items: center;
  color: #000;
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
header div.right_btn {
  font-size: 14px;
  right: 0;
  box-sizing: border-box;
}
header div.right_btn button > img {
  width: 17px;
  display: block;
}
header div.right_btn button {
  background: transparent;
  border: none;
  margin-right: 5px;
  padding-right: 12px;
}
header div.right_btn button:last-of-type {
  margin-right: 0;
}
header h3 {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: bold;
}

</style>