<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>find password</title>
    <style>
        .wai-wrapper{
            display: flex;
            justify-content: center;
        }
        .wai-content {
            max-width: 702px;
            padding: 1rem 3.5rem 5rem 3.5rem;
            box-shadow: 1px 1px 5px 5px #eee;
        }
        .wai-topbar{
            width: 100%;
            height: 5rem;
            display: flex;
            justify-content: center;
        }
        .wai-topbar>#waiwailogo {
            width: 150px;
            height: 74px!important;
        }
        .wai-tip-content>.wai-tip-title{
            font-size: 1.5rem;
        }
        .wai-tip-content>.wai-tip-text{
            font-size: 1.3rem;
            line-height: 1.9rem;
            margin: 0;
        }
        .wai-btn{
            display: flex;
            justify-content: center;
            margin-top: 5rem;
            width: 100%;
            text-align: center;
        }
        .wai-btn>#wai-btn-link {
            font-size: 1.2rem;
            text-align: center;
            text-decoration: none!important;
            border-radius: .45rem;
            width: 250px;
            height: 50px;
        }
        .wai-btn>a>#wai-btn-img{
            width: 100%;
            height: 100%;
        }

    </style>
</head>
<body>
<div class="wai-wrapper">
    <div class="wai-content">
        <div class="wai-topbar">
            <img style="width: 130px;" id="waiwailogo" src="http://cucoe.oss-us-west-1.aliyuncs.com/logo.png" alt="">
        </div>
        <div class="wai-tip-content">
            <h4 class="wai-tip-title">Dear {{$name}}</h4>
            <p class="wai-tip-text">This is an email for finding your password on WaiWaiMall. If you haven't seen the request, please ignore it.</p>
            <p class="wai-tip-text">Please click the button below to reset your password.</p>
        </div>
        <div class="wai-btn">
            <!-- <a href="#"><p class="wai-tip-text">Find My Password</p></a> -->
            <a href="{{$url}}" id="wai-btn-link"><img id="wai-btn-img" src="http://cucoe.oss-us-west-1.aliyuncs.com/buton.png"></a>
        </div>
    </div>
</div>
<script>
    (function (doc, win) {
        var docEl = doc.documentElement,
            resizeEvt = 'orientationchange' in window ? 'orientationchange' : 'resize',
            recalc = function () {
                var clientWidth = docEl.clientWidth;
                if (!clientWidth) return;
                if(clientWidth >= 640){
                    docEl.style.fontSize = '12px';
                }else{
                    docEl.style.fontSize = 16 * (clientWidth / 640) + 'px';

                }
            };

        if (!doc.addEventListener) return;
        win.addEventListener(resizeEvt, recalc, false);
        doc.addEventListener('DOMContentLoaded', recalc, false);
    })(document, window);
</script>
</body>
</html>