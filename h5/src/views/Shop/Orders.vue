<template>
  <div class="orders">
    <header>
      <button class="iconfont iconfanhui back" @click="goBack">
        <i class="iconfont icon-return"></i>
      </button>
      <h3>{{ info.title }}</h3>
    </header>
    <div class="content">
      <van-tabs v-model="active" @click="tabHandler" swipeable>
        <van-tab
          v-for="(tabName, idx) in tabLabels"
          :key="idx"
          :title="tabName.label"
        ></van-tab>
        <div class="scroll-list" v-if="active == 0">
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
                  <!-- <p>No Data</p> -->
                </div>
                <li class="detail" v-for="(item, index) in one" :key="index">
                  <div
                    v-if="item.gmo_status == 1"
                    class="detail-top"
                    @click="jumpDet(item.gmo_id, item.category_pid)"
                  >
                    <!-- <img :src="firstiImg" alt="" /> -->
                    <span><span></span>{{ item.category_type }}</span>
                    <span>待发货</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div
                    v-else-if="item.gmo_status == 2"
                    class="detail-top"
                    @click="jumpDet(item.gmo_id, item.category_pid)"
                  >
                    <span><span></span>{{ item.category_type }}</span>
                    <span>等待付款</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div
                    v-else-if="item.gmo_status == 3"
                    class="detail-top"
                    @click="jumpDet(item.gmo_id, item.category_pid)"
                  >
                    <!-- <img :src="firstiImg" alt="" /> -->
                    <span><span></span>{{ item.category_type }}</span>
                    <span>已发货</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div
                    v-else-if="item.gmo_status == 4"
                    class="detail-top"
                    @click="jumpDet(item.gmo_id, item.category_pid)"
                  >
                    <!-- <img :src="firstiImg" alt="" /> -->
                    <span><span></span>{{ item.category_type }}</span>
                    <span>已完成</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div
                    v-else-if="item.gmo_status == 5"
                    class="detail-top"
                    @click="jumpDet(item.gmo_id, item.category_pid)"
                  >
                    <!-- <img :src="firstiImg" alt="" /> -->
                    <span><span></span>{{ item.category_type }}</span>
                    <span>已取消</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div
                    v-else-if="item.gmo_status == 6"
                    class="detail-top"
                    @click="jumpDet(item.gmo_id, item.category_pid)"
                  >
                    <!-- <img :src="firstiImg" alt="" /> -->
                    <span><span></span>{{ item.category_type }}</span>
                    <span>待提货</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div class="detail-title">
                    <div v-for="(itemes, ind) in item.goods" :key="ind">
                      <div>
                        <img :src="itemes.go_img" alt="" />
                      </div>
                      <div>
                        <p>{{ itemes.go_title }}</p>
                        <p v-if="itemes.format">{{ itemes.format.name }}</p>
                        <div class="detail-title-li">
                          <span></span>
                          <span
                            ><span class="size">X</span
                            >{{ itemes.go_num }}</span
                          >
                        </div>
                        <div v-if="item.category_pid != 3 && item.category_pid != 17" class="detail-title-tw">赠与≈{{ Math.round(item.gmo_give_num * item.hm_price) }} CNY</div>
                      </div>
                    </div>
                  </div>
                  <!-- <div class="detail-list-one">
                    <span>商品总额</span>
                    <span>￥{{ item.gmo_total_price }}</span>
                  </div>
                  <div class="detail-list-two">
                    <span>会员等级优惠</span>
                    <span>-￥{{ item.gmo_discount_num }}</span>
                  </div> -->
                  <div class="detail-list-three">
                    <span>实付款: </span>
                    <span v-if="item.category_type == 4"
                      >{{ item.gmo_pay_num }} 积分</span
                    >
                    <span v-else>
                      <span v-if="item.category_pid == 3">
                        {{ item.total_pay_huo_price }}{{ item.category_pid == 3 ? '金米' : item.give_currency.currency_name }}
                      </span>
                      <span v-else-if="item.category_pid == 17">
                        ￥{{ item.total_pay_cny_price }}
                      </span>
                      <span v-else>
                        ￥{{ item.total_pay_cny_price }}≈{{
                        item.total_pay_huo_price
                      }}{{ item.category_pid == 3 ? "金米" : item.give_currency.currency_name }}
                      </span>
                    </span>
                  </div>
                  <div v-if="item.gmo_status == 1">
                    <!-- <button @click="cancelMoney(item.gmo_id)">一键退款</button> -->
                    <button @click="tipSend">提醒发货</button>
                  </div>
                  <div v-if="item.gmo_status == 2">
                    <button @click="cancelOrder(item.gmo_id)">取消订单</button>
                    <button
                      @click="
                        go_pay(
                          item.total_pay_huo_price,
                          item.gmo_id,
                          item.category_type,
                          item.category_pid,
                          item.total_pay_cny_price
                        )
                      "
                    >
                      去支付
                    </button>
                  </div>
                  <div v-if="item.gmo_status == 3">
                    <!-- <button @click="jumpDetList(item.gmo_id)">查看物流</button> -->
                    <button @click="confirm(item.gmo_id)">确认收货</button>
                  </div>
                  <div v-if="item.gmo_status == 4">
                    <!-- <button v-if="item.goods[0].go_goods_id != 1" @click="jumpDetList(item.gmo_id)">
                      查看物流
                    </button> -->
                    <!-- <button>评价</button> -->
                    <!-- <button v-if="item.category_pid == 3 && item.is_subscribe == 1" @click="goHp('/prerecord', item.gmo_id)" class="y-list-w">预约记录</button> -->
                  </div>
                  <div v-if="item.gmo_status == 6">
                    <!-- <button v-if="item.goods[0].go_goods_id != 1" @click="jumpDetList(item.gmo_id)">
                      查看物流
                    </button> -->
                    <!-- <button>评价</button> -->
                    <!-- <button @click="cancelMoney(item.gmo_id)" v-if="item.store_use == 0">一键退款</button> -->
                    <button @click="confirm(item.gmo_id)">确认收货</button>
                  </div>
                  <div class="details-list-five" v-if="item.category_type == 5">
                    <div class="list-five-title">
                      <!-- 未拼团 -->
                      <div v-if="item.group_data.status == 0">
                        <div>
                          <div>
                            <img :src="ptImg" alt="" />
                            <img :src="ptImg" alt="" />
                            <p>未拼团</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团中 -->
                      <div v-else-if="item.group_data.status == 1">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团中({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团成功 -->
                      <div v-else-if="item.group_data.status == 2">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                            />
                            <p>拼团成功({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团关闭 -->
                      <div v-else-if="item.group_data.status == 3">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团关闭({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
            </van-list>
          </van-pull-refresh>
        </div>
        <!-- 待付款 -->
        <div class="scroll-list" v-else-if="active == 1">
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
                  <!-- <p>No Data</p> -->
                </div>
                <li class="detail" v-for="(item, index) in two" :key="index">
                  <div
                    class="detail-top"
                    @click="jumpDet(item.gmo_id, item.category_pid)"
                  >
                    <!-- <img :src="firstiImg" alt="" /> -->
                    <span><span></span>{{ item.category_type }}</span>
                    <span>等待付款</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div class="detail-title">
                    <div v-for="(itemes, ind) in item.goods" :key="ind">
                      <div>
                        <img :src="itemes.go_img" alt="" />
                      </div>
                      <div>
                        <p>{{ itemes.go_title }}</p>
                        <p v-if="itemes.format">{{ itemes.format.name }}</p>
                        <div class="detail-title-li">
                          <span></span>
                          <span
                            ><span class="size">X</span
                            >{{ itemes.go_num }}</span
                          >
                        </div>
                        <div v-if="item.category_pid != 3 && item.category_pid != 17" class="detail-title-tw">赠与≈{{ Math.round(item.gmo_give_num * item.hm_price) }} CNY</div>
                      </div>
                    </div>
                  </div>
                  <!-- <div class="detail-list-one">
                    <span>商品总额</span>
                    <span>￥{{ item.gmo_total_price }}</span>
                  </div>
                  <div class="detail-list-two">
                    <span>会员等级优惠</span>
                    <span>-￥{{ item.gmo_discount_num }}</span>
                  </div> -->
                  <div class="detail-list-three">
                    <span>实付款: </span>
                    <span v-if="item.category_type == 4"
                      >{{ item.gmo_pay_num }} 积分</span
                    >
                    <span v-else>
                      <span v-if="item.category_pid == 3">
                        {{ item.total_pay_huo_price }}{{ item.category_pid == 3 ? '金米' : item.give_currency.currency_name }}
                      </span>
                      <span v-else-if="item.category_pid == 17">
                        ￥{{ item.total_pay_cny_price }}
                      </span>
                      <span v-else>
                        ￥{{ item.total_pay_cny_price }}≈{{
                        item.total_pay_huo_price
                      }}{{ item.category_pid == 3 ? "金米" : item.give_currency.currency_name }}
                      </span>
                    </span>
                  </div>
                  <div>
                    <button @click="cancelOrder(item.gmo_id)">取消订单</button>
                    <button
                      @click="
                        go_pay(
                          item.total_pay_huo_price,
                          item.gmo_id,
                          item.category_type,
                          item.category_pid,
                          item.total_pay_cny_price
                        )
                      "
                    >
                      去付款
                    </button>
                  </div>
                  <div class="details-list-five" v-if="item.category_type == 5">
                    <div class="list-five-title">
                      <!-- 未拼团 -->
                      <div v-if="item.group_data.status == 0">
                        <div>
                          <div>
                            <img :src="ptImg" alt="" />
                            <img :src="ptImg" alt="" />
                            <p>未拼团</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团中 -->
                      <div v-else-if="item.group_data.status == 1">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团中({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团成功 -->
                      <div v-else-if="item.group_data.status == 2">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                            />
                            <p>拼团成功({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团关闭 -->
                      <div v-else-if="item.group_data.status == 3">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团关闭({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
            </van-list>
          </van-pull-refresh>
        </div>
        <!-- 待发货 -->
        <div class="scroll-list" v-else-if="active == 2">
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
                  <!-- <p>No Data</p> -->
                </div>
                <li class="detail" v-for="(item, index) in three" :key="index">
                  <div class="detail-top" @click="jumpDet(item.gmo_id)">
                    <!-- <img :src="firstiImg" alt="" /> -->
                    <span><span></span>{{ item.category_type }}</span>
                    <span>待发货</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div class="detail-title">
                    <div v-for="(itemes, ind) in item.goods" :key="ind">
                      <div>
                        <img :src="itemes.go_img" alt="" />
                      </div>
                      <div>
                        <p>{{ itemes.go_title }}</p>
                        <p v-if="itemes.format">{{ itemes.format.name }}</p>
                        <div class="detail-title-li">
                          <span></span>
                          <span
                            ><span class="size">X</span
                            >{{ itemes.go_num }}</span
                          >
                        </div>
                        <div v-if="item.category_pid != 3 && item.category_pid != 17" class="detail-title-tw">赠与≈{{ Math.round(item.gmo_give_num * item.hm_price) }} CNY</div>
                      </div>
                    </div>
                  </div>
                  <!-- <div class="detail-list-one">
                    <span>商品总额</span>
                    <span>￥{{ item.gmo_total_price }}</span>
                  </div>
                  <div class="detail-list-two">
                    <span>会员等级优惠</span>
                    <span>-￥{{ item.gmo_discount_num }}</span>
                  </div> -->
                  <div class="detail-list-three">
                    <span>实付款: </span>
                    <span v-if="item.category_type == 4"
                      >{{ item.gmo_pay_num }} 积分</span
                    >
                    <span v-else>
                      <span v-if="item.category_pid == 3">
                        {{ item.total_pay_huo_price }}{{ item.category_pid == 3 ? '金米' : item.give_currency.currency_name }}
                      </span>
                      <span v-else-if="item.category_pid == 17">
                        ￥{{ item.total_pay_cny_price }}
                      </span>
                      <span v-else>
                        ￥{{ item.total_pay_cny_price }}≈{{
                        item.total_pay_huo_price
                      }}{{ item.category_pid == 3 ? "金米" : item.give_currency.currency_name }}
                      </span>
                    </span>
                  </div>
                  <div>
                    <!-- <button @click="cancelMoney(item.gmo_id)">一键退款</button> -->
                    <button @click="tipSend">提醒发货</button>
                  </div>
                  <div class="details-list-five" v-if="item.category_type == 5">
                    <div class="list-five-title">
                      <!-- 未拼团 -->
                      <div v-if="item.group_data.status == 0">
                        <div>
                          <div>
                            <img :src="ptImg" alt="" />
                            <img :src="ptImg" alt="" />
                            <p>未拼团</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团中 -->
                      <div v-else-if="item.group_data.status == 1">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团中({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团成功 -->
                      <div v-else-if="item.group_data.status == 2">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                            />
                            <p>拼团成功({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团关闭 -->
                      <div v-else-if="item.group_data.status == 3">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团关闭({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
            </van-list>
          </van-pull-refresh>
        </div>
        <!-- 待收货 -->
        <div class="scroll-list" v-else-if="active == 3">
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
                  <!-- <p>No Data</p> -->
                </div>
                <li class="detail" v-for="(item, index) in four" :key="index">
                  <div class="detail-top" @click="jumpDet(item.gmo_id)">
                    <!-- <img :src="firstiImg" alt="" /> -->
                    <span><span></span>{{ item.category_type }}</span>
                    <span>已发货</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div class="detail-title">
                    <div v-for="(itemes, ind) in item.goods" :key="ind">
                      <div>
                        <img :src="itemes.go_img" alt="" />
                      </div>
                      <div>
                        <p>{{ itemes.go_title }}</p>
                        <p v-if="itemes.format">{{ itemes.format.name }}</p>
                        <div class="detail-title-li">
                          <span></span>
                          <span
                            ><span class="size">X</span
                            >{{ itemes.go_num }}</span
                          >
                        </div>
                        <div v-if="item.category_pid != 3 && item.category_pid != 17" class="detail-title-tw">赠与≈{{ Math.round(item.gmo_give_num * item.hm_price) }} CNY</div>
                      </div>
                    </div>
                  </div>
                  <!-- <div class="detail-list-one">
                    <span>商品总额</span>
                    <span>￥{{ item.gmo_total_price }}</span>
                  </div>
                  <div class="detail-list-two">
                    <span>会员等级优惠</span>
                    <span>-￥{{ item.gmo_discount_num }}</span>
                  </div> -->
                  <div class="detail-list-three">
                    <span>实付款: </span>
                    <span v-if="item.category_type == 4"
                      >{{ item.gmo_pay_num }} 积分</span
                    >
                    <span v-else>
                      <span v-if="item.category_pid == 3">
                        {{ item.total_pay_huo_price }}{{ item.category_pid == 3 ? '金米' : item.give_currency.currency_name }}
                      </span>
                      <span v-else-if="item.category_pid == 17">
                        ￥{{ item.total_pay_cny_price }}
                      </span>
                      <span v-else>
                        ￥{{ item.total_pay_cny_price }}≈{{
                        item.total_pay_huo_price
                      }}{{ item.category_pid == 3 ? "金米" : item.give_currency.currency_name }}
                      </span>
                    </span>
                  </div>
                  <div>
                    <!-- <button @click="jumpDetList(item.gmo_id)">查看物流</button> -->
                    <button @click="confirm(item.gmo_id)">确认收货</button>
                  </div>
                  <div class="details-list-five" v-if="item.category_type == 5">
                    <div class="list-five-title">
                      <!-- 未拼团 -->
                      <div v-if="item.group_data.status == 0">
                        <div>
                          <div>
                            <img :src="ptImg" alt="" />
                            <img :src="ptImg" alt="" />
                            <p>未拼团</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团中 -->
                      <div v-else-if="item.group_data.status == 1">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团中({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团成功 -->
                      <div v-else-if="item.group_data.status == 2">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                            />
                            <p>拼团成功({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团关闭 -->
                      <div v-else-if="item.group_data.status == 3">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团关闭({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
            </van-list>
          </van-pull-refresh>
        </div>
        <!-- 待使用 -->
        <div class="scroll-list" v-else-if="active == 4">
          <van-pull-refresh
            v-model="fiveDownLoading"
            @refresh="onRefresh('five')"
            success-text="刷新成功"
          >
            <van-list
              v-model="fiveUpLoading"
              :finished="fiveFinished"
              :finished-text="finishedText"
              @load="onLoad('five')"
              :offset="offset"
            >
              <ul class="list-wrap">
                <div v-show="isfive" class="no-data">
                  <img :src="noDataImg" />
                  <!-- <p>No Data</p> -->
                </div>
                <li class="detail" v-for="(item, index) in five" :key="index">
                  <div
                    class="detail-top"
                    @click="jumpDet(item.gmo_id, item.category_pid)"
                  >
                    <!-- <img :src="firstiImg" alt="" /> -->
                    <span><span></span>{{ item.category_type }}</span>
                    <span>待提货</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div class="detail-title">
                    <div v-for="(itemes, ind) in item.goods" :key="ind">
                      <div>
                        <img :src="itemes.go_img" alt="" />
                      </div>
                      <div>
                        <p>{{ itemes.go_title }}</p>
                        <p v-if="itemes.format">{{ itemes.format.name }}</p>
                        <div class="detail-title-li">
                          <span></span>
                          <span
                            ><span class="size">X</span
                            >{{ itemes.go_num }}</span
                          >
                        </div>
                        <div v-if="item.category_pid != 3 && item.category_pid != 17" class="detail-title-tw">赠与≈{{ Math.round(item.gmo_give_num * item.hm_price) }} CNY</div>
                      </div>
                    </div>
                  </div>
                  <!-- <div class="detail-list-one">
                    <span>商品总额</span>
                    <span>¥{{ item.gmo_total_price }}</span>
                  </div>
                  <div class="detail-list-two">
                    <span>会员等级优惠</span>
                    <span>-¥{{ item.gmo_discount_num }}</span>
                  </div> -->
                  <div class="detail-list-three">
                    <span>实付款: </span>
                    <span v-if="item.category_type == 4"
                      >{{ item.gmo_pay_num }} 积分</span
                    >
                    <span v-else>
                      <span v-if="item.category_pid == 3">
                        {{ item.total_pay_huo_price }}{{ item.category_pid == 3 ? '金米' : item.give_currency.currency_name }}
                      </span>
                      <span v-else-if="item.category_pid == 17">
                        ￥{{ item.total_pay_cny_price }}
                      </span>
                      <span v-else>
                        ￥{{ item.total_pay_cny_price }}≈{{
                        item.total_pay_huo_price
                      }}{{ item.category_pid == 3 ? "金米" : item.give_currency.currency_name }}
                      </span>
                    </span>
                  </div>
                  <div v-if="item.gmo_status == 6">
                    <!-- <button v-if="item.goods[0].go_goods_id != 1" @click="jumpDetList(item.gmo_id)">
                      查看物流
                    </button> -->
                    <!-- <button @click="cancelMoney(item.gmo_id)" v-if="item.store_use == 0">一键退款</button> -->
                    <button @click="confirm(item.gmo_id)">确认收货</button>
                  </div>
                  <div class="details-list-five" v-if="item.category_type == 5">
                    <div class="list-five-title">
                      <!-- 未拼团 -->
                      <div v-if="item.group_data.status == 0">
                        <div>
                          <div>
                            <img :src="ptImg" alt="" />
                            <img :src="ptImg" alt="" />
                            <p>未拼团</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团中 -->
                      <div v-else-if="item.group_data.status == 1">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团中({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团成功 -->
                      <div v-else-if="item.group_data.status == 2">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                            />
                            <p>拼团成功({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团关闭 -->
                      <div v-else-if="item.group_data.status == 3">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团关闭({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
            </van-list>
          </van-pull-refresh>
        </div>
        <!-- 已完成 -->
        <div class="scroll-list" v-else-if="active == 5">
          <van-pull-refresh
            v-model="sixDownLoading"
            @refresh="onRefresh('six')"
            success-text="刷新成功"
          >
            <van-list
              v-model="sixUpLoading"
              :finished="sixFinished"
              :finished-text="finishedText"
              @load="onLoad('six')"
              :offset="offset"
            >
              <ul class="list-wrap">
                <div v-show="issix" class="no-data">
                  <img :src="noDataImg" />
                  <!-- <p>No Data</p> -->
                </div>
                <li class="detail" v-for="(item, index) in six" :key="index">
                  <div
                    class="detail-top"
                    @click="jumpDet(item.gmo_id, item.category_pid)"
                  >
                    <!-- <img :src="firstiImg" alt="" /> -->
                    <span><span></span>{{ item.category_type }}</span>
                    <span>已完成</span>
                    <img :src="nextImg" alt="" />
                  </div>
                  <div class="detail-title">
                    <div v-for="(itemes, ind) in item.goods" :key="ind">
                      <div>
                        <img :src="itemes.go_img" alt="" />
                      </div>
                      <div>
                        <p>{{ itemes.go_title }}</p>
                        <p v-if="itemes.format">{{ itemes.format.name }}</p>
                        <div class="detail-title-li">
                          <span></span>
                          <span
                            ><span class="size">X</span
                            >{{ itemes.go_num }}</span
                          >
                        </div>
                        <div v-if="item.category_pid != 3 && item.category_pid != 17" class="detail-title-tw">赠与≈{{ Math.round(item.gmo_give_num * item.hm_price) }} CNY</div>
                      </div>
                    </div>
                  </div>
                  <!-- <div class="detail-list-one">
                    <span>商品总额</span>
                    <span>￥{{ item.gmo_total_price }}</span>
                  </div>
                  <div class="detail-list-two">
                    <span>会员等级优惠</span>
                    <span>-￥{{ item.gmo_discount_num }}</span>
                  </div> -->
                  <div class="detail-list-three">
                    <span>实付款: </span>
                    <span v-if="item.category_type == 4"
                      >{{ item.gmo_pay_num }} 积分</span
                    >
                    <span v-else>
                      <span v-if="item.category_pid == 3">
                        {{ item.total_pay_huo_price }}{{ item.category_pid == 3 ? '金米' : item.give_currency.currency_name }}
                      </span>
                      <span v-else-if="item.category_pid == 17">
                        ￥{{ item.total_pay_cny_price }}
                      </span>
                      <span v-else>
                        ￥{{ item.total_pay_cny_price }}≈{{
                        item.total_pay_huo_price
                      }}{{ item.category_pid == 3 ? "金米" : item.give_currency.currency_name }}
                      </span>
                    </span>
                  </div>
                  <div v-if="item.gmo_status == 4">
                    <!-- <button v-if="item.goods[0].go_goods_id != 1" @click="jumpDetList(item.gmo_id)">
                      查看物流
                    </button> -->
                    <!-- <button>评价</button> -->
                    <!-- <button v-if="item.category_pid == 3 && item.is_subscribe == 1" class="y-list-w" @click="goHp('/prerecord', item.gmo_id)">预约记录</button> -->
                  </div>
                  <div class="details-list-five" v-if="item.category_type == 5">
                    <div class="list-five-title">
                      <!-- 未拼团 -->
                      <div v-if="item.group_data.status == 0">
                        <div>
                          <div>
                            <img :src="ptImg" alt="" />
                            <img :src="ptImg" alt="" />
                            <p>未拼团</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团中 -->
                      <div v-else-if="item.group_data.status == 1">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团中({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团成功 -->
                      <div v-else-if="item.group_data.status == 2">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                            />
                            <p>拼团成功({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                      <!-- 拼团关闭 -->
                      <div v-else-if="item.group_data.status == 3">
                        <div>
                          <div>
                            <img
                              :src="item.group_data.user_list[0].head"
                              alt=""
                            />
                            <img
                              :src="item.group_data.user_list[1].head"
                              alt=""
                              v-if="
                                item.group_data.group_num > 2 &&
                                item.gmo_status == 1
                              "
                            />
                            <img :src="ptImg" alt="" v-else />
                            <p>拼团关闭({{ item.group_data.group_num }}人团)</p>
                          </div>
                          <div></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
            </van-list>
          </van-pull-refresh>
        </div>
      </van-tabs>
    </div>
    <div class="maskak" v-if="fullBoll"></div>
    <div class="changeShow" v-if="fullBoll">
      <div>
        <img src="../../assets/shop/xwu-img.png" alt="" @click="cenelAll" />
      </div>
      <p v-if="payType == 2 || 1">该商品需支付{{ payType == 2 ? `${payNum - 0}金米` : payType == 17 ? `￥${totalPayNum - 0}` : `￥${totalPayNum - 0}≈${payNum - 0}赠与收益` }}</p>
      <p v-else>该商品需支付￥{{ payNum }},请选择支付方式</p>
      <!-- <div v-if="payType == 1">
        <div
          class="mask-tak"
          v-for="(item, index) in payData"
          :key="index"
          @click="changePay(index)"
        >
          <div>
            <img src="../../assets/shop/gwq.png" v-if="item.id == 1" />
            <img
              src="../../assets/shop/cart/mlfxjk_icon.png"
              v-else-if="item.id == 3"
            />
            <img src="../../assets/shop/zh.png" v-else />
            <span v-if="item.id == 4">{{ item.name }}</span>
            <span v-else>{{ item.name }}(余额{{ item.money }})</span>
          </div>
        </div>
      </div> -->
      <!-- 消费积分支付 -->
      <div v-if="payType == 2">
        <div class="mask-tak" v-for="(item, index) in payData" :key="index">
          <div>
            <img src="../../assets/shop/fire_img.png" class="payicon" />
            <span>{{ item.name }}(可用{{ item.money }})</span>
          </div>
          <div>
            <img src="../../assets/shop/cart/wei-img.png" class="active" />
          </div>
        </div>
      </div>
      <div v-else>
        <div
          class="mask-tak"
          v-for="(item, index) in payData"
          :key="index"
          @click="changePay(item.id)"
        >
          <div>
            <img :src="item.img" alt="" />
            {{ item.name }}{{item.id == 1 ? `(余额${item.money})` : ''}}
          </div>
          <div>
            <img :src="item.id == isA ? isCheck : isCheckNo" alt="" />
          </div>
        </div>
      </div>
      <div class="line"></div>
      <div @click="shopPay">
        <button>确认支付</button>
      </div>
    </div>
    <!-- 输入密码弹窗 -->
    <KeyBord
      ref="keyBord"
      @data-password="buttonNum"
      @handleShow="handleShow"
    ></KeyBord>
    <!-- 放弃付款弹窗 -->
    <div class="popup" v-show="toastShow">
      <div class="popup-box">
        <div class="popup-title">
          <p>确认放弃付款吗?</p>
          <p class="title">超过支付时效后,订单会被取消哦!</p>
        </div>

        <div class="button-grounp">
          <div class="button button-cancel" @click="goOrder">确定离开</div>
          <div class="button button-determine" @click="shopPay">继续支付</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import {
  get_orders_list,
  cancel_order,
  pay_type,
  pay_orders,
  confirm_order,
  sub_refund,
} from "@/http/api.js";
import TopHeader from "@/components/TopHeader";
export default {
  name: "orders",
  components: {
    TopHeader,
  },
  inject: ["reload"],
  data() {
    return {
      info: {
        title: "订单管理",
      },
      ptImg: require("../../assets/shop/ptdd_wct_img.png"),
      tabLabels: [
        {
          label: "全部",
          isFirst: true,
          type: "one",
        },
        {
          label: "待付款",
          isFirst: true,
          type: "two",
        },
        {
          label: "待发货",
          isFirst: true,
          type: "three",
        },
        {
          label: "待收货",
          isFirst: true,
          type: "four",
        },
        {
          label: "待提货",
          isFirst: true,
          type: "five",
        },
        {
          label: "已完成",
          isFirst: true,
          type: "six",
        },
      ],
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
      sixUpLoading: false, //上拉加载
      sixFinished: false, //上拉加载完毕
      sixDownLoading: false, //下拉刷新
      finishedText: "沒有更多了",
      noDataImg: require("../../assets/shop/no_data.png"),
      firstiImg: require("../../assets/shop/dingd_icon_1.png"),
      nextImg: require("../../assets/shop/dingd_icon_2.png"),
      offset: 100,
      isone: false,
      istwo: false,
      isthree: false,
      isfour: false,
      isfive: false,
      issix: false,
      one: [],
      two: [],
      three: [],
      four: [],
      five: [],
      six: [],
      oneOption: {
        status: 0,
        page: 1,
        rows: 10,
      },
      twoOption: {
        status: 2,
        page: 1,
        rows: 10,
      },
      threeOption: {
        status: 1,
        page: 1,
        rows: 10,
      },
      fourOption: {
        status: 3,
        page: 1,
        rows: 10,
      },
      fiveOption: {
        status: 6,
        page: 1,
        rows: 10,
      },
      sixOption: {
        status: 4,
        page: 1,
        rows: 10,
      },
      dataOption: {},
      active: Number(localStorage.getItem("orderActiveId"))
        ? Number(localStorage.getItem("orderActiveId"))
        : 0,
      payData: [],
      isCheck: require("../../assets/shop/cart/wei-img.png"),
      isCheckNo: require("../../assets/shop/cart/dhi-img.png"),
      isA: 1, // 支付方式id选择
      fullBoll: false, // 支付选择开关
      toastShow: false, // 是否继续支付开关
      payNum: "", // 支付总金额
      payType: "", // 支付类型=4是积分支付
      payTypeId: "", // 积分支付最后付款ID
      totalPayNum: "", //支付人民币金额
    };
  },
  methods: {
    goHp(urls, id) {
      let url = `${urls}?id=${id}`;
      this.$router.push({ path: url });
    },
    // 确认收货
    confirm(_id) {
      let option = {};
      option.gmo_id = _id;
      this.$http.post(confirm_order, option).then(({ data }) => {
        if (data.code == 10000) {
          window.toast_txt(data.message);
          setTimeout(() => {
            this.reload();
          }, 1000);
        } else {
          window.toast_txt(data.message);
        }
      });
    },
    goBack() {
      // type=1是从支付过来，返回首页
      if (this.$platform == "android") {
        apps.exit();
      } else {
        if (this.$route.query.type == 1) {
          this.$router.push({ path: "/" });
        } else {
          this.$router.back();
        }
      }

      localStorage.removeItem("orderActiveId");
    },
    tipSend() {
      window.toast_txt("已通知卖家发货");
    },
    jumpDet(_id, type) {
      // 跳转到详情 type=2，返回上一级是回退
      // console.log(type);
      let url = "";
      // if (type == 2) {
      //   url = "/ordDet/" + _id + "/" + 2;
      // } else {
      //   url = "/ordersDet/" + _id + "/" + 2;
      // }
      url = "/ordersDet/" + _id + "/" + 2;
      this.$router.push({ path: url });
    },
    // 查看物流
    jumpDetList(_id) {
      let url = "/logistics" + "?id=" + _id;
      this.$router.push({ path: url });
    },
    cancelOrder(_id) {
      let option = {};
      option.gmo_id = _id;
      this.$http.post(cancel_order, option).then(({ data }) => {
        if (data.code == 10000) {
          window.toast_txt(data.message);
          this.reload();
        }
      });
    },
    cancelMoney(_id) {
      let option = {};
      option.gmo_id = _id;
      this.$http.post(sub_refund, option).then(({ data }) => {
        if (data.code == 10000) {
          window.toast_txt(data.message);
          this.reload();
        }
      });
    },
    onLoad(type) {
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
    onRefresh(type) {
      let key = type + "Option";
      this[key].page = 1;
      this[type] = [];
      this.onLoad(type);
    },
    tabHandler(idx) {
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
        localStorage.setItem("orderActiveId", idx);
      });
    },
    fetchList(obj) {
      return new Promise((resolve, reject) => {
        let option = {
          ...obj,
        };
        this.$http.post(get_orders_list, option).then(({ data }) => {
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
    go_pay(_num, _id, type, num_id, totalNum) {
      // 所需支付的数量
      this.payNum = _num;
      this.totalPayNum = totalNum;
      // 订单id
      this.dataOption.gmo_id = _id;
      // type等于4是积分支付类型，传数字2去请求积分的支付方式
      // if (type == 4) {
      //   this.payType = 2;
      // }
      // 1和2 分别是乐购区和自提区 3是置换区
      if (num_id == 2 || num_id == 1) {
        this.payType = 1;
      }else if (num_id == 16) {
        this.payType = 16;
      }else if (num_id == 17) {
        this.payType = 17;
      }else {
        this.payType = 2;
      }
      // 点击去支付的时候再请求支付方式
      this._pay_type();
    },
    cenelAll() {
      this.fullBoll = false;
      this.isA = this.payData[0].id;
      this.payType = "";
      this.payTypeId = "";
    },
    _pay_type() {
      let option = {
        type: "",
      };
      // 支付方式类型id 1为微信和贡献值 2为积分
      if (this.payType == 2) {
        option.type = this.payType;
      } else if (this.payType == 99 || this.payType == 16 || this.payType == 17) {
        option.type = this.payType;
      } else {
        option.type = 1;
      }
      this.$http.post(pay_type, option).then(({ data }) => {
        if (data.code == 10000) {
          this.payData = [];
          if(this.payType == 2) {
            this.payData = data.result.pay_type;
          }else if(this.payType == 17) {
            let obj = {};
            data.result.pay_type.forEach((item,index) =>{
              let obj = {
                id: item.id,
                name: item.name,
                is_recommend: item.is_recommend,
              };
              if(item.id == 3){
                obj.img = require("../../assets/shop/cart/vxzf_icon.png");
              }else if(item.id == 4){
                obj.img = require("../../assets/shop/cart/zfbzf_icon.png");
              }
              this.payData.push(obj);
              this.isA=this.payData[0].id;
            });
          }else {
            let obj = {};
            data.result.pay_type.forEach((item,index) =>{
              let obj = {
                id: item.id,
                name: item.name,
                is_recommend: item.is_recommend,
              };
              if(item.id == 1) {
                // obj.img = require("../../assets/shop/gwq.png");
                obj.img = require("../../assets/shop/fire_img.png");
                obj.money = item.money;
              }else if(item.id == 3){
                obj.img = require("../../assets/shop/cart/vxzf_icon.png");
              }else if(item.id == 4){
                obj.img = require("../../assets/shop/cart/zfbzf_icon.png");
              }
              this.payData.push(obj);
              this.isA=this.payData[0].id;
            });
          }
          
          // 弹出支付选择
          this.fullBoll = true;
        } else {
          window.toast_txt(data.message);
        }
      });
    },
    // 切换支付方式
    changePay(_index) {
      if (Number(this.isA) != Number(_index)) {
        this.isA = _index;
      };
    },
    buttonNum(data) {
      this.dataOption.pay_pwd = data;
      this.$http.post(pay_orders, this.dataOption).then(({ data }) => {
        if (data.code == 10000) {
          this.$toast(data.message);
          setTimeout(() => {
            this.reload();
          }, 1000);
        } else {
          this.$toast(data.message);
        }
      });
      //关闭付款弹窗
      this.$refs.keyBord.closeKey();
    },
    //接收子组件传递 询问框显示
    handleShow(show) {
      this.toastShow = show;
      this.fullBoll = false;
    },
    //放弃付款
    goOrder() {
      // 关闭支付选择框
      this.cenelAll();
      this.toastShow = false;
    },
    shopPay() {
      this.toastShow = false;
      this.dataOption.pay_type = this.isA; //支付方式id
      // if(this.payType == 1) {
      //   this.dataOption.pay_type = this.payType; //消费积分支付方式id
      // }
      // this.$refs.keyBord.showKey();
      if (this.payType == 2) {
        this.isA = this.payType; // this.payType==2 是金米支付
      }
      //判断支付方式 1为赠与收益支付，3为微信支付 为积分支付
      if (this.isA == 1 || this.isA == 2) {
        this.$refs.keyBord.showKey();
        this.dataOption.pay_type = this.isA;
      }  else if(this.isA == 3) {
        // 微信支付
        let obj = {};
        obj.gmo_id = this.dataOption.gmo_id;
        obj.pay_type = this.isA;
        this.$http.post(pay_orders, obj).then(({ data }) => {
          if (data.code == 10000) {
            let url = data.result.pay_url;
            if (this.$platform == "android") {
              apps.openBrowser(url);
            } else {
              window.location.href = url + '?v=' + (new Date().getTime());
            }
            // const local = window.location.host; //授权域名
            // let urlenCode = "";
            // urlenCode = encodeURIComponent(
            //   `http://${local}/#/payConfirm?ids=${obj.gmo_id}&type=3`
            // ); //编码
            // window.location.href = `${data.result.wx_pay.mweb_url}&redirect_url=${urlenCode}`;
          } else {
            this.$toast(data.message);
          }
        });
      }else if(this.isA == 4) {
        // 支付宝支付
        let obj = {};
        obj.gmo_id = this.dataOption.gmo_id;
        obj.pay_type = this.isA;
        this.$http.post(pay_orders, obj).then(({ data }) => {
          // if (data.code == 10000) {
          //   sessionStorage.setItem('html',data.result.wx_pay);
          //   this.$router.push({path: '/alipay'});
          // } else {
          //   this.$toast(data.message);
          // }
          if (data.code == 10000) {
            let url = data.result.pay_url;
            if (this.$platform == "android") {
              apps.openBrowser(url);
            } else {
              window.location.href = url + '?v=' + (new Date().getTime());
            }
          } else {
            this.$toast(data.message);
          }
        });
      }
    },
    backChange() {
      this.goBack();
    },
  },
  beforeCreate() {
    // 如果是从安卓打开新的webview
    let urlId = window.location.hash;
    urlId = urlId.split("?id=");
    urlId = urlId[1];
    if (!localStorage.getItem("orderActiveId")) {
      if (this.$route.query.id) {
        localStorage.setItem("orderActiveId", this.$route.query.id);
        return false;
      } else {
        if (urlId) {
          localStorage.setItem("orderActiveId", urlId);
        }
      }
    }
  },
  mounted() {
    if (window.history && window.history.pushState) {
      history.pushState(null, null, document.URL);
      window.addEventListener("popstate", this.backChange, false); //false阻止默认事件
    }
  },
  destroyed() {
    window.removeEventListener("popstate", this.backChange, false); //false阻止默认事件
  },
};
</script>

<style lang="scss" scoped>
header {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 44px;
  background: #f7f5f6;
  color: #000;
  text-align: center;
  line-height: 44px;
  display: flex;
  align-items: center;
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

header h3 {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: bold;
}

/deep/ .van-tabs__line {
  color: #ff461e;
  background-color: #ff461e;
}
/deep/ .van-tab--active {
  color: #ff461e;
}
/deep/ .van-tabs__nav {
  background: #f7f5f6;
}
/deep/ .van-tabs__wrap {
  position: fixed;
  z-index: 2;
  width: 100%;
}
.content {
  z-index: 2;
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  overflow: auto;
  background: #f7f5f6;
  -webkit-overflow-scrolling: touch;
  font-family: "PingFang SC";
  .scroll-list {
    padding: 0 15px;
    padding-top: 10px;
    box-sizing: border-box;
    height: calc(100% - 44px);
    .no-data {
      margin-top: 70px;
      text-align: center;
      font-size: 14px;
      color: #999;
      p {
        margin-top: 24px;
      }
      img {
        width: 160px;
      }
    }
    .detail {
      width: 100%;
      height: auto;
      padding: 0 14px;
      padding-bottom: 18px;
      box-sizing: border-box;
      background: #ffffff;
      box-shadow: -2px 4px 20px 0px rgba(124, 70, 3, 0.05);
      border-radius: 15px;
      margin-bottom: 15px;
      > .detail-top {
        font-size: 14px;
        font-weight: normal;
        color: #ff461e;
        display: flex;
        align-items: center;
        padding: 10px 0;
        box-sizing: border-box;
        > img:first-child {
          width: 19px;
          height: 20px;
          margin-right: 5px;
        }
        > span:nth-child(1) {
          display: flex;
          align-items: center;
          height: 20px;
          color: #353333;
          > span {
            width: 9px;
            height: 9px;
            background: #ff461e;
            border-radius: 50%;
            opacity: 1;
            margin-right: 4px;
            display: inline-block;
          }
        }
        > span:nth-child(2) {
          margin-left: auto;
        }
        > img:last-child {
          width: 6px;
          height: 12px;
          margin-left: 10px;
        }
      }
      > .detail-title {
        > div {
          width: 100%;
          height: 80px;
          display: flex;
          align-items: center;
          border-bottom: 1px solid #d3d3d3;
          padding-bottom: 30px;
          padding-top: 25px;
          > div:nth-child(1) {
            padding-left: 5px;
            > img {
              width: 70px;
              height: 70px;
              border-radius: 8px;
            }
          }
          > div:nth-child(2) {
            font-size: 16px;
            color: #333333;
            margin-left: 10px;
            flex: 1;
            > p {
              text-overflow: -o-ellipsis-lastline;
              overflow: hidden;
              text-overflow: ellipsis;
              display: -webkit-box;
              -webkit-line-clamp: 1;
              -webkit-box-orient: vertical;
            }
            p:nth-child(2) {
              font-size: 12px;
              color: #333;
              margin-top: 10px;
            }
            > .detail-title-li {
              display: flex;
              padding-top: 11px;
              align-items: flex-end;
              > span:nth-child(1) {
                color: #333;
              }
              > span:nth-child(2) {
                font-size: 12px;
                flex: 1;
                text-align: right;
              }
              .size {
                font-size: 8px;
              }
            }
            > .detail-title-tw {
              margin-top: 10px;
              height: 27px;
              background: #ffe1db;
              opacity: 1;
              border-radius: 6px;
              color: #ff461e;
              font-size: 14px;
              padding: 0 10px;
              // width: 180px;
              width: fit-content;
              display: flex;
              justify-content: center;
              align-items: center;
            }
          }
        }
      }
      > .detail-list-one {
        font-size: 12px;
        color: #333333;
        display: flex;
        padding: 14px 0;
        box-sizing: border-box;
        > span:last-child {
          margin-left: auto;
        }
      }
      > .detail-list-two {
        font-size: 12px;
        color: #333333;
        display: flex;
        padding-bottom: 9px;
        box-sizing: border-box;
        > span:last-child {
          margin-left: auto;
        }
      }
      > .detail-list-three {
        font-size: 14px;
        color: #333333;
        box-sizing: border-box;
        text-align: right;
        margin: 15px 0;
        > span:last-child {
          color: #ff461e;
        }
      }
      > div:nth-child(4) {
        margin-top: 10px;
        text-align: right;
        color: #333;
        box-sizing: border-box;
        font-size: 14px;
        > button {
          width: 90px;
          height: 36px;
          border: 1px solid #ff461e;
          border-radius: 4px;
          background: transparent;
          outline: none;
          color: #ff461e;
        }
        > button:last-child {
          color: #ffffff;
          min-width: 106px;
          height: 36px;
          margin-left: 10px;
          font-size: 14px;
          background: #ff461e;
          border: none;
        }
        .y-list-x {
          background: #ff461e !important;
          border-radius: 4px;
          border: none;
          width: 102px !important;
        }
        .y-list-w {
          background: linear-gradient(
            90deg,
            #6eb7de 0%,
            #2492cb 100%
          ) !important;
          border-radius: 4px;
          border: none;
          width: 102px !important;
        }
      }
      > .details-list-five {
        border-top: 1px solid #d3d3d3;
        margin-top: 15px;
        > .list-five-title {
          margin-top: 15px;
          img {
            width: 34px;
            height: 34px;
            border-radius: 50%;
          }
          > div > div {
            display: flex;
            > div:nth-child(1) {
              min-width: 100px;
              text-align: center;
              > img:nth-child(2) {
                margin-left: -10px;
              }
              p {
                width: fit-content;
                height: 14px;
                line-height: 14px;
                background: rgba(254, 254, 254, 0.94);
                border: 1px solid #e7c285;
                border-radius: 7px;
                font-size: 10px;
                font-family: "Source Han Sans CN";
                font-weight: bold;
                color: #9e7111;
                text-align: center;
                margin: 0 auto;
                margin-top: -10px;
                position: relative;
                padding: 0 10px;
                box-sizing: border-box;
              }
            }
          }
        }
      }
    }
  }
}
.y-list-x {
  background: #ff461e;
  border-radius: 4px;
  border: none;
  width: 102px !important;
}
.y-list-w {
  background: linear-gradient(90deg, #6eb7de 0%, #2492cb 100%);
  border-radius: 4px;
  border: none;
  width: 102px !important;
}
.scroll-list .list-wrap > li:nth-child(2) {
  margin-top: 46px;
}
.changeShow {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 90%;
  background: #ffffff;
  border-radius: 6px;
  padding: 0 12px;
  box-sizing: border-box;
  padding-top: 12px;
  box-shadow: 0px -1px 0px 0px rgba(230, 230, 230, 1);
  font-family: "PingFang SC";
  z-index: 5;
  > div:nth-child(1) {
    width: 100%;
    text-align: right;
    > img {
      width: 21px;
    }
  }
  > p {
    text-align: center;
    font-size: 18px;
    font-weight: bold;
    color: #1c1c1c;
    padding: 24px 0;
  }
  .mask-tak {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 24px;
    padding-bottom: 24px;
    font-weight: 500;
    font-size: 12px;
    color: #1c1c1c;
    > div:nth-child(1) {
      > img {
        width: 20px;
        vertical-align: middle;
      }
      > span {
        padding-left: 8px;
      }
    }
    > div:nth-child(2) {
      > img {
        width: 20px;
        height: 20px;
        vertical-align: middle;
      }
    }
  }
  > .line {
    border-top: 1px solid #e8e8e8;
    padding-bottom: 30px;
  }
  > div:last-child {
    font-weight: 500;
    text-align: center;
    padding-bottom: 30px;
    color: #fff;
    > button {
      font-size: 16px;
      width: 191px;
      height: 40px;
      outline: none;
      border: none;
      background: #ff461e;
      border-radius: 6px;
    }
  }
}
.maskak {
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  background: #1d1b1b;
  opacity: 0.53;
  z-index: 4;
}
.popup {
  position: absolute;
  left: 0;
  top: 0;
  width: 100%;
  min-height: 100%;
  background: rgba(0, 0, 0, 0.45);
  z-index: 3;
  .popup-box {
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 99999;
    width: 292px;
    height: 186px;
    border-radius: 12px;
    background-color: #ffffff;
    color: #ffffff;
    .popup-title {
      font-size: 18px;
      color: #1c1c1c;
      text-align: center;
      height: 127px;
      border-bottom: 1px solid #e8e8e8;

      p {
        margin-top: 39px;
      }
      .title {
        margin-top: 16px;
        font-size: 16px;
      }
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
        color: #FF461E;
      }
    }
  }
}
</style>