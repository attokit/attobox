<?php

//var_dump($opt);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document</title>
<link rel="stylesheet" href="https://wx.cgy.design/src/qrcode.css" />
</head>
<body>

<div id="qrbox" class="qr-flex">
    <img src="" id="qrcode">
    <div id="qrinfo"></div>
</div>

<script type="module">

import Poller from 'https://wx.cgy.design/src/poller.js';

const poller = new Poller('qyspkj', 'testscan');
poller.start().then(res=>{
    if (res!=false) {
        console.log(res);
    } else {
        console.log('scan false');
    }
});


</script>
</body>
</html>