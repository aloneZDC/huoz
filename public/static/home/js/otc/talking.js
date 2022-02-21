$(document).ready(function () {
    im();
});

function im() {
    window.JIM = new JMessage({
        debug: false
    });

    var name_prefix = "HBJSCross-";
    var jim_login = false;

    init();

    JIM.onDisconnect(function () {
        console.log("【disconnect reconnect】");
        JIM.init({
            "appkey": __deel.across_appkey,
            "random_str": __deel.random_str,
            "signature": __deel.signature,
            "timestamp": __deel.timestamp,
            "flag": 1
        }).onAck(function (data) {
            console.log('ack【】:' + JSON.stringify(data));
        }).onSuccess(function (data) {
            console.log('success:' + JSON.stringify(data));
            //给当前用户登录
            login();
        }).onFail(function (data) {
            console.log('error:' + JSON.stringify(data))
        });

    }); //异常断线监听

    function getFile() {
        var fd = new FormData();
        var file = document.getElementById('file_box');
        if (!file.files[0]) {
            throw new Error(tip1);
        }
        fd.append(file.files[0].name, file.files[0]);
        return fd;
    }

    function init() {
        //加载消息列表
        get_message_list();

        JIM.init({
            "appkey": __deel.across_appkey,
            "random_str": __deel.random_str,
            "signature": __deel.signature,
            "timestamp": __deel.timestamp,
            "flag": 1
        }).onAck(function (data) {
            console.log('ack【】:' + JSON.stringify(data));
        }).onSuccess(function (data) {
            console.log('success:' + JSON.stringify(data));

            //给当前用户登录
            login();
        }).onFail(function (data) {
            console.log('error:' + JSON.stringify(data))
        });

        load_height();
    }

    /**
     * 加载消息列表
     */
    function get_message_list() {
        var data = {
            order_id: __deel.order_id,
            order_user_id: __deel.across_user,
            access_key: __deel.access_key,
        };

        $.ajax({
            url: __deel.get_messages,
            type: 'post',
            dataType: 'json',
            data: data,
            success: function (callback) {
                if (callback.Code === 1) {
                    console.log(callback.Msg);
                    if (callback.Msg.length > 0) {
                        var _data = [];
                        $.each(callback.Msg, function (index, value) {
                            var data = {};
                            data.content = value.content;
                            data.ctime_ms = value.msg_time;
                            data.position = value._position;
                            data.head = value.user_head;
                            _data.push(data);
                        });
                        show_msg(_data);
                    }
                } else {
                    //layer.alert(callback.Msg, {icon: 5});
                    return false;
                }
            },
            error: function (e) {
                //layer.alert("消息加载失败，网络异常", {icon: 5});
                return false;
            }
        });
    }

    function loading() {
        layer.load(1, {
            shade: [0.3, '#000'] //0.1透明度的白色背景
        });
    }

    $('#btn_send').on('click', function () {
        var _val = $('input[id="send_val"]').val();

        if (!$.trim(_val).length > 0) {
            layer.msg(tip2, {icon: 5});
            return false;
        }
        sendmsg(_val);
        if (jim_login) {
            sendSingleMsg(_val);

        }
        $('input[id="send_val"]').val('');
    });

    $('.btn-cont input[name="upload_img"]').on('change', function () {
        var file = this.files[0];
        ;
        var reader = new FileReader();
        reader.readAsDataURL(file);

        loading();
        reader.onload = function (e) {
            var dx = (e.total / 1024) / 1024;
            if (this.result) {
                $.ajax({
                    url: __deel.upload,
                    type: 'post',
                    dataType: 'json',
                    data: {img: this.result},
                    success: function (callback) {
                        if (callback.code == 10000) {
                            layer.closeAll();
                            sendmsg(callback.result.src);
                        } else {
                            layer.closeAll();
                            layer.msg(tip3);
                        }
                    },
                    error: function (e) {
                        layer.closeAll();
                        layer.msg(tip3, {icon: 5});
                        return false;
                    }
                });
            }
        };
    });


    function sendmsg(content) {
        var data = {
            send_id: __deel.order_id,
            send_type: "order",
            msg_type: "text",
            msg_body: content,
            target_id: __deel.target_id,
            target_name: __deel.target_name,
            from_id: __deel.across_user,
            from_name: __deel.across_name,
            head: __deel.across_head,
            access_key: __deel.access_key
        };

        $.ajax({
            type: 'post',
            dataType: 'json',
            data: data,
            url: __deel.send_messages,
            success: function (callback) {
                if (callback.Code === 1) {
                    $('input[class="message-input"]').val("");

                    data.content = content;
                    data.ctime_ms = callback.Msg.msg_time;
                    show_msg(data);
                } else {
                    layer.alert(callback.Msg, {icon: 5});
                    return false;
                }
            },
            error: function (e) {
                layer.msg(tip4, {icon: 5});
                return false;
            }
        });
    }

    function loginOut() {
        JIM.loginOut();
    }

    function register(user_id, _password) {
        var _data = [];

        JIM.register({
            'username': user_id ? user_id : name_prefix + __deel.target_id,
            'password': _password ? _password : __deel.password
        }).onAck(function (data) {
            console.log('ack:' + JSON.stringify(data));
            _data = data;
        }).onSuccess(function (data) {
            console.log('success:' + JSON.stringify(data));
            _data = data;
        }).onFail(function (data) {
            console.log('error:' + JSON.stringify(data));
            _data = data;
        });

        return _data;
    }

    function login(user_id, _password) {
        JIM.login({
            'username': user_id ? user_id : name_prefix + __deel.across_user,
            'password': _password ? _password : __deel.across_pass
        }).onSuccess(function (data) {
            jim_login = true;
            console.log('success:' + JSON.stringify(data));

            JIM.onMsgReceive(function (data) {
                var username = name_prefix + __deel.across_user;
                if (data.messages[0].content['from_type'] == 'admin') return;

                data.content = data.messages[0].content.msg_body.text;
                data.ctime_ms = data.messages[0].ctime_ms;
                data.head = __deel.target_head;
                data.position = "l";
                show_msg(data);

                data = JSON.stringify(data);
                console.log('msg_receive:' + data);
            });

            JIM.onEventNotification(function (data) {
                console.log('event_receive: ' + JSON.stringify(data));
            });

            JIM.onSyncConversation(function (data) { //离线消息同步监听
                console.log('event_receive: ' + data);
            });

            JIM.onUserInfUpdate(function (data) {
                console.log('onUserInfUpdate : ' + JSON.stringify(data));
            });

            JIM.onSyncEvent(function (data) {
                console.log('onSyncEvent : ' + JSON.stringify(data));
            });

        }).onFail(function (data) {
            console.log('error:' + JSON.stringify(data));

            if (data['code'] === 880103) {
                register();
            }
        }).onTimeout(function (data) {
            console.log('timeout:' + JSON.stringify(data));
        });
    }


    function getSelfInfo(user_id) {
        JIM.getUserInfo({
            'username': user_id ? user_id : name_prefix + __deel.target_id,
            'appkey': __deel.across_appkey
        }).onSuccess(function (data) {
            console.log('success:' + JSON.stringify(data));
        }).onFail(function (data) {
            console.log('error:' + JSON.stringify(data));
        });
    }

    function sendSingleMsg(content) {
        JIM.sendSingleMsg({
            'target_username': name_prefix + __deel.target_id,
            'target_nickname': __deel.target_name,
            'content': content,
            'send_id': __deel.send_id,
            'appkey': __deel.across_appkey,
            'no_offline': true,
            'no_notification': true,
            'custom_notification': {
                'enabled': false,
//                    'title': '',
//                    'alert': ''
            }
        }).onSuccess(function (data) {
            console.log('success:' + JSON.stringify(data));

            data.content = content;

            layer.closeAll();
            // show_msg(data);
        }).onFail(function (data) {
            layer.closeAll();
            console.log('error:' + JSON.stringify(data));
        });
    }

    function show_msg(data) {
        var _html = "",
            _chating = $('#dialog_box'),
            _chating_height = 0;

        if (data.length > 0) {
            _html = _set_html(data);
        } else {
            _html = set_html(data);
        }

        _chating.append(_html);
        _chating_height = _chating[0].scrollHeight + 80;

        _chating.animate({
            scrollTop: _chating_height
        });

        function _set_html(data) {
            var _html = "";
            $.each(data, function (index, value) {
                value.position = (typeof value.position == 'undefined' ? "r" : value.position);
                value.head = (typeof value.head == 'undefined' ? __deel.target_head : value.head);
                var unixTimestamp = new Date(parseInt(value.ctime_ms)),
                    _time = unixTimestamp.toLocaleString(),
                    _time_hm = unixTimestamp.getHM();
                if (value.content.indexOf('images') !== -1) {
                    value.content = "<img style='max-height:200px;max-width:100%;'  src='" + value.content + "' />";
                }

                if (__deel.layer === 'simple') {
                    console.log( value.position+"--------"+"simple")
                    if (value.position === 'l') {
                        _html += "<div class='order_content_right_content_time'>\n" +
                            "        <p class='time'> " + _time + "</p>\n" +
                            "     </div>\n" +
                            "      <div class='order_content_right_content_user'>\n" +
                            "          <div class='order_content_right_content_usertext'>\n" +
                            "               <span class='order_content_right_content_usertext_img'>" + value.head + "</span>\n" +
                            "               <div class='order_content_right_content_usertext_text'>\n" + value.content +
                            "               </div>\n" +
                            "           </div>\n" +
                            "       </div>"
                    } else {
                        _html += "<div class='order_content_right_content_time'>\n" +
                            "        <p class='time'> " + _time + "</p>\n" +
                            "     </div>\n" +
                            "      <div class='order_content_right_content_user'>\n" +
                            "          <div class='order_content_right_content_usertext float_right'>\n" +
                            "               <span class='order_content_right_content_usertext_img'>" + value.head + "</span>\n" +
                            "               <div class='order_content_right_content_usertext_text'>\n" + value.content +
                            "               </div>\n" +
                            "           </div>\n" +
                            "       </div>"
                    }

                }

                if (__deel.layer === 'complex') {
                    console.log( value.position+"--------"+"complex")
                    if (value.position === 'l') {
                        _html += "<div class='order_content_right_content_time'>\n" +
                            "        <p class='time'> " + _time + "</p>\n" +
                            "     </div>\n" +
                            "      <div class='order_content_right_content_user'>\n" +
                            "          <div class='order_content_right_content_usertext'>\n" +
                            "               <span class='order_content_right_content_usertext_img'>" + value.head + "</span>\n" +
                            "               <div class='order_content_right_content_usertext_text'>\n" + value.content +
                            "               </div>\n" +
                            "           </div>\n" +
                            "       </div>"
                    }else if(value.position === 'r'){
                            _html += "<div class='order_content_right_content_time'>\n" +
                            "        <p class='time'> " + _time + "</p>\n" +
                            "     </div>\n" +
                            "      <div class='order_content_right_content_user'>\n" +
                            "          <div class='order_content_right_content_usertext float_right'>\n" +
                            "               <span class='order_content_right_content_usertext_img'>" + value.head + "</span>\n" +
                            "               <div class='order_content_right_content_usertext_text'>\n" + value.content +
                            "               </div>\n" +
                            "           </div>\n" +
                            "       </div>"
                    } else {
                        _html += "<div class='order_content_right_content_time'>\n" +
                        "        <p class='time'> " + _time + "</p>\n" +
                        "     </div>\n" +
                        "      <div style='text-align: center;'>\n" +value.content +
                        "       </div>"


                    }

                }
            });

            return _html;
        }

        function set_html(data) {
            data.position = (typeof data.position == 'undefined' ? "r" : data.position);
            data.head = (typeof data.head == 'undefined' ? __deel.target_head : data.head);

            var unixTimestamp = new Date(parseInt(data.ctime_ms)),
                _time = unixTimestamp.toLocaleString(),
                _time_hm = unixTimestamp.getHM(),
                _html = "",
                _chating = $("div.chating"),
                _chating_height = 0;

            if (data.content.indexOf('images') !== -1) {
                data.content = "<img style='max-height:200px;max-width:100%;' src='" + data.content + "' />";
            }

            if (__deel.layer === 'simple') {
                if (data.position === 'l') {
                    _html += "<div class='order_content_right_content_time'>\n" +
                        "        <p class='time'> " + _time + "</p>\n" +
                        "     </div>\n" +
                        "      <div class='order_content_right_content_user'>\n" +
                        "          <div class='order_content_right_content_usertext'>\n" +
                        "               <span class='order_content_right_content_usertext_img'>" + data.head + "</span>\n" +
                        "               <div class='order_content_right_content_usertext_text'>\n" + data.content +
                        "               </div>\n" +
                        "           </div>\n" +
                        "       </div>"
                } else {
                    _html += "<div class='order_content_right_content_time'>\n" +
                        "        <p class='time'> " + _time + "</p>\n" +
                        "     </div>\n" +
                        "      <div class='order_content_right_content_user'>\n" +
                        "          <div class='order_content_right_content_usertext float_right'>\n" +
                        "               <span class='order_content_right_content_usertext_img'>" + data.head + "</span>\n" +
                        "               <div class='order_content_right_content_usertext_text'>\n" + data.content +
                        "               </div>\n" +
                        "           </div>\n" +
                        "       </div>"
                }

            }

            if (__deel.layer === 'complex') {
                if (data.position === 'l') {
                    _html += "<div class='order_content_right_content_time'>\n" +
                        "        <p class='time'> " + _time + "</p>\n" +
                        "     </div>\n" +
                        "      <div class='order_content_right_content_user'>\n" +
                        "          <div class='order_content_right_content_usertext'>\n" +
                        "               <span class='order_content_right_content_usertext_img'>" + data.head + "</span>\n" +
                        "               <div class='order_content_right_content_usertext_text'>\n" + data.content +
                        "               </div>\n" +
                        "           </div>\n" +
                        "       </div>"
                } else {
                    _html += "<div class='order_content_right_content_time'>\n" +
                        "        <p class='time'> " + _time + "</p>\n" +
                        "     </div>\n" +
                        "      <div class='order_content_right_content_user'>\n" +
                        "          <div class='order_content_right_content_usertext float_right'>\n" +
                        "               <span class='order_content_right_content_usertext_img'>" + data.head + "</span>\n" +
                        "               <div class='order_content_right_content_usertext_text'>\n" + data.content +
                        "               </div>\n" +
                        "           </div>\n" +
                        "       </div>"
                }

            }
            return _html;
        }
    }

    function load_height() {
        var _chating = $("div.chating"),
            _chating_height = 0;

        _chating.resize(function () {
            _chating_height = _chating[0].scrollHeight + 60;

            _chating.animate({
                scrollTop: _chating_height
            });
        });
    }

    Date.prototype.getHM = function () {
        var hours = this.getHours(),
            minute = this.getMinutes();

        hours = hours < 10 ? ('0' + hours) : hours;
        minute = minute < 10 ? ('0' + minute) : minute;

        return hours + ":" + minute;
    };

    Date.prototype.toLocaleString = function () {
        var hours = this.getHours(),
            minute = this.getMinutes(),
            second = this.getSeconds();

        hours = hours < 10 ? ('0' + hours) : hours;
        minute = minute < 10 ? ('0' + minute) : minute;
        second = second < 10 ? ('0' + second) : second;

        return this.getFullYear() + "-" + (this.getMonth() + 1) + "-" + this.getDate() + " " + hours + ":" + minute + ":" + second;
    };
}

(function ($, h, c) {
    var a = $([]), e = $.resize = $.extend($.resize, {}), i, k = "setTimeout", j = "resize", d = j
        + "-special-event", b = "delay", f = "throttleWindow";
    e[b] = 350;
    e[f] = true;
    $.event.special[j] = {
        setup: function () {
            if (!e[f] && this[k]) {
                return false
            }
            var l = $(this);
            a = a.add(l);
            $.data(this, d, {
                w: l.width(),
                h: l.height()
            });
            if (a.length === 1) {
                g()
            }
        },
        teardown: function () {
            if (!e[f] && this[k]) {
                return false
            }
            var l = $(this);
            a = a.not(l);
            l.removeData(d);
            if (!a.length) {
                clearTimeout(i)
            }
        },
        add: function (l) {
            if (!e[f] && this[k]) {
                return false
            }
            var n;

            function m(s, o, p) {
                var q = $(this), r = $.data(this, d);
                r.w = o !== c ? o : q.width();
                r.h = p !== c ? p : q.height();
                n.apply(this, arguments)
            }

            if ($.isFunction(l)) {
                n = l;
                return m
            } else {
                n = l.handler;
                l.handler = m
            }
        }
    };

    function g() {
        i = h[k](function () {
            a.each(function () {
                var n = $(this), m = n.width(), l = n.height(), o = $
                    .data(this, d);
                if (m !== o.w || l !== o.h) {
                    n.trigger(j, [o.w = m, o.h = l])
                }
            });
            g()
        }, e[b])
    }
})(jQuery, this);