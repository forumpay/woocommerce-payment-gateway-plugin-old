var fpamount = 0;
var fporderid = "";
var fpaddr = "";
var chpaymentid = "";
var fpcurrency = "";
var getqrurl = "";
var getsturl = "";
var timeerstar = "";
var fpTimer;

var script = document.createElement("script");
script.src = "https://code.jquery.com/jquery-3.4.1.min.js";
script.type = "text/javascript";
document.getElementsByTagName("head")[0].appendChild(script);

function forumpaygetrate(currency) {
  if (currency == "0") {
    clearInterval(fpTimer);
    $("#forumpay-err-div").hide();
    $("#fp-details-div").hide();
    return;
  }
  fpcurrency = currency;
  getqrurl = $("#forumpay-rateurl").attr("data");
  fporderid = $("#forumpay-orderid").attr("data");

  var data = {
    currency: currency,
    orderid: fporderid,
  };

  $("#qr-img-div").hide();
  $("#forumpay-err-div").hide();
  $("#forumpay-loading").show();

  jQuery.ajax({
    type: "POST",
    data: data,
    url: getqrurl,
    success: function (rdata) {
      $("#forumpay-loading").hide();

      var response_json = jQuery.parseJSON(rdata);

      if (response_json.status == "Yes") {
        $("#forumpay-addr").text(response_json.addr);
        $("#forumpay-tot").text(response_json.amount);
        $("#forumpay-exrate").text(response_json.exrate);
        $("#forumpay-examt").text(response_json.examt);
        $("#forumpay-netpfee").text(response_json.netpfee);
        $("#forumpay-waittime").text(response_json.waittime);
        $("#forumpay-txfee").text(response_json.txfee);
        $("#fp-details-div").show();

        fpcurrency = currency;
        fpaddr = response_json.addr;
        fpamount = response_json.amount;
        fppaymentid = response_json.payment_id;

        if (timeerstar == "") {
          timeerstar = "start";
          clearInterval(fpTimer);
          fpTimer = setInterval(function () { forumpaygetrate(currency); }, 5000);
        }
      } else {
        clearInterval(fpTimer);
        $("#fp-details-div").hide();
        $("#forumpay-err-div").show();
        $("#forumpay-err").text(response_json.errmgs);
      }
    },
    error: function () {
      $("#forumpay-loading").hide();
      clearInterval(fpTimer);
      alert("API Request fail");
    },
  });
}

function forumpaygetqrcode() {
  if (fpcurrency == "") return;

  clearInterval(fpTimer);
  timeerstar = "qr";

  getqrurl = $("#forumpay-qrurl").attr("data");
  fporderid = $("#forumpay-orderid").attr("data");

  var data = {
    currency: fpcurrency,
    orderid: fporderid,
  };

  $("#qr-img-div").hide();
  $("#forumpay-err-div").hide();
  $("#forumpay-loading").show();
  $("#forumpay-btn-div").hide();
  $("#forumpay-ccy-div").hide();

  jQuery.ajax({
    type: "POST",
    data: data,
    url: getqrurl,
    success: function (rdata) {
      $("#forumpay-loading").hide();

      var response_json = jQuery.parseJSON(rdata);

      if (response_json.status == "Yes") {
        $("#forumpay-addr").text(response_json.addr);
        $("#forumpay-qr-img").prop("src", response_json.qr_img);
        $("#forumpay-tot").text(response_json.amount);
        $("#forumpay-exrate").text(response_json.exrate);
        $("#forumpay-examt").text(response_json.examt);
        $("#forumpay-netpfee").text(response_json.netpfee);
        $("#forumpay-waittime").text(response_json.waittime);
        $("#forumpay-txfee").text(response_json.txfee);
        $("#qr-img-div").show();
        $("#fp-details-div").show();

        fpaddr = response_json.addr;
        fpamount = response_json.amount;
        fppaymentid = response_json.payment_id;

        if (timeerstar == "qr") {
          timeerstar = "start";
          clearInterval(fpTimer);
          fpTimer = setInterval(forumpaygetstaus, 5000);
        }
      } else {
        $("#forumpay-err-div").show();
        $("#forumpay-err").text(response_json.errmgs);
      }
    },
  });
}

function forumpaygetstaus() {
  getsturl = $("#forumpay-sturl").attr("data");

  var data = {
    currency: fpcurrency,
    addr: fpaddr,
    orderid: fporderid,
    amount: fpamount,
    paymentid: fppaymentid,
  };

  $("#forumpay-loading").show();

  jQuery.ajax({
    type: "POST",
    data: data,
    url: getsturl,
    success: function (response) {
      $("#forumpay-loading").hide();
      response_json = jQuery.parseJSON(response);
      if (response_json.status == "Confirmed") {
        var returl = (getsturl = $("#forumpay-returl").attr("data"));
        window.location.href = returl;
      } else if (response_json.status == "Cancelled") {
        var cancelurl = $("#forumpay-cancelurl").attr("data");
        window.location.href = cancelurl;
      } else {
        $("#forumpay-payst").text(response_json.status);
        $("#forumpay-payst-div").show();
      }
    },
  });
}
