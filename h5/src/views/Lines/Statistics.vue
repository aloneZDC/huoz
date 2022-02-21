<template>
  <div class="statis">
    <top-header :info="info"></top-header>
    <div class="content">
      <p class="title-time">{{ dataConfig.today }}</p>
      <div class="cont">
        <div class="cont-list">
          <div>
            <p>{{ dataConfig.max_info.today_num }}</p>
            <p>当日L社区预约</p>
          </div>
          <div>
            <p>{{ dataConfig.max_info.total_num }}</p>
            <p>累计L社区预约</p>
          </div>
        </div>
        <div class="cont-list">
          <div>
            <p>{{ dataConfig.max_info.month_num }}</p>
            <p>当月L社区预约</p>
          </div>
          <div>
            <p>{{ dataConfig.max_info.last_month_num }}</p>
            <p>上个月L社区预约</p>
          </div>
        </div>
        <div class="cont-list">
          <div>
            <p>{{ dataConfig.max_info.total_lately_num }}</p>
            <p>累计L社区抱彩</p>
          </div>
          <!-- 不需要的数据 -->
          <div class="cont-list-style">
            <p>0.000</p>
            <p>最近一次L社区抱彩</p>
          </div>
        </div>
      </div>
      <div class="cont">
        <div class="cont-list">
          <div>
            <p>{{ dataConfig.min_info.today_num }}</p>
            <p>当日M社区预约</p>
          </div>
          <div>
            <p>{{ dataConfig.min_info.total_num }}</p>
            <p>累计M社区预约</p>
          </div>
        </div>
        <div class="cont-list">
          <div>
            <p>{{ dataConfig.min_info.month_num }}</p>
            <p>当月M社区预约</p>
          </div>
          <div>
            <p>{{ dataConfig.min_info.last_month_num }}</p>
            <p>上个月M社区预约</p>
          </div>
        </div>
        <div class="cont-list">
          <div>
            <p>{{ dataConfig.min_info.total_lately_num }}</p>
            <p>累计M社区抱彩</p>
          </div>
          <!-- 不需要的数据 -->
          <div class="cont-list-style">
            <p>0.000</p>
            <p>最近一次M社区抱彩</p>
          </div>
        </div>
      </div>

      <div class="cont">
        <div class="cont-list">
          <div>
            <p>{{ dataConfig.oneself_info.today_num }}</p>
            <p>当日本人预约</p>
          </div>
          <div>
            <p>{{ dataConfig.oneself_info.total_num }}</p>
            <p>累计本人预约</p>
          </div>
        </div>
        <div class="cont-list">
          <div>
            <p>{{ dataConfig.oneself_info.month_num }}</p>
            <p>当月本人预约</p>
          </div>
          <div>
            <p>{{ dataConfig.oneself_info.last_month_num }}</p>
            <p>上个月本人预约</p>
          </div>
        </div>
        <div class="cont-list">
          <div>
            <p>{{ dataConfig.oneself_info.lately_num }}</p>
            <p>最近一次本人抱彩</p>
          </div>
          <div>
            <p>{{ dataConfig.oneself_info.total_lately_num }}</p>
            <p>累计本人抱彩</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { statistics_info } from "@/http/api.js";
export default {
  name: "",
  components: {},
  data() {
    return {
      info: {
        title: "数据统计明细",
        isBack: true,
        exit: false,
      },
      dataConfig: {
        oneself_info: {},
        max_info: {},
        min_info: {},
      }
    };
  },
  methods: {
    _index() {
      this.$http.post(statistics_info).then(({ data }) => {
        if (data.code == 10000) {
          this.dataConfig = {
            ...data.result,
          };
        }
      });
    },
  },
  created() {
    this._index();
  }
};
</script>

<style lang="scss" scoped>
.content {
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  width: auto;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang SC";
  padding: 0 15px;
  box-sizing: border-box;
  .title-time {
    font-size: 14px;
    font-weight: 500;
    color: #5C5C5C;
    margin: 15px 0;
    text-align: center;
  }
  .cont {
    padding: 15px;
    box-sizing: border-box;
    background: #ededed;
    border-radius: 12px;
    margin-bottom: 15px;
    .cont-list {
      display: flex;
      justify-content: space-around;
      text-align: center;
      font-size: 14px;
      color: #717171;
      padding-bottom: 20px;
      > div {
        > p:nth-child(1) {
          font-size: 16px;
          font-weight: bold;
          line-height: 22px;
          color: #0f0f0f;
          padding-bottom: 4px;
        }
      }
      .cont-list-style {
        visibility: hidden;
      }
    }
    
    > div:last-child {
      padding-bottom: 0;
    }
  }
}
</style>