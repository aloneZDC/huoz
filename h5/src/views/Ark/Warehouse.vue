<template>
  <div class="inDetails">
    <top-header :info="info"></top-header>
    <div class="content">
      <div class="title" v-show="dataOption.type == 1">
        说明:每个方舟自“第四个推进”起,若推进失败,则100%退
        回失败关所有玩家的已投燃料,并再享受已结算关累计的幸运舱加权分红
        (根据每人已投燃料比例加权分)。
      </div>
      <div class="scroll">
        <van-pull-refresh
          v-model="isDownLoading"
          @refresh="onRefresh"
          success-text="刷新成功"
        >
          <van-list
            v-model="isUpLoading"
            :finished="finished"
            :finished-text="finishedText"
            @load="onLoad"
            :offset="offset"
            :immediate-check="false"
          >
            <div v-show="isNo" class="no-data">
              <img :src="noDataImg" />
            </div>
            <ul class="list-warp">
              <li class="detail" v-for="(item, index) in items" :key="index" @click="goNext(item.id)">
                <div>
                  <span>{{ item.name }}</span>
                  <div>
                    <i class="iconfont icon-return"></i>
                  </div>
                </div>
                <p>
                  <span v-if="dataOption.type == 1">幸运舱: </span>
                  <span v-else-if="dataOption.type == 2">市值舱: </span>
                  <span v-else>工具舱: </span>
                  <!-- <span>{{ dataOption.type == 1 ? '幸运舱: ' : dataOption.type == 2 ? '市值舱: ' : '工具舱: ' }}</span> -->
                  <span>
                  {{ item.warehouse - 0 }} L令牌</span>
                </p>
              </li>
            </ul>
          </van-list>
        </van-pull-refresh>
      </div>
    </div>
  </div>
</template>

<script>
import { ark_rocket_index } from "@/http/api.js";
export default {
  name: "inDetails",
  components: {},
  data() {
    return {
      info: {
        title: "",
        isBack: true,
        exit: true,
      },
      textStr: "",
      items: [],
      isUpLoading: false, //上拉加载
      finished: false, //上拉加载完毕
      isDownLoading: false, //下拉刷新
      isNo: false,
      offset: 100,
      finishedText: "没有更多了",
      noDataImg: require("../../assets/rocket/fg-img.png"),
      dataOption: {
        type: "",
        page: 1,
        rows: 10,
      },
      imgList: require("../../assets/rocket/fv-img.png"),
    };
  },
  methods: {
    goNext(_id) {
      let url = "/wareDetsArk" + '?id=' + _id + "&type=" + this.dataOption.type;
      this.$router.push({ path: url });
    },
    onLoad() {
      this.isUpLoading = true;
      this.$http
        .post(ark_rocket_index, this.dataOption)
        .then(({ data }) => {
          this.isDownLoading = false;
          this.isUpLoading = false;
          if (data.code == 10001) {
            this.finished = true;
            if (this.items.length == 0) {
              this.isNo = true;
            } else {
              this.isNo = false;
            }
            return;
          }
          this.items = this.items.concat(data.result);
          if (data.result.length < this.dataOption.rows) {
            this.finished = true;
            return;
          } else {
            this.finished = false;
          }
          this.dataOption.page += 1;
        })
        .catch((err) => {
          err;
          this.items = [];
        });
    },
    onRefresh() {
      this.dataOption.page = 1;
      // 清空列表数据
      this.items = [];
      this.finished = false;
      // 重新加载数据
      // 将 loading 设置为 true，表示处于加载状态
      // this.loading = false;
      this.onLoad();
    },
  },
  created() {
    let urlId = window.location.hash;
    urlId = urlId.split("?id=");
    this.dataOption.type = urlId[1];
    if(this.dataOption.type == 1) {
      this.info.title = "幸运舱";
    }else if(this.dataOption.type == 2) {
      this.info.title = "市值舱";
    }else {
      this.info.title = "工具舱";
    }
    this.onRefresh();
  },
};
</script>

<style lang="scss" scoped>
/deep/ header {
  background: #fff;
  color: #0f0f0f;
  i {
    color: #0f0f0f;
  }
}
.content {
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  overflow: auto;
  background: #fff;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang TC";
  padding: 0 15px;
  box-sizing: border-box;
  font-weight: 500;
  .title {
    margin-top: 20px;
    font-size: 13px;
    color: #848484;
    padding: 0 8px;
    box-sizing: border-box;
    margin-bottom: 20px;
    line-height: 20px;
  }
  .detail {
    background: url("../../assets/rocket/gdh-img.png") no-repeat center;
    width: 167px;
    height: 95px;
    color: #4A4A4A;
    padding: 20px 0;
    box-sizing: border-box;
    margin-bottom: 15px;
    > div {
      display: flex;
      font-size: 16px;
      align-items: center;
      padding-left: 15px;
      font-weight: bolder;
      > div {
        transform: rotate(180deg);
        margin-left: 56px;
        > i {
          font-size: 18px;
        }
      }
    }
    > p {
      color: #999998;
      font-size: 12px;
      font-weight: bold;
      margin-top: 16px;
      display: flex;
      margin-left: 15px;
      align-items: center;
    }
  }
  .list-warp {
    display: flex;
    flex-wrap: wrap;
    > li:nth-child(2n) {
      margin-left: 10px;
    }
  }
  .no-data {
    margin-top: 60px;
    text-align: center;
  }
}
</style>