$(function() {
	// 限价
	$(".issue_cont_one_input").on("input propertychange", function() {
		let _val = $(this).val() * 10000;
		let max = $(".max").html() * 10000;
		if (max>0 && _val > max) {
			$(this).val((max / 10000).toFixed(2));
			
		};
		if ($(".issue_cont_one_ins").val()) {
			let _val = Number($(".issue_cont_one_input").val()) * Number($(".issue_cont_one_ins").val());
			_val = _val.toFixed(2);
			if (_val == 0.0000) {
				_val = '';
			}
			$(".issue_cont_one_infore").val(_val);
		}
	});
	$(".issue_cont_one_input").on("blur", function() {
		let _val = $(this).val() * 10000;
		let min = $(".min").html() * 10000;
		let max = $(".max").html() * 10000;
		if (min>0 && _val < min) {
			$(this).val((min / 10000).toFixed(4));
		} else if (max>0 && _val > max) {
			$(this).val((max / 10000).toFixed(4));
		};
		if ($(".issue_cont_one_ins").val()) {
			let _val = Number($("#issue_cont_one_input").val()) * Number($("#issue_cont_one_ins").val());
			_val = _val.toFixed(2);
			if (_val == 0.0000) {
				_val = '';
			}
			$(".issue_cont_one_infore").val(_val);
		}
		if($(".issue_cont_one_input").val() == ""){
			$(".issue_cont_one_infore").val(0);
		} else {
			let _sum = Number($(".issue_cont_one_input").val()) * Number($(".issue_cont_one_ins").val());
			_sum = _sum.toFixed(2);
			if (_sum == 0.0000) {
				_sum = '';
			}
			$(".issue_cont_one_infore").val(_sum);
		}
	});

	// 发布数量
	$(".issue_cont_one_ins").on("input propertychange blur", function() {
		let _val = parseFloat($(this).val()).toFixed(6);
		let cont = parseFloat($(".quantity").html()).toFixed(6);
		let _value = _val * 1000000;
		let _cont = cont * 1000000;
		if(status == "sell"){
			if (_val) {
				if (_value < _cont) {
					let num = $(this).val() / cont * 100;
					let _sub = sub(cont, $(this).val());
					$("#numRange").val(num);
					$("#residue").html(_sub);
					if ($(".issue_cont_one_input").val()) {
						let _val = Number($(".issue_cont_one_input").val()) * Number($(".issue_cont_one_ins").val());
						_val = _val.toFixed(2);
						$(".issue_cont_one_infore").val(_val);
					}
				} else if (_value > _cont) {
					$(this).val(cont);
					let num = $(this).val() / cont * 100;
					$("#numRange").val(num);
					$("#residue").html('0.000000');
					if ($(".issue_cont_one_input").val()) {
						let _val = Number($(".issue_cont_one_input").val()) * Number($(".issue_cont_one_ins").val());
						_val = _val.toFixed(2);
						$(".issue_cont_one_infore").val(_val);
					}
				}
			}
		}else{
			if ($(".issue_cont_one_input").val()) {
				let _val = Number($(".issue_cont_one_input").val()) * Number($(".issue_cont_one_ins").val());
				_val = _val.toFixed(2);
				$(".issue_cont_one_infore").val(_val);
			}
		}
	});

	// 滑块数量
	let range = document.querySelector('#numRange');
	range.addEventListener('input', function() {
		let cont = parseFloat($(".quantity").html());
		let num = this.value / 100 * cont;
		let bool = String(num).split(".")[1];
		if (bool) {
			if (bool.length < 6) {
				num = num.toFixed(6);
			};
		}
		num = String(num).replace(/^(.*\..{6}).*$/, "$1");
		num = Number(num).toFixed(6);
		let _sub = sub(cont, num);
		if (this.value == 100) {
			cont = cont.toFixed(6);
			$(".issue_cont_one_ins").val(cont);
			$("#residue").html("0.000000");
			if ($(".issue_cont_one_input").val()) {
				let _val = Number($(".issue_cont_one_input").val()) * Number($(".issue_cont_one_ins").val());
				_val = _val.toFixed(2);
				$(".issue_cont_one_infore").val(_val);
			}
		} else if (this.value == 0) {
			$(".issue_cont_one_ins").val('');
			$("#residue").html(cont);
			if ($(".issue_cont_one_input").val()) {
				$(".issue_cont_one_infore").val('');
			}
		} else {
			$(".issue_cont_one_ins").val(num);
			$("#residue").html(_sub);
			if ($(".issue_cont_one_input").val()) {
				let _val = Number($(".issue_cont_one_input").val()) * Number($(".issue_cont_one_ins").val());
				_val = _val.toFixed(2);
				$(".issue_cont_one_infore").val(_val);
			}
		}
	});

	// 交易额
	$(".issue_cont_one_infore").on("input propertychange blur", function() {
		let max = Number($(".issue_cont_one_input").val()) * Number($(".issue_cont_one_ins").val());
		max = max.toFixed(4);
		let thisVal = Number($(".issue_cont_one_input").val());
		thisVal = thisVal.toFixed(4);
		if (thisVal > max) {
			thisVal = max;
			$(".issue_cont_one_infore").val(thisVal);
		}
		if ($(".issue_cont_one_input").val()) {
			let _val = div(Number($(".issue_cont_one_infore").val()), Number($(".issue_cont_one_input").val()));
			_val = _val.toFixed(6);
			let cont = parseFloat($(".quantity").html()).toFixed(6);
			let _value = _val * 1000000,
				_cont = cont * 1000000;
			if (_value >= _cont) {
				_val = cont
			}
			let num = _val / cont * 100;
			let _sub = sub(cont, _val);
			$("#numRange").val(num);
			$("#residue").html(_sub);
			$(".issue_cont_one_ins").val(_val);
		}
	});

	// 加减
	$(".issue_one_change_less").on("click", function() {
		let _val = $(".issue_cont_one_input").val();
		let min = $(".min").html();
		if (_val) {
			if (min > 0 && _val <= min) {
				_val = min;
			} else {
				_val = sub(_val, 0.1);
			}
			$(".issue_cont_one_input").val(_val);
			if ($(".issue_cont_one_ins").val()) {
				let _sum = Number($(".issue_cont_one_input").val()) * Number($(".issue_cont_one_ins").val());
				_sum = _sum.toFixed(4);
				if (_sum == 0.0000) {
					_sum = '';
				}
				$(".issue_cont_one_infore").val(_sum);
			}
		}
	});
	$(".issue_one_change_add").on("click", function() {
		let _val = $(".issue_cont_one_input").val();
		let max = $(".max").html();
		if (_val) {
			if (max > 0 && _val >= max) {
				_val = max;
			} else {
				_val = add(_val, 0.1);
			}
			$(".issue_cont_one_input").val(_val);
			if ($(".issue_cont_one_ins").val()) {
				let _sum = Number($(".issue_cont_one_input").val()) * Number($(".issue_cont_one_ins").val());
				_sum = _sum.toFixed(4);
				if (_sum == 0.0000) {
					_sum = '';
				}
				$(".issue_cont_one_infore").val(_sum);
			}
		}
	});


});
