$(function(){
	// 行情请求接口
	function market(mark){
		$.ajax({
			url:"/index/orders/index_bb",
			type:"POST",
			data:{
				"currency":mark,
			},
			beforeSend:function(){
				if(LBool){
					$("."+_class).siblings().css("display","none");
					$("."+_class).css("display","none");
					var loadingHtml = '<div class="loading">'+
					        '<span></span>\n'+
					        '<span></span>\n'+
					        '<span></span>\n'+
					        '<span></span>\n'+
					        '<span></span>'+
					'</div>';
					$(".quotes").append(loadingHtml);
				}
			},
			success:function(data){
				$(".quotes .loading").remove();
				$(".jiugongge").empty();
				$(".m_list dd").remove();
				$("."+_class).css("display","block");
				LBool = false;
				var cHtml = "";
				for(var i=0;i<data.length;i++){
					if(_class=="m_list"){
						cHtml += `<dd>
									<a href="/index/Orders/exchange/currency/${data[i].currency_mark+'_'+data[i].trade_currency_mark}">
										<div>
											<img src="${data[i].currency_logo}" >${data[i].currency_mark} / ${data[i].trade_currency_mark}</span>
										</div>
<!--										<div>IO</div>-->
										<div>${data[i].new_price}</div>
										<div>${data[i]["24H_done_num"]}</div>
										<div>${data[i]["24H_change_price"]}</div>
										<div class="${data[i]["24H_change"]<0?"drop":"rise"}">
											<span class="${data[i]["24H_change"]<0?"caret_down":"caret_up"}"></span><span><i class="change">${data[i]["24H_change"]}</i>%</span>
										</div>
										<div class="trend" data-id="${data[i].trade_currency_id+"_"+data[i].currency_id}" data-trends="${data[i].trends}" style="height:60px;">
											<canvas id="canDom_${data[i].trade_currency_id+"_"+data[i].currency_id}" class="canvas_trends" width="133" height="33" style="direction: ltr; position: absolute; left: 50%; top: 50%; width: 80%; height: 50%;background: #fff;transform: translate(-50%,-50%);"></canvas>
										</div>
									</a>
								</dd>`;
					}else{
						cHtml += `<li>
									<a href="/index/Orders/exchange/currency/${data[i].currency_mark+'_'+data[i].trade_currency_mark}">
										<div class="pair">
											<h2>
												${data[i].currency_mark} / ${data[i].trade_currency_mark}
											</h2>
											<h2 class="right ${data[i]["24H_change"]<0?"drop":"rise"}">
												<span class="${data[i]["24H_change"]<0?"caret_down":"caret_up"}"></span><span><i class="change">${data[i]["24H_change"]}</i>%</span>
											</h2>
											<div style="clear: both;"></div>
										</div>
										<div class="subtitle">
											<h2>
												XRP Plus
											</h2>
											<h2 class="right">
												${data[i].new_price}
											</h2>
											<div style="clear: both;"></div>
										</div>
										<div class="trend" data-id="${data[i].trade_currency_id+"_"+data[i].currency_id}" data-trends="${data[i].trends}">
											<canvas id="canDom_${data[i].trade_currency_id+"_"+data[i].currency_id}" class="canvas_trends" width="133" height="33" style="direction: ltr; position: absolute; left: 50%; top: 50%; width: 80%; height: 50%;background: #fff;transform: translate(-50%,-50%);"></canvas>
										</div>
										<div class="data_box pure-g">
											<div class="pure-u-1-3">
												<span>H</span><span>${data[i].max_price}</span>
											</div>
											<div class="pure-u-1-3">
												<span>L</span><span>${data[i].min_price}</span>
											</div>
											<div class="pure-u-1-3">
												<span>V</span><span>${data[i]["24H_done_num"]}</span>
											</div>
										</div>
									</a>
								</li>`;
						
					}
				};
				$("."+_class).append(cHtml);
				canvesFn();
			},
			error:function(error){
				console.log(error);
			},
		});
	}
	// 母币初始值
	var _class = $(".posture .active").attr("data-class");
	var mark = $(".m_tab .active").attr("data-mark");
	var LBool = true;
	market(mark);

	// 5秒轮询
	setInterval(function(){
		market(mark);
	},5000)

	var drawLine = {};
	! function() {
		//求数组最大值
		Array.prototype.max = function() {
			var max = this[0];
			var len = this.length;
			for (var i = 1; i < len; i++) {
				if (this[i] > max) {
					max = this[i];
				}
			}
			return max;
		}

		function draw(arr, obj, index) {
			var change = $(".change").eq(index).html();
			var width = 133,
				height = 33;
			var maxV = arr.max();
			//计算y轴增量
			var yStep = height / maxV;
			var domCan = obj;
			var g = domCan.getContext("2d");
			g.beginPath();
			g.lineWidth = 2;
			g.strokeStyle = change<0?"#EA6E44":"#03C086";
			var x_space = width / (arr.length - 1); //水平点的间隙像素
			var xLen = 0;
			for (var i = 0; i < arr.length; i++) {
				var yValue = arr[i]; //纵坐标值
				xLen += x_space;
				var yPont = height - yValue * yStep;
				if (i == 0) {
					xLen = 0;
				}
				var m = xLen + "," + yPont;
				g.lineTo(xLen, yPont);
			}
			g.stroke();
			g.closePath();
		}
		drawLine.minCurve = draw;
	}();
	
	// canves绘图
	const canvesFn = function() {
		$(".trend").each(function(index, item) {
			if ($(item).attr('data-trends') != "0") {
				var arr = JSON.parse($(item).attr('data-trends'));
				var id = $(item).attr('data-id');
				var obj = document.getElementById("canDom_" + id);
				drawLine.minCurve(arr, obj, index);
			}
		});
	}

	// var mySwiper = new Swiper ('#announcement', {
	//     loop: true,
	//     autoplay:true,
	// });
    //
	// mySwiper.el.onmouseover = function(){
	//   mySwiper.autoplay.stop();
	// }
    //
	// mySwiper.el.onmouseout = function(){
	//   mySwiper.autoplay.start();
	// }
    //
	// var newsOne = new Swiper('#newsOne',{
	// 	speed:500,
	// 	autoplay: {
	// 		disableOnInteraction: false,
	// 		delay: 3000,
	// 	},
	// 	loop: true,
	// 	effect: 'flip',
	// 	grabCursor: true,
	// 	flipEffect: {
	// 	  slideShadows : true,
	// 	  limitRotation : true,
	// 	},
	// 	on:{
	// 		autoplay:function(){
	// 			setTimeout(function(){
	// 				newsTwo.slideNext();
	// 			},1000);
	// 			setTimeout(function(){
	// 				newsThree.slideNext();
	// 			},2000);
	// 		}
	// 	}
	// });
	// var newsTwo = new Swiper('#newsTwo',{
	// 	speed:500,
	// 	autoplay: false,
	// 	loop: true,
	// 	effect: 'flip',
	// 	grabCursor: true,
	// 	flipEffect: {
	// 	  slideShadows : true,
	// 	  limitRotation : true,
	// 	}
	// });
	// var newsThree = new Swiper('#newsThree',{
	// 	speed:500,
	// 	autoplay: false,
	// 	loop: true,
	// 	effect: 'flip',
	// 	grabCursor: true,
	// 	flipEffect: {
	// 	  slideShadows : true,
	// 	  limitRotation : true,
	// 	}
	// });
    //
	// newsOne.el.onmouseover = function(){
	//   newsOne.autoplay.stop();
	// }
    //
	// newsOne.el.onmouseout = function(){
	//   newsOne.autoplay.start();
	// }
    //
	// newsTwo.el.onmouseover = function(){
	//   newsOne.autoplay.stop();
	// }
    //
	// newsTwo.el.onmouseout = function(){
	//   newsOne.autoplay.start();
	// }
    //
	// newsThree.el.onmouseover = function(){
	//   newsOne.autoplay.stop();
	// }
    //
	// newsThree.el.onmouseout = function(){
	//   newsOne.autoplay.start();
	// }

	var video = document.getElementById("video");
	$(".shade").on("click",function(){
		$(this).css("display","none");
		$(".vodie_box").css("display","none");
		video.pause();
	});

	$(".isshow").on("click",function(){
		$(".shade").css("display","none");
		$(".vodie_box").css("display","none");
		video.pause();
	});

	$("#video").on("click",function(){
		if(video.paused){
			video.play();
		}else{
			video.pause();
		}
	});

	$(".btn").on("click",function(){
		$(".shade").css("display","block");
		$(".vodie_box").css("display","block");
	});

	$(".m_tab li").on("click",function(){
		LBool = true;
		$(this).addClass('active').siblings('li').removeClass('active');
		mark = $(this).attr("data-mark");
		market(mark);
	});

	$(".posture li").on("click",function(){
		LBool = true;
		_class = $(this).attr("data-class");
		$(this).addClass('active').siblings('li').removeClass('active');
		$("."+_class).css("display","block");
		market(mark);
	});
});