$(function() {
	/*
	 * 工具方法 by wangqq
	 */
	Date.prototype.Format = function(fmt) { //author: meizz 
		var o = {
			"M+": this.getMonth() + 1, //月份 
			"d+": this.getDate(), //日 
			"h+": this.getHours(), //小时 
			"m+": this.getMinutes(), //分 
			"s+": this.getSeconds(), //秒 
			"q+": Math.floor((this.getMonth() + 3) / 3), //季度 
			"S": this.getMilliseconds() //毫秒 
		};
		if (/(y+)/.test(fmt)) fmt = fmt.replace(RegExp.$1, (this.getFullYear() + "").substr(4 - RegExp.$1.length));
		for (var k in o)
			if (new RegExp("(" + k + ")").test(fmt)) fmt = fmt.replace(RegExp.$1, (RegExp.$1.length == 1) ? (o[k]) : (("00" +
				o[k]).substr(("" + o[k]).length)));
		return fmt;
	}

	function getDate(tm) {
		var tt = new Date(parseInt(tm) * 1000).Format("hh:mm:ss");
		return tt;
	}

	// 浮点数计算
	function add(num1, num2) {
		var r1, r2, m, n;
		try {
			r1 = num1.toString().split(".")[1].length
		} catch (e) {
			r1 = 0
		}
		try {
			r2 = num2.toString().split(".")[1].length
		} catch (e) {
			r2 = 0
		}
		m = Math.pow(10, Math.max(r1, r2));
		n = (r1 >= r2) ? r1 : r2;
		return ((num1 * m + num2 * m) / m).toFixed(n);
	}

	function sub(num1, num2) {
		var r1, r2, m, n;
		try {
			r1 = num1.toString().split(".")[1].length
		} catch (e) {
			r1 = 0
		}
		try {
			r2 = num2.toString().split(".")[1].length
		} catch (e) {
			r2 = 0
		}
		n = (r1 >= r2) ? r1 : r2;
		m = Math.pow(10, Math.max(r1, r2));
		return ((num1 * m - num2 * m) / m).toFixed(n);
	}

	function mul(num1, num2) {
		var m = 0;
		try {
			m += num1.toString().split(".")[1].length
		} catch (e) {}
		try {
			m += num2.toString().split(".")[1].length
		} catch (e) {}
		return (Number(num1.toString().replace(".", "")) * Number(num2.toString().replace(".", ""))) / Math.pow(10, m)
	}

	function div(arg1, arg2) {
		var t1 = 0,
			t2 = 0,
			r1, r2;
		try {
			t1 = arg1.toString().split(".")[1].length
		} catch (e) {}
		try {
			t2 = arg2.toString().split(".")[1].length
		} catch (e) {}
		r1 = Number(arg1.toString().replace(".", ""));
		r2 = Number(arg2.toString().replace(".", ""));
		return (r1 / r2) * Math.pow(10, t2 - t1);
	}

	/*

	 * @parm array 排序的数组对象
	 
	 * @parm key 要根据数组对象的哪条属性排序
	 
	 * @parm updown （up 升序 down 降序）
	 
	 */

	function sortByKey(array, key, updown, type) {
		return array.sort(function(a, b) {
			var x = a[key];
			var y = b[key];
			if (type != 'string') {
				x = parseFloat(x);
				y = parseFloat(y);
			}
			if (updown == "up") {
				return (x < y) ? -1 : ((x > y) ? 1 : 0);
			}
			if (updown == "down") {
				return (x < y) ? 1 : ((x > y) ? -1 : 0);
			}
		})
	};

	function sortBy(attr, rev, type) {
		//第二个参数没有传递 默认升序排列
		if (rev == undefined) {
			rev = 1;
		} else {
			rev = (rev) ? 1 : -1;
		}

		return function(a, b) {
			a = a[attr];
			b = b[attr];
			if (type != 'string') {
				a = parseFloat(a);
				b = parseFloat(b);
			}
			if (a < b) {
				return rev * -1;
			}
			if (a > b) {
				return rev * 1;
			}
			return 0;
		}
	}

	const analogy = (type, dataType) => {
		if (type == "sell" || type == "buy") {
			if (dataType == type) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	};

	const analogyLetter = (currency, _valBig, _valSmall) => {
		if (_valBig && _valSmall) {
			if (currency.indexOf(_valBig) != -1 || currency.indexOf(_valSmall) != -1) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	};

	let coinBool = true;
	let buyBtcNum = "0.000000";
	let buyBool = true;
	let sellBool = true;

	/*
	 * 数据请求 by wangqq
	 */

	// 当前委托的请求
	let current = () => {
		$.ajax({
			type: "POST",
			url: "/index/Orders/getuserOrders",
			data: {
				"currency_id": currency + '_' + trade_currency
			},
			success(data) {
				if (data.length <= 0) {
					$("#open_orders_body .J_list_prompt").css("display", "block");
					$("#open_orders_body .l_transac_list").css("display", "none");
				} else {
					$("#open_orders_body .J_list_prompt").css("display", "none");
					$("#open_orders_body .l_transac_list").css("display", "block");
					$("#open_orders_body .l_transac_list").empty();
					let oHtml = '';
					let type = $("#open_orders_scroll .l_tab_transac .z_active a").attr("data-type");
					for (let i = 0; i < data.length; i++) {
						oHtml += `<div class="l_tr" data-type="${data[i].type}" style="display:${analogy(type,data[i].type)?"block":"none"}">
                       <ul>
                         <li class="trade_time">${data[i].add_time}</li>
                         <li class="trade_type">幣幣交易</li>
                         <li class="trade_pair">${c}/${c_trade}</li>
                         <li class="color_${data[i].type=="sell"?"down":"up"}">${data[i].type_name}</li>
                         <li>${data[i].price}</li>
                         <li>${data[i].num}</li>
                         <li>${data[i].totalmoney}</li>
                         <li>${data[i].trade_num}</li>
                         <li>${data[i].have_num}</li>
                         <li>
                           <a href="javascript:;" data-id="${data[i].orders_id}" class="btn_cancel">撤單</a>	
                         </li>
                       </ul>
                     </div>`
					}
					$("#open_orders_body .l_transac_list").append(oHtml);
				}
			},
			error(error) {
				console.log(error);
			}
		});
	};

	// 历史委托的请求
	let history = () => {
		$.ajax({
			type: "POST",
			url: "/index/Orders/getuserOrders_history",
			data: {
				"currency_id": currency + '_' + trade_currency
			},
			success(data) {
				if (data.length <= 0) {
					$("#order_history_body .J_list_prompt").css("display", "block");
					$("#order_history_body .l_transac_list").css("display", "none");
				} else {
					$("#order_history_body .J_list_prompt").css("display", "none");
					$("#order_history_body .l_transac_list").css("display", "block");
					$("#order_history_body .l_transac_list").empty();
					let oHtml = '';
					let type = $("#order_history_scroll .l_tab_transac .z_active a").attr("data-type");
					for (let i = 0; i < data.length; i++) {
						oHtml += `<div class="l_tr" data-type="${data[i].type}"  style="display:${analogy(type,data[i].type)?"block":"none"}">
                       <ul>
                         <li class="trade_time">${data[i].add_time}</li>
                         <li class="trade_type">幣幣交易</li>
                         <li class="trade_pair">${c}/${c_trade}</li>
                         <li class="color_${data[i].type=="sell"?"down":"up"}">${data[i].type_name}</li>
                         <li>${data[i].price}</li>
                         <li>${data[i].num}</li>
                         <li>${data[i].trade_num}</li>
                         <li>${data[i].cprice}</li>
                         <li>${data[i].status==-1?"撤销":"成交"}</li>
                         <li>
                         </li>
                       </ul>
                     </div>`
					}
					$("#order_history_body .l_transac_list").append(oHtml);
				}
			},
			error(error) {
				console.log(error);
			}
		});
	};

	// 交易对的请求
	let transactionArray = [];
	let transactionPair = (mark) => {
		$.ajax({
			type: "POST",
			url: "/index/Orders/get_currency_data",
			data: {
				"currency_mark": mark,
				"currency_id": currency + '_' + trade_currency
			},
			beforeSend() {
				if (!coinBool) {
					if ($(".coin_list dl dd").length > 0) {
						$(".coin_price").each((index, item) => {
							$(item).html("--");
						});
						$(".coin_change").each((index, item) => {
							$(item).css("color", "#B3C1E6");
							$(item).html("--");
						});
					}
				}
			},
			success(data) {
				transactionArray = [];
				coinBool = true;
				if (data.length <= 0) {
					$(".coin_list .loading").css("display", "block");
					$(".coin_list dl").css("display", "none");
				} else {
					$(".coin_list .loading").css("display", "none");
					$(".coin_list dl").css("display", "block");
					if ($(".coin_unit div>.active").length > 0) {
						let active = $(".coin_unit div>.active");
						let key = active.attr("data-key");
						let type = active.attr("data-type");
						let order = active.attr("data-order") == "up" ? "down" : "up";
						sortByKey(data, key, order, type);
					}
					$(".coin_list dl").empty();
					let cHtml = '';
					let _valBig = $("#search").val().toUpperCase();
					let _valSmall = $("#search").val().toLowerCase();
					let rate = "";
					if($(".switch_box").hasClass('active')){
						rate = "new_price_"+smallSymbol;
					}else{
						rate = "new_price";
					}
					for (let p in data) {
						transactionArray.push(data[p]);
						let change = data[p]["24H_change"];
						cHtml += `<dd data-pair="${data[p].currency_mark+"_"+data[p].trade_currency_mark}" data-currency="${data[p].currency_mark}" data-href="/currency/${data[p].currency_mark+"_"+data[p].trade_currency_mark}" style="display:${analogyLetter(data[p].currency_mark,_valBig,_valSmall)?"block":"none"}">
		                <div class="coin_unit">
		                  <div>
		                    <span>
		                      <em>${data[p].currency_mark}</em>
		                    </span>
		                    <span class="coin_price">${data[p][rate]}</span>
		                    <span class="coin_change color-${change<0?"sell":"buy"}">${change}%</span>
		                  </div>
		                </div>
		              </dd>`
					};
					$(".coin_list dl").append(cHtml);
				};
			},
			error(error) {
				console.log(error)
			}
		});
	};

	// 实时交易
	let realTime = () => {
		$.ajax({
			type: "POST",
			url: "/index/Orders/gettrade",
			data: {
				"currency_id": currency + '_' + trade_currency
			},
			success(data) {
				let timeHtml = '';
				let typeHtml = '';
				let priceHtml = '';
				let numHtml = '';
				for (let i = 0; i < data.length; i++) {
					timeHtml += `<dd>${getDate(data[i].trade_time)}</dd>`;
					if (data[i].type == 'sell') {
						typeHtml += `<dd class="color_down">${sellfont}</dd>`;
					} else {
						typeHtml += `<dd class="color_up">${buyfont}</dd>`;
					}
					priceHtml += `<dd>${data[i].price}</d>`;
					numHtml += `<dd>${data[i].num}</d>`
				}
				$(".market_trades_time dd").remove();
				$(".market_trades_time").append(timeHtml);
				$(".market_trades_type dd").remove();
				$(".market_trades_type").append(typeHtml);
				$(".market_trades_price dd").remove();
				$(".market_trades_price").append(priceHtml);
				$(".market_trades_amount dd").remove();
				$(".market_trades_amount").append(numHtml);
			},
			error(error) {
				console.log(error);
			}
		});
	};

	var sellFirst;
	// 卖7
	let sellRecord = () => {
		$.ajax({
			type: "POST",
			url: "/index/Orders/getOrders",
			data: {
				"type": 'sell',
				"currency_id": currency + '_' + trade_currency,
				"limit_min_price": limitMin,
				"limit_max_price": limitMax,
				"is_limit": isLimit,
				"is_time": isTime,
				"min_time": minTime,
				"max_time": maxTime,
			},
			success(data) {
				if(data.length != 0){
					let length = data.length;
					let sellHtml = '';
					if (sellBool) {
						sellBool = false;
						let price = data.length > 0 ? data[data.length - 1].price : "";
						$(".buy_panel input[name='price']").val(price);
						$(".buy_panel input[name='price']").trigger("input");
					}
					if (length <= 0) {
						for (let i = 0; i < 7; i++) {
							sellHtml += `<dd>
											<div class="inner">
												<span class="title color-sell">${sellFont+(7-i)}</span>
												<span class="price">---</span>
												<span class="amount">---</span>
												<b class="color-sell-bg"></b>
											</div>
										</dd>`;
						}
					} else {
						if (length > 7) {
							for (let i = 0; i < 7; i++) {
								sellHtml += `<dd data-price="${data[i].price}">
											<div class="inner">
												<span class="title color-sell">${sellFont+(7-i)}</span>
												<span class="price">${data[i].price}</span>
												<span class="amount">${parseFloat(data[i]['num'] - data[i]['trade_num']).toFixed(6)}</span>
												<b class="color-sell-bg" style="width:${data[i].new_bili}%"></b>
											</div>
										</dd>`;
							}
						} else {
							var remian = 7 - length;
							for (let i = 0; i < 7 - data.length; i++) {
								sellHtml += `<dd>
											<div class="inner">
												<span class="title color-sell">${sellFont+(7-i)}</span>
												<span class="price">---</span>
												<span class="amount">---</span>
												<b class="color-sell-bg"></b>
											</div>
										</dd>`;
							}
							for (let i = 0; i < data.length; i++) {
								sellHtml += `<dd data-price="${data[i].price}">
											<div class="inner">
												<span class="title color-sell">${sellFont+(7-i-remian)}</span>
												<span class="price">${data[i].price}</span>
												<span class="amount">${parseFloat(data[i]['num'] - data[i]['trade_num']).toFixed(6)}</span>
												<b class="color-sell-bg" style="width:${data[i].new_bili}%"></b>
											</div>
										</dd>`;
							}
						}
					}
					sellFirst = data[0].price;
					$("#marketDepthSell dd").remove();
					$("#marketDepthSell").append(sellHtml);
				}
			},
			error(error) {
				console.log(error);
			}
		});
	};

	// 买7
	var buyFirst;
	let buyRecord = () => {
		$.ajax({
			type: "POST",
			url: "/index/Orders/getOrders2",
			data: {
				"type": 'buy',
				"currency_id": currency + '_' + trade_currency,
				"limit_min_price": limitMin,
				"limit_max_price": limitMax,
				"is_limit": isLimit,
				"is_time": isTime,
				"min_time": minTime,
				"max_time": maxTime,
			},
			success(data) {
				if(data.length != 0){
					if (buyBool) {
						buyBool = false;
						let price = data.length > 0 ? data[0].price : "";
						$(".sell_panel input[name='price']").val(price);
						$(".sell_panel input[name='price']").trigger("input");
					}
					if (!$(".buy_panel input[name='price']").val()) {
						if (data[0]) {
							buyBtcNum = div(maxNumBuy, data[0].price);
							buyBtcNum = String(buyBtcNum).replace(/^(.*\..{6}).*$/, "$1");
							buyBtcNum = Number(buyBtcNum).toFixed(6);
						} else {
							buyBtcNum = "0.000000";
						}
						$(".buy_panel .max_num").html(buyBtcNum)
					}
					let length = data.length;
					let sellHtml = '';
					if (length <= 0) {
						for (let i = 0; i < 7; i++) {
							sellHtml += `<dd>
											 <div class="inner">
												 <span class="title color-buy">${buyFont+(i+1)}</span>
												 <span class="price">---</span>
												 <span class="amount">---</span>
												 <b class="color-buy-bg"></b>
											 </div>
										 </dd>`;
						}
					} else {
						for (let i = 0; i < 7; i++) {
							if (data[i]) {
								sellHtml += `<dd data-price="${data[i].price}">
											 <div class="inner">
												 <span class="title color-buy">${buyFont+(i+1)}</span>
												 <span class="price">${data[i].price}</span>
												 <span class="amount">${parseFloat(data[i]['num'] - data[i]['trade_num']).toFixed(6)}</span>
												 <b class="color-buy-bg" style="width:${data[i].new_bili}%"></b>
											 </div>
										 </dd>`;
							} else {
								sellHtml += `<dd>
											 <div class="inner">
												 <span class="title color-buy">${buyFont+(i+1)}</span>
												 <span class="price">---</span>
												 <span class="amount">---</span>
												 <b class="color-buy-bg"></b>
											 </div>
										 </dd>`;
							}
						}
					}
					buyFirst = data[0].price;
					$("#marketDepthBuy dd").remove();
					$("#marketDepthBuy").append(sellHtml);
				}
			},
			error(error) {
				console.log(error);
			}
		});
	};

	// 最新价的请求
	let latestPrice = () => {
		$.ajax({
			type: "POST",
			url: "/index/Orders/getnewbuyprice",
			data: {
				"currency_id": currency + '_' + trade_currency
			},
			success(data) {
				let ticker = rateStatus=="unit_cny"?data.new_price_cny+ " CNY":data.new_price_usd+ " USD";
				$("#tickerClose").html(data.new_price);
				$("#tickerCny").html("≈ " + ticker);
				$(".ticker_close").html(data.new_price);
				$("#tickerCny_ticker_bar").html("≈ " + ticker);
				if (data.change_24 < 0) {
					$("span[name='rate']").addClass("color_down").removeClass("color_up");
				} else {
					$("span[name='rate']").addClass("color_up").removeClass("color_down");
				}
				$("span[name='rate']").html(data["change_24"] + "%");
				$("span[name='high']").html(data.max_price);
				$("span[name='low']").html(data.min_price);
				$("span[name='amout']").html(data["24H_done_num"] + " " + c);
			},
			error(error) {
				console.log(error);
			}
		});
	};

	// 可用资产
	let availableAssets = () => {
		$.ajax({
			type: "POST",
			url: "/index/Orders/get_new_money",
			data: {
				"currency": c + '_' + c_trade
			},
			success(data) {
				if (data.Code == "10000") {
					$(".buy_available").html(data.Msg.currency_trade.num);
					$(".sell_available").html(data.Msg.currency.num)
				}
			},
			error(error) {
				console.log(error);
			}
		});
	}

	let mark = c_trade;
	$(".coin_tab span").each((index, item) => {
		if ($(item).attr("data-mark") == mark) {
			$(item).addClass("cur");
		}
	});

	// 第一次请求
	if (user) {
		history();
		current();
	}
	transactionPair(mark);
	realTime();
	sellRecord();
	buyRecord();
	latestPrice();

	// 5秒轮询
	setInterval(function() {
		if (user) {
			history();
			current();
			availableAssets();
		}
		transactionPair(mark);
		realTime();
		buyRecord();
		latestPrice();

	}, 5000);

	// 4秒轮询
	setInterval(function() {
		sellRecord();
	}, 4000);

	/*
	 * 操作 by wangqq
	 */

	// 点击排序
	$(".coin_unit span").on("click", function() {
		$(this).addClass("active");
		let key = $(this).attr("data-key");
		let type = $(this).attr("data-type");
		let order = $(this).attr("data-order");
		$(this).siblings().attr("data-order", "down");
		$(this).siblings().find("*").removeClass('active');
		$(this).siblings().removeClass('active');
		if (order == "up") {
			$(this).find(".asc").addClass('active');
			$(this).find(".desc").removeClass('active');
		} else {
			$(this).find(".desc").addClass('active');
			$(this).find(".asc").removeClass('active');
		}
		sortByKey(transactionArray, key, order, type);
		order = order == "down" ? "up" : "down";
		$(this).attr("data-order", order);
		$(".coin_list .loading").css("display", "none");
		$(".coin_list dl").css("display", "block");
		$(".coin_list dl").empty();
		let cHtml = '';
		let _valBig = $("#search").val().toUpperCase();
		let _valSmall = $("#search").val().toLowerCase();
		for (let p in transactionArray) {
			let change = transactionArray[p]["24H_change"];
			cHtml += `<dd data-pair="${transactionArray[p].currency_mark+"_"+transactionArray[p].trade_currency_mark}" data-currency="${transactionArray[p].currency_mark}" data-href="/currency/${transactionArray[p].currency_mark+"_"+transactionArray[p].trade_currency_mark}" style="display:${analogyLetter(transactionArray[p].currency_mark,_valBig,_valSmall)?"block":"none"}">
		                <div class="coin_unit">
		                  <div>
		                    <span>
		                      <em>${transactionArray[p].currency_mark}</em>
		                    </span>
		                    <span class="coin_price">${transactionArray[p].new_price}</span>
		                    <span class="coin_change color-${change<0?"sell":"buy"}">${change}%</span>
		                  </div>
		                </div>
		              </dd>`
		};
		$(".coin_list dl").append(cHtml);
	});

	// 买7点击
	$(document).on("click", "#marketDepthBuy dd", function() {
		if ($(this).attr("data-price")) {
			let price = $(this).attr("data-price");
			$(".buy_panel input[name='price']").val(price);
			$(".buy_panel input[name='price']").trigger("input");
			$(".sell_panel input[name='price']").val(price);
			$(".sell_panel input[name='price']").trigger("input");
		}
	});

	// 卖7点击
	$(document).on("click", "#marketDepthSell dd", function() {
		if ($(this).attr("data-price")) {
			let price = $(this).attr("data-price");
			$(".sell_panel input[name='price']").val(price);
			$(".sell_panel input[name='price']").trigger("input");
			$(".buy_panel input[name='price']").val(price);
			$(".buy_panel input[name='price']").trigger("input");
		}
	});

	// 点击交易对跳转页面 
	$(document).on("click", ".coin_list dl dd", function() {
		let pair = c + "_" + c_trade;
		if ($(this).attr("data-pair") != pair) {
			location.href = "/index/Orders/exchange" + $(this).attr("data-href");
		}
	});

	// 资产显示 
	let balance = $("#total_balance b").html(),
		balanceUsd = $(".d_total").html();
	$("#total_eyes").on("click", function() {
		if ($(this).hasClass("open")) {
			$(this).html("&#xe609;");
			$(this).removeClass("open");
			$("#total_balance b").html("*****");
			$(".d_total").html("*****");
		} else {
			$(this).html("&#xe603;");
			$(this).addClass("open");
			$("#total_balance b").html(balance);
			$(".d_total").html(balanceUsd);
		}
	});

	// 母币选择
	$(".coin_tab span").on("click", function() {
		coinBool = false;
		$(this).addClass("cur");
		$(this).siblings("span").removeClass("cur");
		transactionPair($(this).attr("data-mark"));
		mark = $(this).attr("data-mark");
	});

	$("#chartMask").on("click", function(e) {
		e.stopPropagation();
		$(this).css("display", "none");
	});

	$(document).on("click", function() {
		$("#chartMask").css("display", "block");
	});

	// 限价输入框
	// 买入价
	$(".buy_panel input[name='price']").on("input propertychange blur", function() {
		let re = /^[0-9]+.?[0-9]*$/;
		let num = (parseFloat($(this).val()) / usd2cny).toFixed(2);
		if ($(this).val() > 0) {
			let btcNum = div(maxNumBuy, $(this).val());
			btcNum = String(btcNum).replace(/^(.*\..{6}).*$/, "$1");
			btcNum = Number(btcNum).toFixed(6);
			$(".buy_panel .max_num").html(btcNum);
		} else {
			$(".buy_panel .max_num").html(buyBtcNum);
		}
		if (!re.test(num) || $(this).val() == "") {
			num = '0.00';
			$('#buy_limit_math_price').css("display", "none");
		} else {
			$('#buy_limit_math_price').css("display", "block");
			$('#buy_limit_math_price').html("≈ " + num + `${rateStatus=="unit_cny"?" CNY":" USD"}`);
		}
		if ($(".buy_panel input[name='amount']").val()) {
			let _val = (Number($(".buy_panel input[name='price']").val()) * Number($(".buy_panel input[name='amount']").val()));
			_val = _val.toFixed(6);
			$("#buyAmount").html(_val);
		}
	});

	// 卖出价
	$(".sell_panel input[name='price']").on("input propertychange blur", function() {
		let re = /^[0-9]+.?[0-9]*$/;
		let num = (parseFloat($(this).val()) / usd2cny).toFixed(2);
		if (!re.test(num) || $(this).val() == "") {
			num = '0.00';
			$('#sell_limit_math_price').css("display", "none");
		} else {
			$('#sell_limit_math_price').css("display", "block");
			$('#sell_limit_math_price').html("≈ " + num + `${rateStatus=="unit_cny"?" CNY":" USD"}`);
		}
		if ($(".sell_panel input[name='amount']").val()) {
			let _val = (Number($(".sell_panel input[name='price']").val()) * Number($(".sell_panel input[name='amount']").val()));
			_val = _val.toFixed(6);
			$("#sellAmount").html(_val);
		}
	});

	// 数量输入框
	// 买入量
	$(".buy_panel input[name='amount']").on("input propertychange blur", function() {
		let _val = parseFloat($(this).val()).toFixed(6);
		let cont = parseFloat($(".buy_panel .max_num").html()).toFixed(6);
		let _value = _val * 1000000;
		let _cont = cont * 1000000;
		if (_val) {
			if (_value < _cont) {
				let num = $(this).val() / cont * 100;
				let _sub = sub(cont, $(this).val());
				$("#buyRange").val(num);

			} else if (_value > _cont) {
				$(this).val(cont);
				let num = $(this).val() / cont * 100;
				$("#buyRange").val(num);
			}
			if ($(".buy_panel input[name='price']").val()) {
				let _val = (Number($(".buy_panel input[name='price']").val()) * Number($(".buy_panel input[name='amount']").val()));
				_val = _val.toFixed(6);
				$("#buyAmount").html(_val);
			}
		}
	});

	// 卖出量
	$(".sell_panel input[name='amount']").on("input propertychange blur", function() {
		let _val = parseFloat($(this).val()).toFixed(6);
		let cont = parseFloat($(".sell_available").html()).toFixed(6);
		let _value = _val * 1000000;
		let _cont = cont * 1000000;
		if (_val) {
			if (_value < _cont) {
				let num = $(this).val() / cont * 100;
				let _sub = sub(cont, $(this).val());
				$("#sellRange").val(num);

			} else if (_value > _cont) {
				$(this).val(cont);
				let num = $(this).val() / cont * 100;
				$("#sellRange").val(num);
			}
			if ($(".sell_panel input[name='price']").val()) {
				let _val = (Number($(".sell_panel input[name='price']").val()) * Number($(".sell_panel input[name='amount']").val()));
				_val = _val.toFixed(6);
				$("#sellAmount").html(_val);
			}
		}
	});

	// 滑动
	// 买入量
	let buyRange = document.querySelector('#buyRange');
	buyRange.addEventListener('input', function() {
		let cont = parseFloat($(".buy_panel .max_num").html());
		let num = this.value / 100 * cont;
		let bool = String(num).split(".")[1];
		if (bool) {
			if (bool.length < 6) {
				num = num.toFixed(6);
			};
		}
		num = String(num).replace(/^(.*\..{6}).*$/, "$1");
		num = Number(num).toFixed(6);
		if (this.value == 100) {
			cont = cont.toFixed(6);
			$(".buy_panel input[name='amount']").val(cont);
			if ($(".buy_panel input[name='price']").val()) {
				let _val = (Number($(".buy_panel input[name='price']").val()) * Number($(".buy_panel input[name='amount']").val()));
				_val = _val.toFixed(6);
				$("#buyAmount").html(_val);
			}
		} else if (this.value == 0) {
			$(".buy_panel input[name='amount']").val('');
			if ($(".buy_panel input[name='price']").val()) {
				$("#buyAmount").html("0.000000");
			}
		} else {
			$(".buy_panel input[name='amount']").val(num);
			if ($(".buy_panel input[name='price']").val()) {
				let _val = (Number($(".buy_panel input[name='price']").val()) * Number($(".buy_panel input[name='amount']").val()));
				_val = _val.toFixed(6);
				$("#buyAmount").html(_val);
			}
		}
	});

	// 卖出量
	let sellRange = document.querySelector('#sellRange');
	sellRange.addEventListener('input', function() {
		let cont = parseFloat($(".sell_available").html());
		let num = this.value / 100 * cont;
		let bool = String(num).split(".")[1];
		if (bool) {
			if (bool.length < 6) {
				num = num.toFixed(6);
			};
		}
		num = String(num).replace(/^(.*\..{6}).*$/, "$1");
		num = Number(num).toFixed(6);
		if (this.value == 100) {
			cont = cont.toFixed(6);
			$(".sell_panel input[name='amount']").val(cont);
			if ($(".sell_panel input[name='price']").val()) {
				let _val = (Number($(".sell_panel input[name='price']").val()) * Number($(".sell_panel input[name='amount']").val()));
				_val = _val.toFixed(6);
				$("#sellAmount").html(_val);
			}
		} else if (this.value == 0) {
			$(".sell_panel input[name='amount']").val('');
			if ($(".sell_panel input[name='price']").val()) {
				$("#sellAmount").html("0.000000");
			}
		} else {
			$(".sell_panel input[name='amount']").val(num);
			if ($(".sell_panel input[name='price']").val()) {
				let _val = (Number($(".sell_panel input[name='price']").val()) * Number($(".sell_panel input[name='amount']").val()));
				_val = _val.toFixed(6);
				$("#sellAmount").html(_val);
			}
		}
	});
	$(".vo_all_cont_ni").val("");

	// 点击买入
	let status = '';
	let buyLock = true;
	$(".vo_all_close_x,.vo_all_button_o").on("click",function(){
		$(".shade").css("display", "none");
		$(".vo_all").css("display", "none");
		buyLock = true;
		sellLock = true;
		$(".vo_all_cont_ni").val("");
	});
	$(".btn_buy").on("click", function() {
		if (buyLock) {
			buyLock = false;
			if ($(".buy_panel input[name='price']").val() == "") {
				layer.msg(bpe);
				buyLock = true;
				$(".buy_panel input[name='price']").focus();
				return;
			}
			if ($(".buy_panel input[name='amount']").val() == "") {
				layer.msg(nbe);
				$(".buy_panel input[name='amount']").focus();
				buyLock = true;
				return;
			}
			if ($(".buy_panel input[name='amount']").val() <= 0) {
				layer.msg(nlz);
				$(".buy_panel input[name='amount']").focus();
				buyLock = true;
				return;
			}
			if (($(".buy_panel input[name='price']").val()) * ($(".buy_panel input[name='amount']").val()) < 0) {
				layer.msg(ttr);
				buyLock = true;
				return;
			}
			status = "buy";
			$(".shade").css("display", "block");
			$(".vo_all").css("display", "block");
		}
	});

	// 点击卖出
	let sellLock = true;
	$(".btn_sell").on("click", function() {
		if (sellLock) {
			sellLock = false;
			if ($(".sell_panel input[name='price']").val() == "") {
				layer.msg(spe);
				sellLock = true;
				$(".sell_panel input[name='price']").focus();
				return;
			}
			if ($(".sell_panel input[name='amount']").val() == "") {
				layer.msg(nse);
				sellLock = true;
				$(".sell_panel input[name='amount']").focus();
				return;
			}
			if ($(".sell_panel input[name='amount']").val() <= 0) {
				layer.msg(nslz);
				sellLock = true;
				$(".sell_panel input[name='amount']").focus();
				return;
			}
			if (($(".sell_panel input[name='price']").val()) * ($(".sell_panel input[name='amount']").val()) < 0) {
				layer.msg(ttr);
				sellLock = true;
				return;
			}
			status = "sell";
			$(".shade").css("display", "block");
			$(".vo_all").css("display", "block");
		}
	});

	// 安全密码确认
	$(".vo_all_button_t").on("click",function(){
		var pwd = $(".vo_all_cont_ni").val();
		if(pwd == ""){
			layer.msg(tpe);
			return;
		}
		if(status == "buy"){
			$.ajax({
				type: "post",
				url: buyUrl,
				data: {
					buyprice: $(".buy_panel input[name='price']").val(),
					buynum: $(".buy_panel input[name='amount']").val(),
					currency_id: currency + '_' + trade_currency,
					pwd:pwd,
				},
				async: true,
				success: function(d) {
					if (d.status != 1) {
						layer.msg(d.info);
					} else {
						layer.msg(d.info);
						$(".shade").css("display", "none");
						$(".vo_all").css("display", "none");
						$(".buy_panel input[name='price']").val(sellFirst);
						$(".buy_panel input[name='amount']").val("");
						$('#buy_limit_math_price').css("display", "none");
						$('#sell_limit_math_price').css("display", "none");
						current();
						$(".buy_available").html(d.to_over);
						$(".buy_panel .max_num").html(d.to_over);
						maxNumBuy = d.to_over;
						if ($(".buy_panel input[name='price']").val()) {
							let price = $("#marketDepthBuy dd .inner .price").html();
							buyBtcNum = "0.000000";
							if (price) {
								buyBtcNum = div(maxNumBuy, sellFirst);
								buyBtcNum = String(buyBtcNum).replace(/^(.*\..{6}).*$/, "$1");
								buyBtcNum = Number(buyBtcNum).toFixed(6);
							}
							$(".buy_panel .max_num").html(buyBtcNum);
						}
					}
					$(".vo_all_cont_ni").val("");
					buyLock = true;
				}
			});
		}else{
			$.ajax({
				type: "post",
				url: sellUrl,
				data: {
					sellprice: $(".sell_panel input[name='price']").val(),
					sellnum: $(".sell_panel input[name='amount']").val(),
					currency_id: currency + '_' + trade_currency,
					pwd:pwd,
				},
				async: true,
				success: function(d) {
					if (d.status != 1) {
						layer.msg(d.info);
					} else {
						layer.msg(d.info);
						$(".shade").css("display", "none");
						$(".vo_all").css("display", "none");
						$(".sell_panel input[name='price']").val(buyFirst);
						$(".sell_panel input[name='amount']").val("");
						$('#buy_limit_math_price').css("display", "none");
						$('#sell_limit_math_price').css("display", "none");
						$(".sell_available").html(d.from_over);
						$(".sell_panel .max_num").html(d.from_over);
						current();
					}
					$(".vo_all_cont_ni").val("");
					sellLock = true;
				}
			});
		}
	});

	// 撤单(当前委托)
	let cancelLock = true;
	$(document).on("click", ".btn_cancel", function() {
		if (cancelLock) {
			cancelLock = false;
			$.ajax({
				type: "POST",
				url: cancelUrl,
				data: {
					status: -1,
					order_id: $(this).attr("data-id")
				},
				success(data) {
					layer.msg(data.info);
					if (data.status == 1) {
						current();
						history();
						availableAssets();
					}
					cancelLock = true;
				},
				error(error) {
					console.log(error);
				}
			});
		}
	});

	// 筛选(当前委托);
	$("#open_orders_scroll .l_tab_transac li").on("click", function() {
		$(this).addClass("z_active");
		$(this).siblings("li").removeClass("z_active");
		let type = $(this).find("a").attr("data-type");
		if (type == "all") {
			$("#open_orders_scroll .l_tr").each((index, item) => {
				$(item).css("display", "block");
			});
		} else {
			$("#open_orders_scroll .l_tr").each((index, item) => {
				if ($(item).attr("data-type") == type) {
					$(item).css("display", "block");
				} else {
					$(item).css("display", "none");
				}
			});
		}
	});

	// 筛选(历史委托);
	$("#order_history_scroll .l_tab_transac li").on("click", function() {
		$(this).addClass("z_active");
		$(this).siblings("li").removeClass("z_active");
		let type = $(this).find("a").attr("data-type");
		if (type == "all") {
			$("#order_history_scroll .l_tr").each((index, item) => {
				$(item).css("display", "block");
			});
		} else {
			$("#order_history_scroll .l_tr").each((index, item) => {
				if ($(item).attr("data-type") == type) {
					$(item).css("display", "block");
				} else {
					$(item).css("display", "none");
				}
			});
		}
	});

	// 隐藏或展示
	$(".mod_show_btn").on("click", function() {
		var bool = $(this).parent().parent().hasClass("out");
		if (bool) {
			$(this).parent().parent().removeClass("out");
		} else {
			$(this).parent().parent().addClass("out");
		}
	});

	// 币对搜索
	$("#search").on("input", function() {
		if ($(this).val().length > 0) {
			let _valBig = $(this).val().toUpperCase();
			let _valSmall = $(this).val().toLowerCase();
			$(".s_symol").css("display", "none");
			$(".s_clear").css("display", "inline-block");
			$(".coin_list dl dd").each((index, item) => {
				let currency = $(item).attr("data-currency");
				if (currency.indexOf(_valBig) != -1 || currency.indexOf(_valSmall) != -1) {
					$(item).css("display", "block");
				} else {
					$(item).css("display", "none");
				}
			});
		} else {
			$(".s_symol").css("display", "inline-block");
			$(".s_clear").css("display", "none");
			$(".coin_list dl dd").each((index, item) => {
				$(item).css("display", "block");
			});
		}
	});
	$(".s_clear").on("click", function() {
		$("#search").val('');
		$(".s_symol").css("display", "inline-block");
		$(".s_clear").css("display", "none");
		$(".coin_list dl dd").each((index, item) => {
			$(item).css("display", "block");
		});
	});

	// 交易对切换汇率
	$(".switch_box").on("click",function(){
		let rateSymbol = $(".switch_box b").html();
		if($(this).hasClass('active')){
			$(this).removeClass('active');
			$(".coin_unit div span").eq(1).find("em").remove();
		}else{
			$(this).addClass('active');
			$(".coin_unit div span").eq(1).find("i").before(`<em class="uppercase">(${rateSymbol})</em>`);
		}
		transactionPair(mark);
	});
});