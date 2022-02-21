if (typeof window.jQuery === 'undefined') {
    throw "没有加载JQuery类库。";
}

if (typeof member_details === 'undefined') {
    //获取用户资料（点击用户ID，弹窗加载资料页面）
    function member_details(id) {
        if (typeof id === 'undefined' || !parseInt(id) > 0) {
            layer.alert("没有获取到用户ID", {icon: 5});
            return false;
        }

        //弹出即全屏
       $("body").css("overflow-y","hidden");

        var index = layer.open({
            type: 2,
            title: "用户资料 UID:" + id,
            content: _deel.req_member_details_url + "?member_id=" + id,
            area: ['820px', '495px'],
            maxmin: true,
            scrollbar: false, //屏蔽浏览器滚动条
            end:function(){
                $("body").css("overflow-y","auto");
            }
        });
        layer.full(index);
    }
}
if (typeof member_ab_details === 'undefined') {
    //获取用户资料（点击用户ID，弹窗加载资料页面）
    function member_ab_details(type,tm,c) {
        if (typeof type === 'undefined' || !parseInt(type) > 0) {
            layer.alert("类型错误", {icon: 5});
            return false;
        }
        //弹出即全屏
        var index = layer.open({
            type: 2,
            title: tm+" 日" + c,
            content: _deel.req_member_details_url + "?type=" + type+"&tm="+tm,
            area: ['820px', '495px'],
            maxmin: true,
            scrollbar: false, //屏蔽浏览器滚动条
        });
        layer.full(index);
    }
}
$(document).ready(function () {
    //用户资料页面
    if (typeof _deel !== 'undefined' && typeof _deel.method !== 'undefined' && _deel.method === 'details') {
        var _detail = new _detail(),
            _status = {
                hyxx: true,
                czjl: true,
                nbhz: true,
                rgjl: true,
                jyjl: true,
                tbjl: true,
                cbjl: true,
                otc: true,
                zsjl: true,
                cbsx:true,
                yhzb:true,
                xrp:true,
            },
            _config_url = {
                hyxx: 'user_info', //会员信息
                czjl: 'user_pay_log', //充值记录
                nbhz: 'mutualTransfer', //内部互转
                rgjl: 'user_rengou', //认购记录
                jyjl: 'user_transaction', //交易记录
                tbjl: 'user_turn_out_currency', //提积分记录
                cbjl: 'user_turn_on_currency', //充积分记录
                otc: 'orders_trade_log',  //OTC记录
                zsjl: 'rewardList',  //奖励记录
                cbsx: 'money_interest',  //持币生息记录
                yhzb: 'accountbook',  //用户帐本记录
                xrp:'xrp_log',//用户xrp账本记录
            },
            // _ajax_url = _deel.module + '/member/',
            _ajax_url = "",
            _ajax_data = {
                user_id: _deel.user_id
            },
            _default_name = 'hyxx';

        //初始化加载第一个tab
        if (_status[_default_name]) {
            _ajax(_default_name, _ajax_url + _config_url[_default_name], _ajax_data);
        }

        //详情页面tab功能
        $('.user_profile ul.nav li').click(function () {
            var _ = $(this),
                name = _.attr('data-name'),url = _ajax_url +_config_url[name];

            _.parent().find('li').removeClass('active');
            _.addClass('active');

            $('.profile_content > div').hide();
            $('.' + name).show();

            //本次弹窗一个TAB只发起一次网络请求，积分类型选择除外
            if (_status[name]) {
                _ajax(name, url, _ajax_data);
            }
        });

        /**
         * 滚动悬浮侧栏
         * @type {{setting: {startline: string}, init: init}}
         */
        var scrollFixed = {
            setting: {
                startline: 59, //滚动起始位置
            },
            init: function () {
                var sidebar = $('ul.list-group'),
                    nav = $('ul.nav'),
                    select_currency = $('div.select_currency'),
                    // panel_head = $('.panel .table thead tr'),
                    _nav_height = nav.height(),
                    _nav_width = nav.width(),
                    _sidebar_height = sidebar.height(),
                    _sidebar_width = sidebar.width(),
                    // _panel_head_height = panel_head.height(),
                    // _panel_head_width = panel_head.width(),
                    _select_currency_width = '100%',
                    select_currency_height = 0;
                $(window).scroll(function () {
                    var scrollTop = $(window).scrollTop();

                    if (scrollTop >= scrollFixed.setting.startline) {
                        //导航悬浮
                        nav.css({"position": 'fixed', "top": 20, 'width': _nav_width, 'height': _nav_height});

                        //侧边栏悬浮
                        sidebar.css({
                            "position": 'fixed',
                            "top": 20 + scrollFixed.setting.startline,
                            'width': _sidebar_width,
                            'height': _sidebar_height
                        });

                        if(typeof select_currency !== 'undefined'){
                            select_currency_height = 55;
                        }

                        //表格抬头栏悬浮
                        // panel_head.css({
                        //     "position": 'fixed',
                        //     "top": 20 + select_currency_height + scrollFixed.setting.startline,
                        //     'width': _panel_head_width,
                        //     'height': _panel_head_height
                        // });

                        //选择积分类型悬浮
                        if(typeof select_currency !== 'undefined'){
                            select_currency.css({
                                "position": 'fixed',
                                "top": 20 + scrollFixed.setting.startline,
                                'width': _select_currency_width,
                                'background-color': '#fff'
                            });
                        }
                    } else {
                        nav.css({"position": 'inherit', "top": 'auto'});
                        sidebar.css({"position": 'inherit', "top": 'auto'});
                        // panel_head.css({"position": 'inherit', "top": 'auto'});
                        if(typeof select_currency !== 'undefined'){
                            select_currency.css({"position": 'inherit', "top": 'auto'});
                        }
                    }
                });
            }
        };

        scrollFixed.init();
    }

    /**
     * AJAX请求
     * @param name
     * @param url
     * @param data
     * @returns {boolean}
     * @private
     */
    function _ajax(name, url, data) {
        var loading = parent.layer.msg('服务器君正在用力加载中，请稍等···', {
            icon: 16
            , shade: 0.3
            , time: 0
            , scrollbar: false
        });


        $.ajax({
            type: 'post',
            data: data,
            url: url,
            dataType: 'json',
            success: function (callback) {
                if (callback.Code === 1) {
                    //本次弹窗一个TAB只发起一次网络请求，积分类型选择除外
                    _status[name] = false;
                    //执行对应TAB的操作
                    _detail.init(name, callback.Msg, loading);
                } else {
                    parent.layer.alert(callback.Msg, {icon: 5});
                    parent.layer.close(loading);
                }
            },
            error: function (e) {
                parent.layer.alert("网络请求失败~！", {icon: 5});
                parent.layer.close(loading);
            }
        });
        return false;
    }

    function _detail() {
        var _ = this;

        //方法初始化
        _.init = function (name, data, loading) {
            _[name](name, data, loading);
        };

        //钱积分非零值加粗
        _.money_bold = function (money) {
            var color_style = _.money_negative(money);
            if (parseFloat(money) > 0) {
                money = "<b>" + _accMul(money) + "</b>";
            }

            if (parseFloat(money) < 0) {
                money = "<b style='" + color_style + "'>" + _accMul(money) + "</b>";
            }
            return money;
        };

        //钱积分负数标红
        _.money_negative = function (money) {
            var color_style = "";
            if (parseFloat(money) < 0) {
                color_style = "color: #f60;";
            }
            return color_style;
        };
        _accMul=function(arg1){
            var arg2=1;
            var m=0,s1=arg1.toString(),s2=arg2.toString();
            try{m+=s1.split(".")[1].length}catch(e){}
            try{m+=s2.split(".")[1].length}catch(e){}
            return Number(s1.replace(".",""))*Number(s2.replace(".",""))/Math.pow(10,m)
        };
        //会员信息
        _.hyxx = function (name, data, loading) {
            var _user_info = "<td>" + data.user_info.member_id + "</td><td>" + data.user_info.email + "</td><td>" + data.user_info.name + "</td><td>" + data.user_info.phone + "</td><td style='color: #00a800;'>" + data.user_info.rmb + "</td><td style='color: #f00;'>" + data.user_info.forzen_rmb + "</td><td>" + data.user_info.reg_time + "</td>",
                _user_currency = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group');

            $('.profile_content .' + name + ' .table.user_info tbody tr').html(_user_info);

            $.each(data.user_currency, function (key, val) {
                // _user_currency += "<tr><td>" + val.currency_name + "</td><td>"+ _.money_bold(val.sum)+"</td><td>" + _.money_bold(val.num) + "</td><td>" + _.money_bold(val.forzen_num) + "</td><td>"+ _.money_bold(val.num_award) + "</td><td>"+_.money_bold(val.lock_num)+"</td><td>"+ _.money_bold(val.internal_buy) + "</td><td>" + _.money_bold(val.remaining_principal)+ "</td><td>"  + _.money_bold(val.chongbi_num) + "</td><td>" + _.money_bold(val.pay_num) + "</td><td>" + _.money_bold(val.buy_num) + "</td><td>" + _.money_bold(val.sell_num) + "</td><td>" + _.money_bold(val.tibi_num) + "</td><td>" + _.money_bold(val.hzchongbi_num) + "</td><td>" + _.money_bold(val.hztibi_num) + "</td><td>"+_.money_bold(val.balance)+"</td></tr>";
                _user_currency += "<tr><td>" + val.currency_name + "</td><td>" + _.money_bold(val.num) + "</td><td>" + _.money_bold(val.forzen_num) + "</td><td>"+ _.money_bold(val.keep_num) + "</td></tr>";
            });
            $('.profile_content .' + name + ' .table.user_currency tbody').html(_user_currency);

            _right_list_group.find('li:eq(0) .badge').text(data.user_info.totalcount).attr('style', _.money_negative(data.user_info.totalcount));
            _right_list_group.find('li:eq(1) .badge').text(data.user_info.total_adminmoney).attr('style', _.money_negative(data.user_info.total_adminmoney));
            _right_list_group.find('li:eq(2) .badge').text(data.user_info.totalmoney).attr('style', _.money_negative(data.user_info.totalmoney));
            _right_list_group.find('li:eq(3) .badge').text(data.user_info.fifmoney).attr('style', _.money_negative(data.user_info.fifmoney));
            if ($.trim(data.user_info.remarks).length > 0) {
                _right_list_group.find('li:eq(4)').text(data.user_info.remarks).attr('style', "color: #f60;");
            }

            parent.layer.close(loading);
        };

        //充值记录
        _.czjl = function (name, data, loading) {
            var _pay_info = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group');

            $.each(data.pay_list, function (key, val) {
                _pay_info += "<tr><td>" + val.pay_id + "</td><td>" + val.email + "</td><td>" + val.member_name + "</td><td>" + val.member_id + "</td><td>" + val.account + "</td><td>" + _.money_bold(val.money) + "</td><td>" + _.money_bold(val.count) + "</td><td>" + val.status + "</td><td>" + val.currency_type + "</td><td>" + val.add_time + "</td><td>" + val.due_bank + "</td><td>" + val.batch + "</td><td>" + _.money_bold(val.capital) + "</td><td>" + val.username + "</td><td>"+val.message+"</td></tr>";
                   // "<td>" + val.commit_time + "</td><td>" + val.audit_name + "</td><td>" + val.audit_time + "</td></tr>";
            });
            $('.profile_content .' + name + ' .table tbody').html(_pay_info);

            _right_list_group.find('li:eq(0) .badge').text(data.pay_sum.totalczmoney).attr('style', _.money_negative(data.pay_sum.totalczmoney));
            _right_list_group.find('li:eq(1) .badge').text(data.pay_sum.totalcurrency).attr('style', _.money_negative(data.pay_sum.totalcurrency));
            _right_list_group.find('li:eq(2) .badge').text(data.pay_sum.totalczcount).attr('style', _.money_negative(data.pay_sum.totalczcount));

            parent.layer.close(loading);
        };

        //提现审核
        _.txsh = function (name, data, loading) {
            var _withdraw_list = "",
                _finance_list = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group');

            $.each(data.withdraw_list, function (key, val) {
                _withdraw_list += "<tr><td>" + val.withdraw_id + "</td><td>" + val.cardname + "</td><td>" + val.uid + "</td><td>" + val.bankname + "</td><td>" + val.cardnum + "</td><td>" + val.aarea_name + " " + val.barea_name + "</td><td>" + _.money_bold(val.all_money) + "</td><td>" + _.money_bold(val.withdraw_fee) + "</td><td>" + _.money_bold(val.money) + "</td><td>" + val.order_num + "</td><td>" + val.add_time + "</td><td>" + val.status + "</td></tr>";
            });
            $('.profile_content .' + name + ' .table.withdraw_list tbody').html(_withdraw_list);

            _right_list_group.find('li:eq(0) .badge').text(data.total_money);

            $.each(data.finance_list, function (key, val) {
                _finance_list += "<tr><td>" + val.finance_id + "</td><td>" + val.username + "</td><td>" + val.typename + "</td><td>" + val.content + "</td><td>" + _.money_bold(val.money) + "</td><td>" + val.currency_name + "</td><td>" + val.moneytype + "</td><td>" + val.add_time + "</td><td>" + val.ip + "</td></tr>";
            });
            $('.profile_content .' + name + ' .table.finance_list tbody').html(_finance_list);

            parent.layer.close(loading);
        };

        //认购记录
        _.rgjl = function (name, data, loading) {
            var _currency_list = "",
                _issue_log_list = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group'),
                _selected = "",
                _select_currency = $('.profile_content .' + name + ' .select_currency select[name="currency_list"]');

            $.each(data.currency_list, function (key, val) {
                if (parseInt(data.currency_id) === parseInt(val.currency_id)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                _currency_list += "<option value='" + val.currency_id + "' " + _selected + ">" + val.currency_name + "</option>";
            });
            _select_currency.html(_currency_list);

            //选择积分动作
            _.select_currency(_select_currency, name);

            _right_list_group.find('li:eq(0) .badge').text(data.issue_log_count.buynum).attr('style', _.money_negative(data.issue_log_count.buynum));
            _right_list_group.find('li:eq(1) .badge').text(data.issue_log_count.freezenum).attr('style', _.money_negative(data.issue_log_count.freezenum));
            _right_list_group.find('li:eq(2) .badge').text(data.issue_log_count.totalaggregate).attr('style', _.money_negative(data.issue_log_count.totalaggregate));

            $.each(data.issue_log_list, function (key, val) {
                _issue_log_list += "<tr><td>" + val.id + "</td><td>" + val.iid + "</td><td>" + val.title + "</td><td>" + val.name + "</td><td>" + val.member_id + "</td><td>" + val.num + "</td><td>" + _.money_bold(val.deal) + "</td><td>" + _.money_bold(val.price) + "</td><td>" + _.money_bold(val.count) + "</td><td>" + val.add_time + "</td><td>" + val.currency_name + "</td><td>" + val.remarks + "</td></tr>";
            });
            $('.profile_content .' + name + ' .table tbody').html(_issue_log_list);

            parent.layer.close(loading);
        };

        //选择积分 [复用函数]
        _.select_currency = function (el, name) {
            el.off('change').on('change', function () {
                _ajax_data.currency_id = parseInt($(this).val());
                _ajax(name, _ajax_url + _config_url[name], _ajax_data);
            });
        };
        //current_page:当前页; rows每页条目数; count:总数目; name:调用的名称
        // _.show_page_turning=function (current_page,rows,count,name,parameter) {
        //     var str="<tr><td>首页"+_page_turning(parameter,name)+"</td>";
        //     // if(current_page<=0||current_page==1){
        //     //     str+="<td></td>"
        //     // }
        //     str="</tr>";
        //     return str;
        //
        // }
        //     //翻页; _ajax_data:翻页需要的参数
        // ._page_turning=function (_ajax_data,name) {
        //     _ajax(name, _ajax_url + _config_url[name], _ajax_data);
        // };
        //xrp@标
        _.select_xrp= function (el, name) {
            el.off('change').on('change', function () {
                var state=$('select[name="state"]').val();
                var type=$('select[name="type"]').val();
                var status=$('select[name="status"]').val();
                var start_time=$('[name="start_time"]').val();
                _ajax_data.state = parseInt(state);
                _ajax_data.type = parseInt(type);
                _ajax_data.status = parseInt(status);
                _ajax(name, _ajax_url + _config_url[name], _ajax_data);
            });
        };
        // laydate({
        //     elem: '#start_laydate'
        //     ,choose: function(dates){ //选择好日期的回调
        //
        //     }
        // });


        //交易记录
        _.jyjl = function (name, data, loading) {
            var _currency_list = "",
                _trade_list = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group'),
                _selected = "",
                _select_currency = $('.profile_content .' + name + ' .select_currency select[name="currency_list"]');

            $.each(data.currency_list, function (key, val) {
                if (parseInt(data.currency_id) === parseInt(val.currency_id)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                _currency_list += "<option value='" + val.currency_id + "' " + _selected + ">" + val.currency_name + "</option>";
            });
            _select_currency.html(_currency_list);

            //选择积分动作
            _.select_currency(_select_currency, name);

            _right_list_group.find('li:eq(0) .badge').text(data.trade_count.buynum).attr('style', _.money_negative(data.trade_count.buynum));
            _right_list_group.find('li:eq(1) .badge').text(data.trade_count.buymoney).attr('style', _.money_negative(data.trade_count.buymoney));
            _right_list_group.find('li:eq(2) .badge').text(data.trade_count.sellnum).attr('style', _.money_negative(data.trade_count.sellnum));
            _right_list_group.find('li:eq(3) .badge').text(data.trade_count.sellmoney).attr('style', _.money_negative(data.trade_count.sellmoney));

            $.each(data.trade_list, function (key, val) {
                _trade_list += "<tr><td>" + val.trade_id + "</td><td>" + val.trade_no + "</td><td>"+val.other_member_id+"</td><td>" + val.email + "</td><td>"+val.phone+"</td><td>" + val.b_name+'/'+val.b_trade_name + "</td><td>" + _.money_bold(val.num) + "</td><td>" + _.money_bold(val.price) + "</td><td>" + _.money_bold(val.money) + "</td><td>" + _.money_bold(val.fee) + "</td><td>" + val.type_name + "</td><td>" + val.add_time + "</td></tr>";
            });
            $('.profile_content .' + name + ' .table tbody').html(_trade_list);

            parent.layer.close(loading);
        };

        //提积分记录
        _.tbjl = function (name, data, loading) {
            var _currency_list = "",
                _tibi_list = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group'),
                _selected = "",
                _select_currency = $('.profile_content .' + name + ' .select_currency select[name="currency_list"]');

            $.each(data.currency_list, function (key, val) {
                if (parseInt(data.currency_id) === parseInt(val.currency_id)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                _currency_list += "<option value='" + val.currency_id + "' " + _selected + ">" + val.currency_name + "</option>";
            });
            _select_currency.html(_currency_list);

            //选择积分动作
            _.select_currency(_select_currency, name);

            _right_list_group.find('li:eq(0) .badge').text(data.tibi_count.totalcurrency).attr('style', _.money_negative(data.tibi_count.totalcurrency));

            $.each(data.tibi_list, function (key, val) {
                _tibi_list += "<tr><td>" + val.id + "</td><td>" + val.email + "</td><td>" + val.currency_name + "</td><td>" + val.to_url + "</td><td>"+val.ti_id+"</td><td>" + _.money_bold(val.num) + "</td><td>" + _.money_bold(val.actual) + "</td><td>" + val.add_time + "</td><td>" + val.status + "</td><td>" + val.message1 + "</td><td>" + val.message2 + "</td></tr>";
            });
            $('.profile_content .' + name + ' .table tbody').html(_tibi_list);

            parent.layer.close(loading);
        };

        //OTC交易
        _.otc = function (name, data, loading) {
            var _withdraw_list = "",
                _finance_list = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group'),
                _currency_list = "",
                _selected = "",
                _select_currency = $('.profile_content .' + name + ' .select_currency select[name="currency_list"]');

            $.each(data.currency_list, function (key, val) {
                if (parseInt(data.currency_id) === parseInt(val.currency_id)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                _currency_list += "<option value='" + val.currency_id + "' " + _selected + ">" + val.currency_name + "</option>";
            });
            _select_currency.html(_currency_list);

            //选择积分动作
            _.select_currency(_select_currency, name);


            $.each(data.order_list, function (key, val) {
                _withdraw_list += "<tr><td>" + val.orders_id + "</td><td>" + val.email + "</td><td>" + val.member_id + "</td><td>" + val.name + "</td><td>" + val.phone + "</td><td>" + val.currency_name + " </td><td>"+ val.num +"</td><td>" + val.avail_num + "</td><td>" + val.price + "</td><td>" + val.min_money +'~'+val.min_money + "</td><td>"+val.fee +"</td> <td>"+ val.type_name+"</td> <td>"+val.add_time+"</td><td>"+val.status+"</td></tr>";
            });
            $('.profile_content .' + name + ' .table.order_list tbody').html(_withdraw_list);

            _right_list_group.find('li:eq(0) .badge').text(data.total_money);

            $.each(data.trade_list, function (key, val) {
                _finance_list += "<tr><td>" + val.trade_no + "</td><td>" + val.only_number + "</td><td>" + val.member_id + "</td><td>" + val.name + "</td><td>" + val.phone + "</td><td>" + val.currency_name + " </td><td>"+ val.num +"</td><td>" + _.money_bold(val.price) + "</td><td>" + _.money_bold(val.money) + "</td><td>" +val.fee  + "</td><td>"+_.money_bold(val.change_num)+"</td><td>"+val.type_name +"</td><td>"+val.payment_type+"</td> <td>"+val.sell_payment+"</td> <td>"+val.buy_payment+"</td>  <td>"+ val.add_time+"</td><td>"+val.status+"</td></tr>";
            });
            $('.profile_content .' + name + ' .table.trade_list tbody').html(_finance_list);
            parent.layer.close(loading);
        };
        //赠送记录
        _.zsjl = function (name, data, loading) {
            var _currency_list = "",
                _trade_list = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group'),
                _selected = "",
                _select_currency = $('.profile_content .' + name + ' .select_currency select[name="currency_list"]');

            $.each(data.currency_list, function (key, val) {
                if (parseInt(data.currency_id) === parseInt(val.currency_id)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                _currency_list += "<option value='" + val.currency_id + "' " + _selected + ">" + val.currency_name + "</option>";
            });
            _select_currency.html(_currency_list);

            //选择积分动作
            _.select_currency(_select_currency, name);
            _right_list_group.find('li:eq(0) .badge').text(data.count.num).attr('style', _.money_negative(data.count.num));
            _right_list_group.find('li:eq(1) .badge').text(data.count.forzen_num).attr('style', _.money_negative(data.count.forzen_num));
            _right_list_group.find('li:eq(2) .badge').text(data.count.lock_num).attr('style', _.money_negative(data.lock_num));
            _right_list_group.find('li:eq(3) .badge').text(data.count.exchange_num).attr('style', _.money_negative(data.exchange_num));
            _right_list_group.find('li:eq(4) .badge').text(data.count.num_award).attr('style', _.money_negative(data.num_award));
            _right_list_group.find('li:eq(5) .badge').text(data.count.sum_award).attr('style', _.money_negative(data.sum_award));

            $.each(data.trade_list, function (key, val) {
                _trade_list += "<tr><td>" + val.id + "</td><td>" + val.currency_id + "</td><td>" + val.num_award + "</td><td>" + val.tier + "</td><td>" + val.add_time + "</td></tr>";
            });
            $('.profile_content .' + name + ' .table tbody').html(_trade_list);

            parent.layer.close(loading);
        };
        //持币生息
        _.cbsx = function (name, data, loading) {
            var _currency_list = "<option value='0' " + _selected + ">全部</option>",
                _trade_list = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group'),
                _selected = "",
                _select_currency = $('.profile_content .' + name + ' .select_currency select[name="currency_list"]');

            $.each(data.currency_list, function (key, val) {
                if (parseInt(data.currency_id) === parseInt(val.currency_id)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                _currency_list += "<option value='" + val.currency_id + "' " + _selected + ">" + val.currency_name + "</option>";
            });
            _select_currency.html(_currency_list);

            //选择积分动作
            _.select_currency(_select_currency, name);
            _right_list_group.find('li:eq(0) .badge').text(data.num).attr('style', _.money_negative(data.num));
            $.each(data.trade_list, function (key, val) {
                _trade_list += "<tr><td>" + val.id + "</td><td>" + val.member_id + "</td><td>" + val.name + "</td><td>" + val.phone + "</td><td>" + val.currency_name + "</td><td>"+ val.months+"</td><td>"+ val.num+"</td><td>"+val.rate+"</td><td>"+val.day_num+"</td><td>"+val.add_time+"</td><td>"+val.end_time+"</td><td>"+val.status+"</td></td></tr>";
            });
            $('.profile_content .' + name + ' .table tbody').html(_trade_list);

            parent.layer.close(loading);
        };

        //充币记录
        _.cbjl = function (name, data, loading) {
            var _currency_list = "",
                _cbjl_list = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group'),
                _selected = "",
                _select_currency = $('.profile_content .' + name + ' .select_currency select[name="currency_list"]');

            $.each(data.currency_list, function (key, val) {
                if (parseInt(data.currency_id) === parseInt(val.currency_id)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                _currency_list += "<option value='" + val.currency_id + "' " + _selected + ">" + val.currency_name + "</option>";
            });
            _select_currency.html(_currency_list);

            //选择积分动作
            _.select_currency(_select_currency, name);

            _right_list_group.find('li:eq(0) .badge').text(data.tibi_count.totalnum).attr('style', _.money_negative(data.tibi_count.totalnum));
            _right_list_group.find('li:eq(1) .badge').text(data.tibi_count.totalactual).attr('style', _.money_negative(data.tibi_count.totalactual));

            $.each(data.tibi_list, function (key, val) {
                _cbjl_list += "<tr><td>" + val.id + "</td><td>" + val.email + "</td><td>" + val.currency_name + "</td><td>" + val.from_url + "</td><td>"+val.ti_id+"</td><td>" + _.money_bold(val.num) + "</td><td>" + _.money_bold(val.actual) + "</td><td>" + val.add_time + "</td><td>" + val.status + "</td><td>" + val.message1 + "</td><td>" + val.message2 + "</td></tr>";
            });
            $('.profile_content .' + name + ' .table tbody').html(_cbjl_list);

            parent.layer.close(loading);
        };
        //内部互转记录
        _.nbhz = function (name, data, loading) {
            var _currency_list = "",
                _nbhz_list = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group'),
                _selected = "",
                _select_currency = $('.profile_content .' + name + ' .select_currency select[name="currency_list"]');

            $.each(data.currency_list, function (key, val) {
                if (parseInt(data.currency_id) === parseInt(val.currency_id)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                _currency_list += "<option value='" + val.currency_id + "' " + _selected + ">" + val.currency_name + "</option>";
            });
            _select_currency.html(_currency_list);

            //选择积分动作
            _.select_currency(_select_currency, name);

            // _right_list_group.find('li:eq(0) .badge').text(data.tibi_count.totalnum).attr('style', _.money_negative(data.tibi_count.totalnum));
            // _right_list_group.find('li:eq(1) .badge').text(data.tibi_count.totalactual).attr('style', _.money_negative(data.tibi_count.totalactual));

            $.each(data.list, function (key, val) {
                _nbhz_list += "<tr><td>" + val.id + "</td><td>" + val.email + "</td><td>" + val.from_member_id + "</td><td>" + val.name + "</td><td>" + val.phone+ "</td><td>" + val.currency_name + "</td><td>" + val.from_url + "</td><td>" + val.ti_id + "</td><td>" + val.temail + "</td><td>" + val.to_member_id + "</td>"
                +"<td>" + val.tname + "</td><td>" + val.tphone + "</td><td>" + val.to_url + "</td><td>" + _.money_bold(val.num)+ "</td><td>" + _.money_bold(val.actual)+ "</td><td>" + val.add_time + "</td><td>转账成功</td></tr>";
            });
            $('.profile_content .' + name + ' .table tbody').html(_nbhz_list);

            parent.layer.close(loading);
        };

        //帐本记录
        _.yhzb = function (name, data, loading) {
            var _currency_list = "",_type_list = "",
                _yhzb_list = "",
                _right_list_group = $('.profile_content .' + name + ' .list-group'),
                _selected = "",
                _select_currency = $('.profile_content .' + name + ' .select_currency select[name="currency_list"]'),
                _select_type = $('.profile_content .' + name + ' .select_currency select[name="type_list"]');

            $.each(data.currency_list, function (key, val) {
                if (parseInt(data.currency_id) === parseInt(val.currency_id)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                _currency_list += "<option value='" + val.currency_id + "' " + _selected + ">" + val.currency_name + "</option>";
            });
            _select_currency.html(_currency_list);

            console.log(data.type_list);
            _type_list = '<option>请选择</option>';
            $.each(data.type_list, function (key, val) {
                console.log(key);
                if (data.type === key) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                _type_list += "<option value='" + key + "' " + _selected + ">" + val.name + "</option>";
            });
            _select_type.html(_type_list);


            //选择积分动作
            _.select_currency(_select_currency, name);

            $('select[name="type_list"]').off('change').on('change', function () {
                _ajax_data.type = $(this).val();
                _ajax(name, _ajax_url + _config_url[name], _ajax_data);
            });

            // _right_list_group.find('li:eq(0) .badge').text(data.tibi_count.totalnum).attr('style', _.money_negative(data.tibi_count.totalnum));
            // _right_list_group.find('li:eq(1) .badge').text(data.tibi_count.totalactual).attr('style', _.money_negative(data.tibi_count.totalactual));

            $.each(data.list, function (key, val) {
                _yhzb_list += "<tr><td>" + val.id + "</td><td>" + val.currency_name + "</td><td>"+val.from_member_id+"</td><td>"+val.from_phone+"</td><td>"+val.from_email+"</td><td>" + val.type + "</td><td>"+val.currency_pair+"</td><td>"+val.toMemberId+"</td><td>"+val.to_phone+"</td><td>"+val.to_email+"</td><td>" + val.change + "</td><td>" + _.money_bold(val.current)+
                    "</td><td>" + _.money_bold(val.number) + "</td><td>" + _.money_bold(val.after) + "</td><td>" + val.add_time + "</td><td>"+val.ad_remark+"</td></tr>";
            });
            $.each(data.list2, function (key, val) {
                _yhzb_list += "<tr><td>" + val.id + "</td><td>" + val.currency_name + "</td><td>"+val.from_member_id+"</td><td>"+val.from_phone+"</td><td>"+val.from_email+"</td><td>" + val.type + "</td><td>"+val.currency_pair+"</td><td>"+val.toMemberId+"</td><td>"+val.to_phone+"</td><td>"+val.to_email+"</td><td>" + val.change + "</td><td>" + _.money_bold(val.current)+
                    "</td><td>" + _.money_bold(val.number) + "</td><td>" + _.money_bold(val.after) + "</td><td>" + val.add_time + "</td><td>"+val.ad_remark+"</td></tr>";
            });
            $('.profile_content .' + name + ' .table tbody').html(_yhzb_list);

            parent.layer.close(loading);
        };
        //帐本记录
        _.xrp = function (name, data, loading) {
            console.log(data);
            var list1 = "",
                _selected = "",
                _state = $('.profile_content .' + name + ' .select_currency select[name="state"]');
            $.each(data.state_arr, function (key, val) {
                if (parseInt(data.where.state) === parseInt(key)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                list1 += "<option value='" + key + "' " + _selected + ">" + val + "</option>";
            });
            _state.html(list1);
            //选择积分动作
            _.select_xrp(_state, name);
            var list2 = "",
                _type = $('.profile_content .' + name + ' .select_currency select[name="type"]');
            list2 +='<option value="0">请选择</option>';
            $.each(data.type_arr, function (key, val) {
                if (parseInt(data.where.type) === parseInt(key)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                list2 += "<option value='" + key + "' " + _selected + ">" + val + "</option>";
            });
            _type.html(list2);
            _.select_xrp(_type, name);
            var list3 = "",
                _status = $('.profile_content .' + name + ' .select_currency select[name="status"]');
            list3 +='<option value="0">请选择</option>';
            $.each(data.status_arr, function (key, val) {
                if (parseInt(data.where.status) === parseInt(key)) {
                    _selected = 'selected';
                } else {
                    _selected = "";
                }
                list3 += "<option value='" + key + "' " + _selected + ">" + val + "</option>";
            });
            _status.html(list3);
            _.select_xrp(_status, name);
            $('#start_laydate').val(data.where.start_time);
            $('#end_laydate').val(data.where.end_time);
            laydate({
                elem: '#start_laydate'
                ,choose: function(dates){ //选择好日期的回调
                    var state=$('select[name="state"]').val();
                    var type=$('select[name="type"]').val();
                    var status=$('select[name="status"]').val();
                    var end_time=$('[name="end_time"]').val();
                    _ajax_data.state = parseInt(state);
                    _ajax_data.type = parseInt(type);
                    _ajax_data.status = parseInt(status);
                    _ajax_data.start_time = dates;
                    if(end_time ==''){
                        _ajax_data.end_time = dates;
                    }else{
                        _ajax_data.end_time = end_time;
                    }

                    _ajax(name, _ajax_url + _config_url[name], _ajax_data);
                }
            });
            laydate({
                elem: '#end_laydate'
                ,choose: function(dates){ //选择好日期的回调
                    var state=$('select[name="state"]').val();
                    var type=$('select[name="type"]').val();
                    var status=$('select[name="status"]').val();
                    var start_time=$('[name="start_time"]').val();
                    _ajax_data.state = parseInt(state);
                    _ajax_data.type = parseInt(type);
                    _ajax_data.status = parseInt(status);
                    _ajax_data.end_time = dates;
                    if(start_time ==''){
                        _ajax_data.start_time = dates;
                    }else{
                        _ajax_data.start_time = start_time;
                    }

                    _ajax(name, _ajax_url + _config_url[name], _ajax_data);
                }
            });


            var _xrp_list="";
            var _right_list_group = $('.profile_content .' + name + ' .list-group');
            _right_list_group.find('li:eq(0) .badge').text(data.arr_sum[0]).attr('style', _.money_negative(data.arr_sum[0]));
            _right_list_group.find('li:eq(1) .badge').text(data.arr_sum[1]).attr('style', _.money_negative(data.arr_sum[1]));
            _right_list_group.find('li:eq(2) .badge').text(data.arr_sum[2]).attr('style', _.money_negative(data.arr_sum[2]));
            _right_list_group.find('li:eq(3) .badge').text(data.arr_sum[3]).attr('style', _.money_negative(data.arr_sum[3]));
            _right_list_group.find('li:eq(4) .badge').text(data.arr_sum[4]).attr('style', _.money_negative(data.arr_sum[4]));
            _right_list_group.find('li:eq(5) .badge').text(data.arr_sum[5]).attr('style', _.money_negative(data.arr_sum[5]));
            _right_list_group.find('li:eq(6) .badge').text(data.arr_sum[6]).attr('style', _.money_negative(data.arr_sum[6]));
            _right_list_group.find('li:eq(7) .badge').text(data.arr_sum[7]).attr('style', _.money_negative(data.arr_sum[7]));
            _right_list_group.find('li:eq(8) .badge').text(data.arr_sum[8]).attr('style', _.money_negative(data.arr_sum[8]));
            _right_list_group.find('li:eq(9) .badge').text(data.arr_sum[9]).attr('style', _.money_negative(data.arr_sum[9]));
            _right_list_group.find('li:eq(10) .badge').text(data.arr_sum[10]).attr('style', _.money_negative(data.arr_sum[10]));
            _right_list_group.find('li:eq(11) .badge').text(data.arr_sum[11]).attr('style', _.money_negative(data.arr_sum[11]));
            _right_list_group.find('li:eq(12) .badge').text(data.arr_sum[12]).attr('style', _.money_negative(data.arr_sum[12]));
            _right_list_group.find('li:eq(13) .badge').text(data.arr_sum[13]).attr('style', _.money_negative(data.arr_sum[13]));
            $.each(data.list, function (key, val) {

                _xrp_list += "<tr><td>" + val.l_id + "</td><td>" + val.l_type + "</td><td>"+val.l_title+"</td><td>"+val.l_member_id+"</td><td>"+val.phone+"</td><td>" + val.l_state + "</td><td>"+val.l_current_num+"</td><td>"+val.l_fee+"</td><td>"+  _.money_bold(val.l_value)+val.l_unit+"</td><td>"+val.l_change_num+"</td><td>"+val.to_member_id+"</td><td>"+val.to_phone+"</td><td>" + val.l_time + "</td><td>";
                if(val.l_url) _xrp_list += "<a target='_blank' href='"+ val.l_url +"'>动态分红详情</a>";
                _xrp_list += "</td></tr>";
            });
            $('.profile_content .' + name + ' .table tbody').html(_xrp_list);

            parent.layer.close(loading);
        };
        _.c2c = function (name, data, loading) {
            $('.profile_content .' + name + ' .table tbody').html(data);
            parent.layer.close(loading);
        }

    }
});
