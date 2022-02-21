
<template>
  <div class="advance">
  <top-header :info="info"></top-header>
    <div class="content">
      <div class="scroll">
        <van-tabs v-model="active" @click="tabHandler" swipeable :swipe-threshold="4">
          <van-tab
            v-for="(tabName, idx) in tabLabels"
            :key="idx"
            :title="tabName.label"
          ></van-tab>
          <div class="scroll-list" v-if="active == 0">
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.level }}</p>
                <span>当前身份</span>
              </div>
              <div>
                <p>{{ dataConfig.one_num }}/{{ dataConfig.valid_num }}</p>
                <span>累计辅导/有效辅导</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.total_num - 0 }}</p>
                <span>累计本人总额度 (Y令牌)</span>
              </div>
              <div>
                <p>{{ allNum }}</p>
                <span>累计团队总额度 (Y令牌)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.max_team_total - 0 }}</p>
                <span>大社区累计额度 (Y令牌)</span>
              </div>
              <div>
                <p>{{ dataConfig.team_total - 0 }}</p>
                <span>小社区累计额度 (Y令牌)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.personal_subscribe - 0 }}</p>
                <span>累计本人预充 (Y令牌)</span>
              </div>
              <div>
                <p>{{ dataConfig.team_subscribe - 0 }}</p>
                <span>累计团队预充 (Y令牌)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.max_subscribe - 0 }}</p>
                <span>大社区累计预充 (Y令牌)</span>
              </div>
              <div>
                <p>{{ dataConfig.min_subscribe - 0 }}</p>
                <span>小社区累计预充 (Y令牌)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.is_seniority == 1 ? "是" : "否" }}</p>
                <span>昨日本人是否合格</span>
              </div>
              <div>
                <p>{{ dataConfig.is_valid == 1 ? "是" : "否" }}</p>
                <span>本人是否有效</span>
              </div>
            </div>
            <van-divider>辅导明细</van-divider>
            <van-pull-refresh
              v-model="oneDownLoading"
              @refresh="onRefresh('one')"
              success-text="刷新成功"
            >
              <van-list
                v-model="oneUpLoading"
                :finished="oneFinished"
                :finished-text="finishedText"
                @load="onLoad('one')"
                :offset="offset"
              >
                <ul class="list-wrap">
                  <div v-show="isone" class="no-data">
                    <img :src="noDataImg" />
                  </div>
                  <li class="detail" v-for="(item, index) in one" :key="index">
                    <div class="fisrt-type">
                      <h4>玩家{{ item.ename }}</h4>
                      <div class="types">
                        <div>
                          <span>当前身份:</span>
                          <span>{{ item.level }}</span>
                        </div>
                        <div>
                          <span>辅导/有效辅导:</span>
                          <span>{{ item.one_num }}/{{ item.valid_num }}</span>
                        </div>
                      </div>
                      <div class="types">
                        <div>
                          <span>昨日是否合格:</span>
                          <span>{{ item.is_seniority == 1 ? "是" : "否" }}</span>
                        </div>
                        <div>
                          <span>本人是否有效:</span>
                          <span>{{ item.is_valid == 1 ? "是" : "否" }}</span>
                        </div>
                      </div>
                      <div class="types-line">
                        <span>昨日此账户收益/累计:</span>
                        <span>{{ item.reward }} L令牌</span>
                      </div>
                      <div class="types-line">
                        <span>昨日团队自动清算/累计:</span>
                        <span>{{ item.team_reward }} Y令牌</span>
                      </div>
                      <div class="types-line">
                        <span>昨日此账户自动清算/累计:</span>
                        <span>{{ item.personal_num }} Y令牌</span>
                      </div>
                      <div class="types-line">
                        <span>昨日团队预充/累计:</span>
                        <span>{{ item.team_subscribe }} Y令牌</span>
                      </div>
                      <div class="types-line">
                        <span>昨日此账户预充/累计:</span>
                        <span>{{ item.personal_subscribe }} Y令牌</span>
                      </div>
                      <!-- <div class="types-line">
                        <span>昨日本人收益:</span>
                        <span>{{ item.reward - 0 }} Y令牌</span>
                      </div>
                      <div class="types-line">
                        <span>昨日本人清算流水:</span>
                        <span>{{ item.personal_reward - 0 }} Y令牌</span>
                      </div>
                      <div class="types-line">
                        <span>昨日团队清算流水:</span>
                        <span>{{ item.team_reward - 0 }} Y令牌</span>
                      </div> -->
                      <div class="types-l"></div>
                      <div class="types-line">
                        <span>累计总额度:</span>
                        <span>{{ item.count_num - 0 }} Y令牌</span>
                      </div>
                      <div class="types-line">
                        <span>累计本人额度:</span>
                        <span>{{ item.total_num - 0 }} Y令牌</span>
                      </div>
                      <div class="types-line">
                        <span>累计团队额度:</span>
                        <span>{{ item.team_total - 0 }} Y令牌</span>
                      </div>
                      <!-- <div class="types-line">
                        <span>累计总清算流水:</span>
                        <span>{{ item.total_reward - 0 }} Y令牌</span>
                      </div>
                      <div class="types-line">
                        <span>累计本人清算流水:</span>
                        <span>{{ item.total_personal_reward - 0 }} Y令牌</span>
                      </div>
                      <div class="types-line">
                        <span>累计团队清算流水:</span>
                        <span>{{ item.total_team_reward - 0 }} Y令牌</span>
                      </div> -->
                      <div>
                        <span>加入时间:</span>
                        <span>{{ item.add_time }}</span>
                      </div>
                    </div>
                  </li>
                </ul>
              </van-list>
            </van-pull-refresh>
          </div>
          <!-- <div class="scroll-list" v-if="active == 1">
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.today_share_reward - 0 }}</p>
                <span>昨日分享奖 (Y令牌)</span>
              </div>
              <div>
                <p>{{ dataConfig.share_reward - 0 }}</p>
                <span>累计分享奖 (Y令牌)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.first_reward - 0 }}</p>
                <span>昨日1D清算流水 (Y令牌)</span>
              </div>
              <div>
                <p>{{ dataConfig.second_reward - 0 }}</p>
                <span>昨日2D清算流水 (Y令牌)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.third_reward - 0 }}</p>
                <span>昨日3D清算流水 (Y令牌)</span>
              </div>
              <div style="visibility: hidden">
                <p>{{ dataConfig.total_third_reward - 0 }}</p>
                <span>累计3D清算流水 (Y令牌)</span>
              </div>
            </div>
            <van-divider>记录明细</van-divider>
            <van-pull-refresh
              v-model="twoDownLoading"
              @refresh="onRefresh('two')"
              success-text="刷新成功"
            >
              <van-list
                v-model="twoUpLoading"
                :finished="twoFinished"
                :finished-text="finishedText"
                @load="onLoad('two')"
                :offset="offset"
              >
                <ul class="list-wrap">
                  <div v-show="istwo" class="no-data">
                    <img :src="noDataImg" />
                  </div>
                  <li class="detail" v-for="(item, index) in two" :key="index">
                    <div class="detail-type">
                      <div>
                        <p>{{ item.type_name }}</p>
                        <span>{{ item.add_time }}</span>
                      </div>
                      <div>
                        <span>{{ item.reward - 0 }}</span>
                        <span>{{ item.currency_name }}</span>
                      </div>
                    </div>
                  </li>
                </ul>
              </van-list>
            </van-pull-refresh>
          </div> -->
          <!-- <div class="scroll-list" v-if="active == 1">
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.today_team_reward - 0 }}</p>
                <span>昨日管理奖 (Y令牌)</span>
              </div>
              <div>
                <p>{{ dataConfig.team_reward - 0 }}</p>
                <span>累计管理奖 (Y令牌)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfig.today_reward - 0 }}</p>
                <span>昨日团队清算流水 (Y令牌)</span>
              </div>
              <div>
                <p>{{ dataConfig.total_today_reward - 0 }}</p>
                <span>累计团队清算流水 (Y令牌)</span>
              </div>
            </div>
            <van-divider>记录明细</van-divider>
            <van-pull-refresh
              v-model="twoDownLoading"
              @refresh="onRefresh('two')"
              success-text="刷新成功"
            >
              <van-list
                v-model="twoUpLoading"
                :finished="twoFinished"
                :finished-text="finishedText"
                @load="onLoad('two')"
                :offset="offset"
              >
                <ul class="list-wrap">
                  <div v-show="istwo" class="no-data">
                    <img :src="noDataImg" />
                  </div>
                  <li
                    class="detail"
                    v-for="(item, index) in two"
                    :key="index"
                  >
                    <div class="detail-type">
                      <div>
                        <p>{{ item.type_name }}</p>
                        <span>{{ item.add_time }}</span>
                      </div>
                      <div>
                        <span>{{ item.reward - 0 }}</span>
                        <span>{{ item.currency_name }}</span>
                      </div>
                    </div>
                  </li>
                </ul>
              </van-list>
            </van-pull-refresh>
          </div> -->

          <div class="scroll-list" v-if="active == 1">
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfigTwo.yesterday_share_reward - 0 }}</p>
                <span>昨日分享奖 (L令牌)</span>
              </div>
              <div>
                <p>{{ dataConfigTwo.s_share_reward - 0 }}</p>
                <span>累计分享奖 (L令牌)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfigTwo.total_first_reward - 0 }}</p>
                <span>昨日1D预充 (Y令牌)</span>
              </div>
              <div>
                <p>{{ dataConfigTwo.total_second_reward - 0 }}</p>
                <span>昨日2D预充 (Y令牌)</span>
              </div>
            </div>
            <van-divider>记录明细</van-divider>
            <van-pull-refresh
              v-model="twoDownLoading"
              @refresh="onRefresh('two')"
              success-text="刷新成功"
            >
              <van-list
                v-model="twoUpLoading"
                :finished="twoFinished"
                :finished-text="finishedText"
                @load="onLoad('two')"
                :offset="offset"
              >
                <ul class="list-wrap">
                  <div v-show="istwo" class="no-data">
                    <img :src="noDataImg" />
                  </div>
                  <li class="detail" v-for="(item, index) in two" :key="index">
                    <div class="detail-type">
                      <div>
                        <p>{{ item.type_name }}</p>
                        <span>{{ item.add_time }}</span>
                      </div>
                      <div>
                        <span>{{ item.reward }}</span>
                        <span>{{ item.currency_name }}</span>
                      </div>
                    </div>
                  </li>
                </ul>
              </van-list>
            </van-pull-refresh>
          </div>
          <div class="scroll-list" v-if="active == 2">
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfigTwo.yesterday_team_reward - 0}}</p>
                <span>昨日管理奖 (L令牌)</span>
              </div>
              <div>
                <p>{{ dataConfigTwo.s_team_reward - 0}}</p>
                <span>累计管理奖+辅导奖 (L令牌)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfigTwo.yesterday_subscribe - 0 }}</p>
                <span>昨日团队预充 (Y令牌)</span>
              </div>
 
              <div>
                <p>{{ dataConfigTwo.total_subscribe - 0 }}</p>
                <span>累计团队预充 (Y令牌)</span>
              </div>
            </div>
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfigTwo.yesterday_flat_reward - 0 }}</p>
                <span>昨日辅导奖（L令牌）</span>
              </div>
              <div>
                <p>{{ dataConfigTwo.yesterday_flat_subscribe - 0 }}</p>
                <span>昨日辅导预充 (Y令牌)</span>
              </div>
            </div>
            <van-divider>记录明细</van-divider>
            <van-pull-refresh
              v-model="threeDownLoading"
              @refresh="onRefresh('three')"
              success-text="刷新成功"
            >
              <van-list
                v-model="threeUpLoading"
                :finished="threeFinished"
                :finished-text="finishedText"
                @load="onLoad('three')"
                :offset="offset"
              >
                <ul class="list-wrap">
                  <div v-show="isthree" class="no-data">
                    <img :src="noDataImg" />
                  </div>
                  <li class="detail" v-for="(item, index) in three" :key="index">
                    <div class="detail-type">
                      <div>
                        <p>{{ item.type_name }}</p>
                        <span>{{ item.add_time }}</span>
                      </div>
                      <div>
                        <span>{{ item.reward }}</span>
                        <span>{{ item.currency_name }}</span>
                      </div>
                    </div>
                  </li>
                </ul>
              </van-list>
            </van-pull-refresh>
          </div>
          <div class="scroll-list" v-if="active == 3">
            <div class="scroll-list-type">
              <div>
                <p>{{ dataConfigTwo.yesterday_centre_reward - 0 }}</p>
                <span>昨日服务津贴 (L令牌)</span>
              </div>
              <div>
                <p>{{ dataConfigTwo.total_centre_reward - 0 }}</p>
                <span>累计服务津贴 (L令牌)</span>
              </div>
            </div>
            <van-divider>记录明细</van-divider>
            <van-pull-refresh
              v-model="fourDownLoading"
              @refresh="onRefresh('four')"
              success-text="刷新成功"
            >
              <van-list
                v-model="fourUpLoading"
                :finished="fourFinished"
                :finished-text="finishedText"
                @load="onLoad('four')"
                :offset="offset"
              >
                <ul class="list-wrap">
                  <div v-show="isfour" class="no-data">
                    <img :src="noDataImg" />
                  </div>
                  <li
                    class="detail"
                    v-for="(item, index) in four"
                    :key="index"
                  >
                    <div class="detail-type">
                      <div>
                        <p>{{ item.type_name }}</p>
                        <span>{{ item.add_time }}</span>
                      </div>
                      <div>
                        <span>{{ item.reward }}</span>
                        <span>{{ item.currency_name }}</span>
                      </div>
                    </div>
                  </li>
                </ul>
              </van-list>
            </van-pull-refresh>
          </div>
        </van-tabs>
      </div>
    </div>
  </div>
</template>

<script>
import { ark_my_info, ark_help_log, ark_power_log, ark_buy_info, ark_subscribe_info, ark_subscribe_help_log, ark_subscribe_power_log, ark_centre_log } from "@/http/api.js";
export default {
  data () {
    return {
      info: {
        title: "推进贡献",
        isBack: true,
        exit: true,
      },
      oneUpLoading: false, //上拉加载
      oneFinished: false, //上拉加载完毕
      oneDownLoading: false, //下拉刷新
      twoUpLoading: false, //上拉加载
      twoFinished: false, //上拉加载完毕
      twoDownLoading: false, //下拉刷新
      threeUpLoading: false, //上拉加载
      threeFinished: false, //上拉加载完毕
      threeDownLoading: false, //下拉刷新
      fourUpLoading: false, //上拉加载
      fourFinished: false, //上拉加载完毕
      fourDownLoading: false, //下拉刷新
      fiveUpLoading: false, //上拉加载
      fiveFinished: false, //上拉加载完毕
      fiveDownLoading: false, //下拉刷新
      finishedText: "沒有更多了",
      noDataImg: require("../../assets/rocket/fg-img.png"),
      offset: 100,
      isone: false,
      istwo: false,
      isthree: false,
      isfour: false,
      isfive: false,
      one: [],
      two: [],
      three: [],
      four: [],
      five: [],
      // oneOption: {
      //   type: "0",
      //   page: 1,
      //   rows: 10,
      //   typeId: 1,
      // },
      // twoOption: {
      //   type: "3",
      //   page: 1,
      //   rows: 10,
      //   typeId: 2,
      // },
      // threeOption: {
      //   type: "0",
      //   page: 1,
      //   rows: 10,
      //   typeId: 3,
      // },
      // fourOption: {
      //   type: "1",
      //   page: 1,
      //   rows: 10,
      //   typeId: 4,
      // },
      // fiveOption: {
      //   type: "3",
      //   page: 1,
      //   rows: 10,
      //   typeId: 5,
      // },
      oneOption: {
        type: "0",
        page: 1,
        rows: 10,
        typeId: 1,
      },
      twoOption: {
        type: "0",
        page: 1,
        rows: 10,
        typeId: 2,
      },
      threeOption: {
        type: "1",
        page: 1,
        rows: 10,
        typeId: 3,
      },
      fourOption: {
        type: "3",
        page: 1,
        rows: 10,
        typeId: 4,
      },
      tabLabels: [
        {
          label: "我的辅导",
          isFirst: true,
          type: "one",
        },
        {
          label: "分享奖",
          isFirst: true,
          type: "two",
        },
        {
          label: "管理奖",
          isFirst: true,
          type: "three",
        },
        {
          label: "服务津贴",
          isFirst: true,
          type: "four",
        },
        // {
        //   label: "我的辅导",
        //   isFirst: true,
        //   type: "one",
        // },
        // {
        //   label: "管理奖(自动)",
        //   isFirst: true,
        //   type: "two",
        // },
        // {
        //   label: "分享奖",
        //   isFirst: true,
        //   type: "three",
        // },
        // {
        //   label: "管理奖",
        //   isFirst: true,
        //   type: "four",
        // },
        // {
        //   label: "服务津贴",
        //   isFirst: true,
        //   type: "five",
        // },
      ],
      active: Number(localStorage.getItem("incomeReId"))
        ? Number(localStorage.getItem("incomeReId"))
        : 0,
      dataConfig: {},
      dataConfigTwo: {},
      allNum: "",
    }
  },
  methods: {
    async _buy_info () {
      await this.$http.post(ark_buy_info).then(({ data }) => {
        if (data.code == 10000) {
          this.dataConfig = {
            ...data.result,
          };
          this.allNum = this.cal.accAdd(this.dataConfig.max_team_total, this.dataConfig.team_total);
        }
      });
    },
    _buy_info_two () {
      this.$http.post(ark_subscribe_info).then(({ data }) => {
        if (data.code == 10000) {
          this.dataConfigTwo = {
            ...data.result,
          };
        }
      });
    },
    onLoad (type) {
      let key = type + "Option",
        tempUp = type + "UpLoading",
        tempDown = type + "DownLoading",
        tempFin = type + "Finished",
        isShow = "is" + type;
      this[tempUp] = true;
      this.fetchList(this[key]).then((data) => {

        this[tempUp] = false;
        this[tempDown] = false;
        this[type] = this[type].concat(data);
        if (this[type].length == 0) {
          this[isShow] = true;
        }
        this[key].page++;
        this[tempFin] = false;
        if (data.length < this[key].rows) {
          // 數據小于10條，已全部加載完畢finished設置爲true
          this[tempFin] = true;
          return;
        }
      });
    },
    onRefresh (type) {
      let key = type + "Option";
      this[key].page = 1;
      this[type] = [];
      this.onLoad(type);
    },
    tabHandler (idx) {
      this.tabLabels.forEach((item, index) => {
        let key = item.type + "Option";
        this[key].page = 1;

        // this.tabLabels[this.active].isFirst = false;
        if (index == idx) {
          this.onRefresh(item.type);
        }
      });
      this.active = idx;
      // 存儲ID
      this.$nextTick(() => {
        localStorage.setItem("incomeReId", idx);
      });
    },
    fetchList (obj) {
      let url = "";
      // if (obj.type == "0") {
      //   url = ark_my_info;
      // } else if (obj.type == "1") {
      //   url = ark_help_log;
      // } else {
      //   url = ark_power_log;
      // }
      // if (obj.typeId == 1) {
      //   url = ark_my_info;
      // } else if(obj.typeId == 2) {
      //   url = ark_power_log;
      // } else if(obj.typeId == 3) {
      //   url = ark_subscribe_help_log;
      // } else if(obj.typeId == 4) {
      //   url = ark_subscribe_power_log;
      // } else if(obj.typeId == 5) {
      //   url = ark_centre_log;
      // }
      if (obj.typeId == 1) {
        url = ark_my_info;
      } else if(obj.typeId == 2) {
        url = ark_subscribe_help_log;
      } else if(obj.typeId == 3) {
        url = ark_subscribe_power_log;
      } else if(obj.typeId == 4) {
        url = ark_centre_log;
      } 
      return new Promise((resolve, reject) => {
        let option = {
          ...obj,
        };
        this.$http.post(url, option).then(({ data }) => {
          if (data.code == 10000) {
            if (data.result.length >= 0) {
              resolve(data.result);
            }
          } else {
            resolve([]);
          }
        });
      });
    },
  },
  created () {
    this._buy_info();
    this._buy_info_two();
  }
};
</script>

<style lang="scss" scoped>
/deep/ .van-tabs__nav {
  background: transparent;
}
/deep/ .van-tab {
  font-size: 16px;
  color: #6f6f6f;
}
/deep/ .van-tab--active {
  color: #ff461e;
}
/deep/ .van-tabs__line {
  background: #ff461e;
}
// /deep/ .van-tabs__wrap {
//   padding-top: 10px;
// }
/deep/ .van-divider {
  color: #000000;
  border-color: #000000;
  padding: 0 50px;
  margin-bottom: 0;
  font-size: 17px;
}
/deep/ header {
  background: #f5f5f5;
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
  // margin-top: 44px;
  background: #f5f5f5;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang TC";
  font-weight: 500;

  .detail {
    padding: 20px 15px;
    box-sizing: border-box;
    background: #ededed;
    box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.16);
    border-radius: 8px;
    margin-bottom: 10px;
    > .fisrt-type {
      color: #2c2c2c;
      font-weight: 500;
      > h4 {
        font-size: 18px;
        font-weight: 500;
      }
      > div:nth-child(2) {
        font-size: 16px;
        padding-top: 13px;
        > span:nth-child(1) {
          color: #7e7e7e;
        }
      }
      > .types {
        padding-top: 10px;
        font-size: 14px;
        display: flex;
        justify-content: space-between;
        > div {
          > span:nth-child(1) {
            color: #6f6f6f;
          }
        }
      }
      > .types-line {
        padding-top: 10px;
        font-size: 14px;
        > span:nth-child(1) {
          color: #6f6f6f;
        }
      }
      > .types-l {
        padding-top: 10px;
        border-bottom: 1px solid #6e6e6e;
      }
      > div:last-child {
        font-size: 14px;
        padding-top: 10px;
        > span:nth-child(1) {
          color: #adadad;
        }
      }
    }
    .detail-type {
      display: flex;
      justify-content: space-between;
      align-items: center;
      > div:nth-child(1) {
        font-size: 16px;
        color: #2c2c2c;
        > span {
          font-size: 12px;
          color: #6f6f6f;
        }
      }
      > div:nth-child(2) {
        font-size: 18px;
        color: #2c2c2c;
        > span:last-child {
          margin-left: 5px;
          font-size: 14px;
          color: #6f6f6f;
        }
      }
    }
  }
  .list-wrap {
    margin-top: 15px;
    .no-data {
      margin-top: 60px;
      text-align: center;
    }
    > li:last-child {
      margin-bottom: 0;
    }
  }
}
.scroll-list-type {
  width: 100%;
  height: 73px;
  background: #ededed;
  border-radius: 12px;
  margin-bottom: 10px;
  display: flex;
  justify-content: space-around;
  align-items: center;
  > div {
    font-size: 16px;
    font-weight: bold;
    color: #2c2c2c;
    text-align: center;
    > p {
      margin-bottom: 6px;
    }
    > span {
      font-size: 12px;
      font-weight: 400;
      color: #6f6f6f;
    }
  }
}
.scroll-list-type-fire {
  justify-content: left;
  padding: 0px 30px;
  box-sizing: border-box;
}
.scroll-list {
  margin-top: 15px;
  padding: 0 15px;
  box-sizing: border-box;
}
</style>  